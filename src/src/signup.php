<?php
require_once("../config/logs.php");
require_once("../middleware/email_otp_verifier.php");
require_once("../middleware/file_access_lock/gateway_locker.php");
require_once("../middleware/ratelimit.php");
require_once("../config/db.php");
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
        return ['status' => false, 'msg' => 'Please request OTP to continue'];
    }
    if (env('OTP_VERIFICATION_ENABLED', false)) {
        $otp_verify = verify_email($otp_input, $email_verify);
        if (!$otp_verify) {
            return ['status' => false, 'msg' => 'OTP verified Failed'];
        }
    }

    $sql = QueryLoader::Load('signup/verify_user.sql', ['signup.php']);
    $stmt = $conn->prepare($sql);
    $stmt->execute([$username, $email]);

    if ($stmt->fetch()) {
        return ["status" => false, "msg" => "Email or Username already exists"];
    }
    $sql = QueryLoader::Load('signup/add-user.sql', ['signup.php']);
    $stmt = $conn->prepare($sql);
    $stmt->execute([$name, $username, $email, password_hash($password, PASSWORD_DEFAULT)]);
    log_activity("New user registered: {$username} ({$email})");
    return ["status" => true, "msg" => "Registration successful"];
}
?>