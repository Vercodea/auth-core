<?php

/**
 * Load environment variables from .env file
 * Call ONCE at application bootstrap
 */
function config_env()
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    
    try {
        require_once __DIR__ . '/../../../../../vendor/autoload.php';
        $root_dir = dirname(__DIR__, 5);
        $dotenv = Dotenv\Dotenv::createImmutable($root_dir);
        $dotenv->load();
        
    } catch (Exception $e) {
        error_log("ENV loading failed: " . $e->getMessage());
    }
}

/**
 * Get environment variable with default fallback
 * Use this EVERYWHERE instead of $_ENV or getenv() directly
 * 
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function env($key, $default = null)
{
    // Try getenv() first (most reliable across all PHP configurations)
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    
    // Fallback to $_ENV (in case getenv missed it)
    $value = $_ENV[$key] ?? null;
    if ($value !== null) {
        return $value;
    }
    
    // Fallback to $_SERVER (for Apache SetEnv compatibility)
    $value = $_SERVER[$key] ?? null;
    if ($value !== null) {
        return $value;
    }
    
    return $default;
}