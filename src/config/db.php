<?php
/**
 * PHP Error Logging Configuration
 * Configures both display_errors and error_log() output
 */

// Load environment first to access env() helper
require_once('config_env.php');
require_once('../Query/query_loader.php');
config_env();

// Use environment variable for log directory, fallback to default
$log_dir = env('LOG_DIR', __DIR__ . '/../logs');
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true);
}

// Configure error logging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', $log_dir . '/php-errors.log');

// Environment-based display settings
$app_env = getenv('APP_ENV') ?: 'development';
if ($app_env === 'production') {
    ini_set('display_errors', 0);  // Hide errors from users
} else {
    ini_set('display_errors', 1);  // Show errors in development
}

require_once("../middleware/file_access_lock/gateway_locker.php");
verify_pipeline_access([
  'signup.php',
  'signin.php',
  'logout.php',
  'account_recover.php',
  'ratelimit.php',
  'otp_mailer.php',
  'email_otp_verifier.php'
]);
function redis_manager()
{
    /*
    $file_access = db_files_access();
    $condition = $file_access !== true;
    if ($condition) {
        return ['status' => false, 'msg' => 'Security Protocol Violated'];
    }
    */
    $host = env('REDIS_HOST', '127.0.0.1');
    $port = env('REDIS_PORT', 6379);
    $password = env('REDIS_PASSWORD', '');
    
    try {
        static $redis = null;
        if ($redis === null) {
            $redis = new Redis();
            $redis->connect($host, $port);
            
            // Authenticate if password is provided
            if (!empty($password)) {
                $redis->auth($password);
            }
        }
    } catch (Exception $e) {
        error_log('Redis connection error: ' . $e->getMessage());
        exit;
    }
    return $redis;
}
function mysqldb_manager() {
    $host = env('MYSQL_HOST', '127.0.0.1');
    $username = env('MYSQL_USERNAME', 'root');
    $password = env('MYSQL_PASSWORD', '');
    $dbname = env('MYSQL_DBNAME', '');
    $port = env('MYSQL_PORT', 3306);
    $socket = env('MYSQL_DIR_URL', null);
    $charset = env('CHARSET', 'utf8mb4');

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $dsn = ($socket !== null && $socket !== false)
        ? "mysql:unix_socket=$socket;dbname=$dbname;charset=$charset"
        : "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";
        $db = new PDO($dsn, $username, $password, $options);  
    }catch (Exception $e) {
        error_log($e->getMessage());
        exit;
    }
    return $db;
}
