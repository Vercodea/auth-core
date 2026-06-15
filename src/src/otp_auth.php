<?php
require_once __DIR__ . '/../middleware/otp_manager/otp_mailer.php';
require_once __DIR__ . '/../middleware/file_access_lock/gateway_locker.php';
verify_pipeline_access(['otp_mailer.php']);
function otp_sender($email) {
    $mail_otp =signup_otp_manager($email);
    if (!$mail_otp) {
        log_activity("Failed to send OTP for email: {$email}");
        return ['status'=> false,'msg'=> 'Failed to Send OTP retry later'];
    }
    return signup_otp_manager($email);
}