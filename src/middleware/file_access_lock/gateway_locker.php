<?php
declare(strict_types=1);

function verify_pipeline_access(array $allowed_files): bool
{
    // 1. Capture the trace EXACTLY at the moment the check is requested
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    
    // We look at index 1 because index 0 is this wrapper function itself
    $caller_file = (!empty($trace[1]['file']) ? basename($trace[1]['file']) : null);

    // 2. Enforce the whitelist
    if ($caller_file === null || !in_array($caller_file, $allowed_files, true)) {
        
        // Log the breach for your security dashboards
        error_log("Vercodea Intrusion Prevention: Pipeline Violation by [" . ($caller_file ?? 'UNKNOWN') . "]");
        
        // Kill the request with a clean JSON defense response
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'blocked',
            'reason' => 'Directory Pipeline Violation'
        ]);
        exit;
    }

    return true;
}