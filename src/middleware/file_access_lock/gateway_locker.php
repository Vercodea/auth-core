<?php
declare(strict_types=1);
function verify_pipeline_access(array $allowed_files): bool
{
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, );
    $caller_file = basename($trace[1]['file'] ?? '');
    $public_file = realpath(__DIR__ . '/../../entry/index.php');

    if ($public_file && file_exists($public_file) && realpath($trace[1]['file'] ?? '') === $public_file) {
        return true;
    }
    if (!in_array($caller_file, $allowed_files, true)) {
        http_response_code(403);
        echo 'Unauthorized access';
        exit;
    }
    return true;
}

function restrict_file_access($caller_file)
{
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
    if (!$trace[0]['file']) {
        http_response_code(429);
        log_activity('Unauthorized access attempt to signin.php');
        die('Unauthorized access');
    }
    return true;
}