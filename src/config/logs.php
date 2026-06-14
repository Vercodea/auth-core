<?php
function log_activity($message) {
    // Use environment variable for log directory, fallback to default
    $log_dir = env('LOG_DIR', __DIR__ . '/../logs');
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    // Format the message with a precise timestamp
    $timestamp = date('Y-m-d H:i:s');
    $formatted_message = "[{$timestamp}] [SUCCESS/INFO] {$message}" . PHP_EOL;

    // Write/Append securely to the file
    file_put_contents($log_dir . '/activity.log', $formatted_message, FILE_APPEND | LOCK_EX);
}
?>