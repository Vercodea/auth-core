<?php
require_once __DIR__ . '/../middleware/file_access_lock/gateway_locker.php';

class OtpMessageLoader {
    public static function Load(string $filename, array $allowed_files) {
        verify_pipeline_access($allowed_files);
        $file_path = __DIR__ . "/Otp_messages/messages/{$filename}";
        if (!file_exists($file_path)) {
            throw new Exception("OTP message file not found: {$filename}");
        }
        return file_get_contents($file_path);
    }   
}