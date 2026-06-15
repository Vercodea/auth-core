<?php
require_once __DIR__ . '/file_access_lock/gateway_locker.php';

verify_pipeline_access(['signin.php']);

function session_manager()
{
    if (session_status() == PHP_SESSION_NONE) {
        session_start([
            'cookie_lifetime' => 0,
            'cookie_secure' => true,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Strict',
        ]);
    }
}
