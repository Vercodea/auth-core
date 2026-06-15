<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/logs.php';
require_once __DIR__ . '/../middleware/session_manager.php';
require_once __DIR__ . '/../middleware/file_access_lock/gateway_locker.php';
require_once __DIR__ . '/../middleware/ratelimit.php';
require_once __DIR__ . '/../config/config_env.php';
require_once __DIR__ . '/../Query/query_loader.php';

verify_pipeline_access(['ratelimit.php', 'session_manager.php']);
config_env();
function login_manager($username, $email, $password)
{
    $conn = mysqldb_manager();
    $cache = redis_manager();
    $user = !empty($username) ? $username : $email;
    $ratelimit = ratelimit_manager_signin($user);
    $condition = $ratelimit !== true;
    if ($condition) {
        return ['status' => false, 'msg' => 'Security Protocol Violated'];
    }
    try {
        $sql = QueryLoader::Load('signin/verify-user.sql', ['signin.php']);
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user, $user]);
        $identity = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$identity) {
            return ['status' => false, 'msg' => 'Invalid Credentials'];
        }
        if (!isset($identity['password'])) {
            error_log("User {$identity['id']} has no password hash");
            return ['status' => false, 'msg' => 'Account configuration error'];
        }

        if (password_verify($password, $identity['password'])) {
            $csrf_token = bin2hex(random_bytes(32));
            $domain = env('SESSION_COOKIE_DOMAIN', false);
            $session_data = [
                'user_id' => $identity['id'],
                'username' => $identity['username'],
                'csrf_token' => hash('sha256', $csrf_token),
            ];
            $session_id = bin2hex(random_bytes(16));
            $setsession = $cache->hMSet("session:{$session_id}", $session_data);
            $cache->expire("session:{$session_id}", env('SESSION_EXPIRY', 3600));
            if (!$setsession) {
                error_log("Failed to set session for user {$identity['id']}");
                return ['status' => false, 'msg' => 'An error occurred. Please try again later.'];
            }
            $cookies_conf = [
                'expires' => time() + env('SESSION_EXPIRY', 3600),
                'path' => env('SESSION_COOKIE_PATH', '/'),
                'domain' => $domain,
                'secure' => env('SESSION_COOKIE_SECURE', false),
                'httponly' => env('SESSION_COOKIE_HTTPONLY', true),
                'samesite' => env('SESSION_COOKIE_SAMESITE', 'Strict'),
            ];
            setcookie('csrf-token', $csrf_token, $cookies_conf);
            setcookie('session-id', $session_id, $cookies_conf);
            log_activity("User {$identity['username']} logged in successfully from " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown IP'));
            return ['status' => true, 'msg' => 'Login successful'];
        }
        return ['status' => false, 'msg' => 'Invalid Credentials'];
    } catch (Exception $e) {
        http_response_code(500);
        error_log($e->getMessage());
        return ['status' => false, 'msg' => 'An error occurred. Please try again later.'];
    }

}