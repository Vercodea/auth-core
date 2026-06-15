<?php
require_once __DIR__ . '/../src/signup.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/logs.php';
require_once __DIR__ . '/file_access_lock/gateway_locker.php';

verify_pipeline_access(['email_otp_verifier.php', 'signup.php']);

function verify_email($otp_input, $email_verify)
{
    $cache = redis_manager();

    try {
        $cached_otp = $cache->hGet($email_verify, 'otp');

        // Check if OTP exists
        if ($cached_otp === false) {
            error_log("OTP not found or expired for: $email_verify");
            log_activity("OTP verification failed (expired/not found) for: $email_verify");
            return false;
        }

        // Verify OTP (loose comparison for type flexibility)
        if ($otp_input == $cached_otp) {
            // Delete OTP after successful verification (one-time use)
            $cache->del($email_verify);
            log_activity("OTP verified successfully for: $email_verify");
            return true;
        }

        log_activity("OTP verification failed (incorrect code) for: $email_verify");
        return false;

    } catch (Exception $e) {
        http_response_code(500);
        error_log("OTP verification error for $email_verify: " . $e->getMessage());
        return false;
    }
}
?>