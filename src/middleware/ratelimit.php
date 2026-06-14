<?php
require_once '../config/db.php';
require_once '../config/logs.php';
require_once 'network_check.php';
require_once '../config/config_env.php';
require_once ('../middleware/file_access_lock/gateway_locker.php');
require_once __DIR__ . '/../Query/query_loader.php';
verify_pipeline_access(['network_check.php', 'signin.php', 'signup.php', 'email_otp_verifier.php']);
config_env();
function ratelimit_manager_signup()
{
    $penalty_period = env('PENALTY_PERIOD', 60);
    $max_attempts = env('MAX_ATTEMPTS', 5);
    $limit_expires = env('LIMIT_EXPIRES', 3600);
    $cache = redis_manager();
    $ip_manager = check_ip();
    if ($ip_manager['ip']) {
        $ip = $ip_manager['ip'];
    } else {
        echo 'Malicious Activities spotted';
        exit;
    }
    try {
        $current_time = time();
        $saved_penalty = (int) $cache->hGet("reg-ratelimit:{$ip}", 'penalty-period');
        $time_remaining = $saved_penalty - $current_time;
        $final_time = ($time_remaining >= 0) ? $time_remaining : 0;
        if ($cache->exists("reg-ratelimit:{$ip}")) {
            if ($saved_penalty > 0) {
                if ($time_remaining > 0) {
                    log_activity("Rate limit exceeded for IP: {$ip} (signup attempts)");
                    $err_msg = [
                        'status' => 'blocked',
                        'reason' => 'Too many attempts',
                        'resets' => 'retry after ' . $final_time . 's'
                    ];
                    http_response_code(429);
                    header('Content-Type: application/json');
                    echo json_encode($err_msg);
                    exit;
                }
            }
        }
        $attempts = (int)$cache->hIncrBy("reg-ratelimit:{$ip}", 'attempts', 1);
        if ($attempts == 1) {
            $cache->hSet("reg-ratelimit:{$ip}", 'start-time', $current_time);
            $cache->expire("reg-ratelimit:{$ip}", (int)$limit_expires);
        }
        if ($attempts >= $max_attempts) {
            $resets_at = $current_time + $penalty_period;
            $cache->hSet("reg-ratelimit:{$ip}", 'penalty-period', $resets_at);
            $cache->expire("reg-ratelimit:{$ip}", $penalty_period);
            $err_msg = [
                'status' => 'blocked',
                'reason' => 'Too many attempts',
                'resets' => 'retry after ' . $final_time
            ];
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode($err_msg);
            exit;
        }
        return true;
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}
function ratelimit_manager_signin($user)
{
    $cache = redis_manager();
    $conn = mysqldb_manager();
    $penalty_period = (int)env('PENALTY_PERIOD', 60);
    $max_attempts = (int)env('MAX_ATTEMPTS', 5);
    $limit_expires = (int)env('LIMIT_EXPIRES', 3600);
    
    $ip_manager = check_ip();
    if (!$ip_manager['ip']) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Malicious Activities spotted';
        exit;
    }
    
    $sql = QueryLoader::Load('ratelimiter/sign-in.sql', ['ratelimit.php']);
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user, $user]);
    $identity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If the username/email doesn't exist, let the login manager handle the graceful error
    if (!$identity['id']) {
        return false;
    }
    
    $user_key = "log-ratelimit:{$identity['id']}";
    $ip_key = "log-ratelimit:{$ip_manager['ip']}";
    $current_time = time();
    
    try {
        // 1. Check IP penalty FIRST (attacker switching accounts)
        $ip_data = $cache->hMGet($ip_key, ['penalty-period']);
        $ip_penalty = isset($ip_data['penalty-period']) ? (int)$ip_data['penalty-period'] : 0;
        
        if ($ip_penalty > 0 && $ip_penalty > $current_time) {
            $time_remaining = $ip_penalty - $current_time;
            log_activity("Rate limit exceeded for IP: {$ip_manager['ip']} (signin attempts - IP penalty)");
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'blocked',
                'reason' => 'Too many attempts from this IP',
                'resets' => 'retry after ' . $time_remaining . 's'
            ]);
            exit;
        }
        
        // 2. Check user penalties
        $data = $cache->hMGet($user_key, ['penalty-period', 'attempts']);
        $saved_penalty = isset($data['penalty-period']) ? (int)$data['penalty-period'] : 0;
        $attempts = isset($data['attempts']) ? (int)$data['attempts'] : 0;
        
        if ($saved_penalty > 0 && $saved_penalty > $current_time) {
            $time_remaining = $saved_penalty - $current_time;
            log_activity("Rate limit exceeded for user: {$user} (signin attempts - user penalty)");
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'blocked',
                'reason' => 'Too many attempts',
                'resets' => 'retry after ' . $time_remaining . 's'
            ]);
            exit;
        }
        
        // 3. Increment attempts
        $attempts = $cache->hIncrBy($user_key, 'attempts', 1);
        
        if ((int)$attempts === 1) {
            $cache->hSet($user_key, 'start-time', $current_time);
            $cache->expire($user_key, $limit_expires);
            $cache->expire($ip_key, $limit_expires);
        }
        
        // 4. Lock them out if they cross the threshold
        if ($attempts >= $max_attempts) {
            $resets_at = $current_time + $penalty_period;
            
            // Lock user
            $cache->hSet($user_key, 'penalty-period', $resets_at);
            $cache->expire($user_key, $penalty_period);
            
            // Lock IP (store timestamp, not $penalty_period)
            $cache->hSet($ip_key, 'penalty-period', $resets_at);
            $cache->expire($ip_key, $penalty_period);
            
            log_activity("Account locked: {$user} exceeded max signin attempts from {$ip_manager['ip']}");
            
            $domain = env('DOMAIN', 'localhost');
            $otp_api_key = env('OTP_API_KEY', '');
            $ip_address = $ip_manager['ip'];
            $blocked_at = date('Y-m-d H:i:s');
            $block_expires = $penalty_period;
            $send_otp_url = (string)env('SEND_OTP_URL', '');
            $html_message = OtpMessageLoader::Load('signup_otp.html', ['otp_mailer.php']);
            $payload = [
                'from' => $domain,
                'to' => $identity['email'],
                'subject' => 'Verification OTP',
                'html' => $html_message
            ];

            $ch = curl_init($send_otp_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, env('CURLOPT_SSL_VERIFYPEER', false));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $otp_api_key,
                'Content-Type: application/json'
            ]);
            curl_exec($ch);
            
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'blocked',
                'reason' => 'Too many attempts',
                'resets' => 'retry after ' . $penalty_period . 's'
            ]);
            exit;
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Rate limiter cache error: " . $e->getMessage());
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'msg' => 'Security subsystem unavailable'
        ]);
        exit;
    }
}