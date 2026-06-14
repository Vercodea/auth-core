<?php
require_once __DIR__ . '/../middleware/file_access_lock/gateway_locker.php';
class QueryLoader {
    public static function Load(string $filename, array $allowed_files) {
        verify_pipeline_access($allowed_files);
        $file_path = __DIR__ . "/Query_commands/{$filename}";
        if (!file_exists($file_path)) {
            throw new Exception("Query file not found: {$filename}");
        }
        return file_get_contents($file_path);
    }   
}