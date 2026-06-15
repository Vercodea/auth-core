<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/logs.php';
require_once __DIR__ . '/../config/config_env.php';
require_once __DIR__ . '/../middleware/file_access_lock/gateway_locker.php';

$current_file = __DIR__ . '/../auth_init.php';
restrict_file_access($current_file);

config_env();
function logout_manager()
{
    $cache = redis_manager();
    $date = date('Y-m-d H:i:s');
    $session_token = $_COOKIE['csrf-token'] ?? null;
    $session_id = $_COOKIE['session-id'] ?? null;

    try {
        if (!$session_token || !$session_id) {
            error_log('Session not found in cookies');
            return ['status' => false, 'msg' => 'Failed to logout, Unauthorized access'];
        }

        $stored_session = $cache->hGet("session:{$session_id}", 'csrf_token');
        $user = $cache->hGet("session:{$session_id}", 'username');
        if (!$stored_session) {
            error_log('Session Not found in cache');
            return ['status' => false, 'msg' => 'Failed to logout, Unauthorized access'];
        }
        $verify = hash_equals($stored_session, hash('sha256', $session_token));
        if (!$verify) {
            error_log('Session mismatch');
            return ['status' => false, 'msg' => 'Invalid or Expired Session'];
        }
        $cache->del("session:{$session_id}");
        unset($_COOKIE['csrf-token']);
        unset($_COOKIE['session-id']);
        setcookie('csrf-token', '', time() - 3600, env('SESSION_COOKIE_PATH', '/'));
        setcookie('session-id', '', time() - 3600, env('SESSION_COOKIE_PATH', '/'));
        log_activity("{$user} logged out successfully at {$date}");
        return ['status' => true, 'msg' => 'Logout Successful'];
    } catch (Exception $e) {
        http_response_code(500);
        error_log($e->getMessage());
        exit;
    }
}