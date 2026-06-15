<?php
require_once __DIR__ . '/src/signup.php';
require_once __DIR__ . '/src/logout.php';
require_once __DIR__ . '/src/otp_auth.php';
require_once __DIR__ . '/src/signin.php';
require_once __DIR__ . '/src/account_recover.php';
require_once __DIR__ . '/middleware/file_access_lock/gateway_locker.php';
require_once __DIR__ . '/middleware/start_system.php';
require_once __DIR__ . '/middleware/email_otp_verifier.php';

$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
$caller_file = __DIR__ . '/../src/entry/index.php';

if (!$trace[0]['file'] || !file_exists($caller_file)) {
    die('Unauthorized access');
}

verify_pipeline_access([]);
trait AuthInit_trait

{
    public static function init(){
        init_db();
    }
    private static function is_common_password($password)
    {
        static $common_passwords = null;

        if ($common_passwords === null) {
            $common_passwords = [];
            $file = __DIR__ . '/security-checks_tools/common_passwords.txt';
            $file_reserved = __DIR__ . '/security-checks_tools/reserved_passwords.txt';

            if (file_exists($file)) {
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $common_passwords[strtolower(trim($line))] = true;
                }
            }
        }

        return isset($common_passwords[strtolower(trim($password))]);
    }

    private static function is_reserved_password($password)
    {
        static $reserved_passwords = null;

        if ($reserved_passwords === null) {
            $reserved_passwords = [];
            $file_reserved = __DIR__ . '/security-checks_tools/reserved_passwords.txt';

            if (file_exists($file_reserved)) {
                $lines = file($file_reserved, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $reserved_passwords[strtolower(trim($line))] = true;
                }
            }
        }

        return isset($reserved_passwords[strtolower(trim($password))]);
    }

    public static function auth_send_otp($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => false, 'msg' => 'Invalid email format'];
        }
        return otp_sender($email);
    }

    public static function auth_register($name, $username, $email, $password, $otp_input)
    {
        // Email validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => false, 'msg' => 'Invalid email format'];
        }

        // Username validation
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
            return ['status' => false, 'msg' => 'Username must be 3-50 characters and can only contain letters, numbers, underscore, dot, and dash'];
        }

        // Name validation
        if (!preg_match('/^[a-zA-Z ]{2,100}$/', $name)) {
            return ['status' => false, 'msg' => 'Name must be 2-100 characters and can only contain letters and spaces'];
        }

        // OTP Validation
        if (!preg_match('/^\d{6}$/', $otp_input)) {
            return ['status' => false, 'msg' => 'Invalid OTP format'];
        }

        // Password validation
        if (strlen($password) < 8) {
            return ['status' => false, 'msg' => 'Password must be at least 8 characters'];
        }

        if (strlen($password) > 4096) {
            return ['status' => false, 'msg' => 'Password too long'];
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return ['status' => false, 'msg' => 'Password must contain at least one uppercase letter'];
        }

        if (!preg_match('/[a-z]/', $password)) {
            return ['status' => false, 'msg' => 'Password must contain at least one lowercase letter'];
        }

        if (!preg_match('/[0-9]/', $password)) {
            return ['status' => false, 'msg' => 'Password must contain at least one number'];
        }

        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            return ['status' => false, 'msg' => 'Password must contain at least one special character'];
        }

        // Check for common passwords
        if (self::is_common_password($password)) {
            return ['status' => false, 'msg' => 'Password is too common. Please choose a stronger password'];
        }

        // Check for reserved passwords
        if (self::is_reserved_password($password)) {
            return ['status' => false, 'msg' => 'Password is reserved. Please choose a different password'];
        }

        if ($password == $username || $password == $email) {
            return ['status' => false, 'msg' => 'Password cannot be the same as username or email'];
        }

        // Call registration
        return register_manager($name, $username, $email, $password, $otp_input);
    }

    public static function auth_login($username, $email, $password)
    {
        // Require at least username OR email
        if (empty($username) && empty($email)) {
            return ['status' => false, 'msg' => 'Username or email is required'];
        }

        // Validate email if provided
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => false, 'msg' => 'Invalid email format'];
        }

        // Validate username if provided
        if (!empty($username) && !preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
            return ['status' => false, 'msg' => 'Invalid username format'];
        }

        if (empty($password)) {
            return ['status' => false, 'msg' => 'Password is required'];
        }

        if (strlen($password) > 4096) {
            return ['status' => false, 'msg' => 'Password too long'];
        }

        return login_manager($username, $email, $password);
    }

    public static function auth_logout()
    {
        return logout_manager();
    }

    public static function auth_account_recovery_link($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => false, 'msg' => 'Invalid email format'];
        }
        return account_recovery_magic_link_manager($email);
    }

    public static function auth_verify_recovery($magic_id, $magic_token, $new_password, $confirm_password)
    {
        if (empty($new_password) || empty($confirm_password)) {
            return ['status' => false, 'msg' => 'Password fields cannot be empty'];
        }

        if ($confirm_password !== $new_password) {
            return ['status' => false, 'msg' => 'Passwords do not match'];
        }

        // Password validation
        if (strlen($new_password) < 8) {
            return ['status' => false, 'msg' => 'Password must be at least 8 characters'];
        }
        if (!preg_match('/[A-Z]/', $new_password)) {
            return ['status' => false, 'msg' => 'Password must contain at least one uppercase letter'];
        }
        if (!preg_match('/[a-z]/', $new_password)) {
            return ['status' => false, 'msg' => 'Password must contain at least one lowercase letter'];
        }
        if (!preg_match('/[0-9]/', $new_password)) {
            return ['status' => false, 'msg' => 'Password must contain at least one number'];
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $new_password)) {
            return ['status' => false, 'msg' => 'Password must contain at least one special character'];
        }

        return account_recovery_manager($magic_id, $magic_token, $confirm_password);
    }

}