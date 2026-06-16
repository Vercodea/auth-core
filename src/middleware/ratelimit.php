<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/logs.php';
require_once __DIR__ . '/network_check.php';
require_once __DIR__ . '/../config/config_env.php';
require_once __DIR__ . '/file_access_lock/gateway_locker.php';
require_once __DIR__ . '/../Query/query_loader.php';
verify_pipeline_access(['network_check.php', 'signin.php', 'signup.php', 'email_otp_verifier.php', 'ratelimit.php']);
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
        $attempts = (int) $cache->hIncrBy("reg-ratelimit:{$ip}", 'attempts', 1);
        if ($attempts == 1) {
            $cache->hSet("reg-ratelimit:{$ip}", 'start-time', $current_time);
            $cache->expire("reg-ratelimit:{$ip}", (int) $limit_expires);
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
    $penalty_period = env('PENALTY_PERIOD', 60);
    $max_attempts = env('MAX_ATTEMPTS', 5);
    $limit_expires = env('LIMIT_EXPIRES', 3600);
    $cache = redis_manager();
    $ip_manager = check_ip();
    if ($ip_manager['ip']) {
        $ip = $ip_manager['ip'];
    } else {
        echo 'Malicious Activities spotted';
        error_log("Malicious activity detected: No valid IP address found during signin attempt from unknown source.");
        exit;
    }
    try {
        $current_time = time();
        $saved_penalty = (int) $cache->hGet("log-ratelimit:{$ip}", 'penalty-period');
        $time_remaining = $saved_penalty - $current_time;
        $final_time = ($time_remaining >= 0) ? $time_remaining : 0;
        if ($cache->exists("log-ratelimit:{$ip}")) {
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
        $attempts = (int) $cache->hIncrBy("log-ratelimit:{$ip}", 'attempts', 1);
        if ($attempts == 1) {
            $cache->hSet("log-ratelimit:{$ip}", 'start-time', $current_time);
            $cache->expire("log-ratelimit:{$ip}", (int) $limit_expires);
        }
        if ($attempts >= $max_attempts) {
            $resets_at = $current_time + $penalty_period;
            $cache->hSet("log-ratelimit:{$ip}", 'penalty-period', $resets_at);
            $cache->expire("log-ratelimit:{$ip}", $penalty_period);
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

        // Database connection
        $conn = mysqldb_manager();

        // Load and execute SQL query to find user by username or email
        $sql = QueryLoader::Load('ratelimiter/sign-in.sql', ['ratelimit.php']);
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user, $user]);

        // Fetch user identity
        $identity = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if user exists
        if (empty($identity['id'])) {
            log_activity("Invalid login attempt for user: {$user}, user not found" . ' (signin attempt)');
            return false;
        }

        $user_key = "log-ratelimit:{$identity['id']}";

        $attempts = (int) $cache->hIncrBy($user_key, 'attempts', 1);
        if ($attempts == 1) {
            $cache->hSet($user_key, 'start-time', $current_time);
            $cache->expire($user_key, (int) $limit_expires);
        }

        $data = $cache->hMGet($user_key, ['penalty-period', 'attempts']);
        $saved_penalty = isset($data['penalty-period']) ? (int) $data['penalty-period'] : 0;
        $attempts = isset($data['attempts']) ? (int) $data['attempts'] : 0;

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

        if ($attempts >= $max_attempts) {
            $resets_at = $current_time + $penalty_period;

            // Lock user
            $cache->hSet($user_key, 'penalty-period', $resets_at);
            $cache->expire($user_key, $penalty_period);

            log_activity("Account locked: {$user} exceeded max signin attempts from {$ip_manager['ip']}");

            $domain = env('DOMAIN', 'localhost');
            $otp_api_key = env('OTP_API_KEY', '');
            $ip_address = $ip_manager['ip'];
            $blocked_at = date('Y-m-d H:i:s');
            $block_expires = $penalty_period;
            $send_otp_url = (string) env('SEND_OTP_URL', '');
            $html_message = OtpMessageLoader::Load('signup_otp.html', ['otp_mailer.php']);
            $html_message = str_replace('{$ip_address}', $ip_address, $html_message);
            $html_message = str_replace('{$blocked_at}', $blocked_at, $html_message);
            $html_message = str_replace('{$block_expires}', $block_expires, $html_message);
            $payload = [
                'from' => $domain,
                'to' => $identity['email'],
                'subject' => 'Account Locked Due to Suspicious Activity',
                'html' => $html_message
            ];
            $payload = [
                'from' => $domain,
                'to' => $identity['email'],
                'subject' => 'Verification OTP',
                'html' => $html_message
            ];

            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'blocked',
                'reason' => 'Too many attempts',
                'resets' => 'retry after ' . $penalty_period . 's'
            ]);

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
            $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($response_code >= 400) {
                error_log("Failed to send OTP email to {$identity['email']} after account lockout. HTTP Status: {$response_code}");
                exit;
            }

            exit;
        }

        return true;
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}
