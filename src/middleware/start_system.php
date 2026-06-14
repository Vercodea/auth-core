<?php
require_once('../config/db.php');
require_once('../Query/query_loader.php');
require_once('../middleware/file_access_lock/gateway_locker.php');
verify_pipeline_access(['start_system.php', 'gateway_locker.php', 'db.php', 'query_loader.php']);
function init_db() {
    $conn = mysqldb_manager();
    $sql = QueryLoader::Load('startup/createtables.sql', ['start_system.php']);
    $stmt = $conn->prepare($sql);
    $stmt->execute();
}