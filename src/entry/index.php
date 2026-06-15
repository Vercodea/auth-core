<?php
include_once __DIR__ . '/../auth_init.php';
require_once __DIR__ . '/../middleware/file_access_lock/gateway_locker.php';

verify_pipeline_access(['index.php']);

class AuthInit {
    use AuthInit_trait;
}

AuthInit::init();

