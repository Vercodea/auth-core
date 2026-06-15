<?php
require_once __DIR__ . '/../config/logs.php';
require_once __DIR__ . '/../middleware/email_otp_verifier.php';
require_once __DIR__ . '/../middleware/file_access_lock/gateway_locker.php';
require_once __DIR__ . '/../middleware/ratelimit.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../Query/query_loader.php';

verify_pipeline_access(['otp_mailer.php', 'ratelimit.php', 'email_otp_verifier.php', 'query_loader.php']);
function register_manager($name, $username, $email, $password, $otp_input)
{
    $conn = mysqldb_manager();
    $ratelimit = ratelimit_manager_signup();
    $condition = $ratelimit !== true;
    if ($condition) {
        return ['status' => false, 'msg' => 'Security Protocol Violated'];
    }
    $email_verify = $_COOKIE['email'] ?? null;
    if (!$email_verify) {
        log_activity("OTP verification failed for {$email} - No OTP requested (signup attempt)");
        return ['status' => false, 'msg' => 'Please request OTP to continue'];
    }
    if (env('OTP_VERIFICATION_ENABLED', false)) {
        $otp_verify = verify_email($otp_input, $email_verify);
        if (!$otp_verify) {
            log_activity('OTP verification failed for ' . ($email_verify ?? 'unknown email') . ' - Invalid OTP' . ' (signup attempt)');
            return ['status' => false, 'msg' => 'OTP verification Failed'];
        }
    }

    $sql = QueryLoader::Load('signup/verify_user.sql', ['signup.php']);
    $stmt = $conn->prepare($sql);
    $stmt->execute([$username, $email]);

    if ($stmt->fetch()) {
        log_activity("Registration attempt with existing username/email: {$username}/{$email}" . ' (signup attempt)');
        return ["status" => false, "msg" => "Email or Username already exists"];
    }
    $sql = QueryLoader::Load('signup/add-user.sql', ['signup.php']);
    $stmt = $conn->prepare($sql);
    $stmt->execute([$name, $username, $email, password_hash($password, PASSWORD_DEFAULT)]);
    log_activity("New user registered: {$username} ({$email})" . ' (signup attempt)');
    return ["status" => true, "msg" => "Registration successful"];
}
?>