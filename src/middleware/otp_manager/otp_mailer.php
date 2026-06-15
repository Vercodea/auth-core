<?php
//require_once('../../src/signup.php');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/logs.php';
require_once __DIR__ . '/../../config/config_env.php';
require_once __DIR__ . '/../file_access_lock/gateway_locker.php';
require_once __DIR__ . '/Otp_messages/message_loader.php';
config_env();

verify_pipeline_access(['otp_auth.php', 'otp_mailer.php', 'email_otp_verifier.php']);

function signup_otp_manager($email)
{
    $otp_api_key = env('OTP_API_KEY', '');
    $otp_code = random_int(100000, 999999);
    $send_otp_url = (string) env('SEND_OTP_URL', '');
    $cache = redis_manager();
    try {
        if (!$cache->exists($email)) {
            $otp_details = ['otp' => $otp_code, 'time' => date('Y-m-d H:i:s')];
            $cache->hMSet($email, $otp_details);
            $expires = (int) env('OTP_EXPIRES', 300);
            $cache->expire($email, $expires);
            $domain = env('DOMAIN', 'localhost');
            setcookie('email', $email, time() + $expires, '/', '', false, true);
            $html_message = OtpMessageLoader::Load('signup_otp.html', ['otp_mailer.php']);
            $html_message = str_replace('{$otp_code}', $otp_code, $html_message);
            $payload = [
                'from' => $domain,
                'to' => $email,
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
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($http_code !== 200) {
                error_log("Magic link email failed to send to {$email}. HTTP: {$http_code}");
                return ['status' => false, 'msg' => 'Failed to send recovery email. Please try again.'];
            }
        }
        log_activity("OTP sent to {$email}");
        return ['status' => true, 'msg' => 'OTP sent successfully'];
    } catch (Exception $e) {
        error_log($e->getMessage());
        return ['status' => false, 'msg' => 'Failed to send OTP. Please try again.'];
    }
}

function account_recovery_magic_link_manager($email)
{
    $cache = redis_manager();
    $api_key = env('OTP_API_KEY', '');
    $send_email_url = (string) env('SEND_OTP_URL', '');

    try {
        // Generate magic link tokens
        $magic_token = bin2hex(random_bytes(16));
        $magic_id = random_int(100000, 999999);
        $reset_page = env('ACCOUNT_RECOVERY_SUBDOMAIN_PATH', 'http://localhost');
        $reset_url = "{$reset_page}?token={$magic_token}&id={$magic_id}";

        // Load email template
        $html_message = OtpMessageLoader::Load('account_recovery.html', ['account_recover.php']);
        $html_message = str_replace('{$reset_url}', $reset_url, $html_message);

        $limit_time = (int) env('ACCOUNT_RECOVERY_LIMIT_TIME', 300);
        $time_remaining = $limit_time;

        // Check if recovery already requested for this magic_id
        if ($cache->hExists("account_recovery:{$magic_id}")) {
            return ['status' => false, 'msg' => "A recovery email has already been sent. Please check your inbox or wait {$time_remaining} seconds to request a new one."];
        }

        // Store recovery details
        $details = [
            'token' => hash('sha256', $magic_token),
            'email' => $email,
            'time' => date('Y-m-d H:i:s')
        ];
        $cache->hMSet("account_recovery:{$magic_id}", $details);
        $cache->expire("account_recovery:{$magic_id}", $limit_time);

        $cookies_conf = [
            'expires' => time() + $limit_time,
            'path' => env('SESSION_COOKIE_PATH', '/'),
            'domain' => env('DOMAIN', 'localhost'),
            'secure' => env('SESSION_COOKIE_SECURE', false),
            'httponly' => env('SESSION_COOKIE_HTTPONLY', true),
            'samesite' => env('SESSION_COOKIE_SAMESITE', 'Strict'),
        ];

        setcookie('email', $email, $cookies_conf);
        // Send magic link email
        $payload = [
            'from' => env('DOMAIN', 'localhost'),
            'to' => $email,
            'subject' => 'Password Recovery Link',
            'html' => $html_message
        ];

        $ch = curl_init($send_email_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, env('CURLOPT_SSL_VERIFYPEER', false));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ]);
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code !== 200) {
            error_log("Magic link email failed to send to {$email}. HTTP: {$http_code}");
            return ['status' => false, 'msg' => 'Failed to send recovery email. Please try again.'];
        }

        log_activity("Account recovery link sent to {$email}");
        return ['status' => true, 'msg' => 'Recovery link sent to your email'];

    } catch (Exception $e) {
        http_response_code(500);
        error_log("Magic link error for {$email}: " . $e->getMessage());
        return ['status' => false, 'msg' => 'An error occurred. Please try again later.'];
    }
}