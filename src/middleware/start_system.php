<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../Query/query_loader.php';
require_once __DIR__ . '/file_access_lock/gateway_locker.php';
verify_pipeline_access(['start_system.php', 'gateway_locker.php', 'db.php', 'query_loader.php']);
function init_db()
{
    try {
        $conn = mysqldb_manager();
        $sql = QueryLoader::Load('startup/createtables.sql', ['start_system.php']);
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Database initialization error: " . $e->getMessage());
    }
}