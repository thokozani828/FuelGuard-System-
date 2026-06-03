<?php
/**
 * FuelGuard Pro - Central Configuration
 * Loads environment variables from .env file
 */

// Load .env file
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Define Supabase Constants
if (!defined('SUPABASE_URL')) {
    define('SUPABASE_URL', getenv('SUPABASE_URL') ?: 'https://shdaldiqnbtlgjajxroi.supabase.co');
}

if (!defined('SUPABASE_KEY')) {
    define('SUPABASE_KEY', getenv('SUPABASE_KEY') ?: '');
}

// Database Connection Settings (from db.php context)
define('DB_HOST', getenv('DB_HOST') ?: 'db.shdaldiqnbtlgjajxroi.supabase.co');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'postgres');
define('DB_USER', getenv('DB_USER') ?: 'postgres');
define('DB_PASS', getenv('DB_PASS') ?: 'RangerFord828@2');

/**
 * Validate that a user or driver is logged in
 */
function validateSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['driver_id'])) {
        http_response_code(401);
        echo json_encode([
            'status' => 401,
            'success' => false,
            'error' => 'Unauthorized: Please log in to access this data.'
        ]);
        exit();
    }
}
?>