<?php
require_once __DIR__ . '/../middleware/file_access_lock/gateway_locker.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/logs.php';
require_once __DIR__ . '/../Query/query_loader.php';

verify_pipeline_access(['account_recover.php', 'gateway_locker.php', 'auth_init.php', 'otp_mailer.php']);

function account_recovery_manager($magic_id, $magic_token, $confirm_password) {
    $cache = redis_manager();
    $conn = mysqldb_manager();
    $data = $cache->hGetAll("account_recovery:{$magic_id}");
    
    if (empty($data)) {
        log_activity("Invalid recovery link attempt for magic ID: {$magic_id}");
        return ['status' => false, 'msg' => 'Invalid recovery link. Please request a new one.'];
    };

    $token = $data['token'];
    $verify_token = hash_equals($token, hash('sha256', $magic_token));
    if (!$verify_token) {
        log_activity("Invalid recovery link attempt for magic ID: {$magic_id}");
        return ['status' => false, 'msg' => 'Invalid recovery link. Please request a new one.'];
    }
    $email = $_COOKIE['email'];
    $sql = QueryLoader::Load('account_recovery/recover_account.sql', ['account_recover.php']);
    $stmt = $conn->prepare($sql);
    $stmt->execute([password_hash($confirm_password, PASSWORD_DEFAULT), $email]);

    $cache->del("account_recovery:{$magic_id}");
    log_activity("Account recovery successful for email: {$email}");
    return ["status"=> true,"msg"=> "Account recovery Successful"];
}