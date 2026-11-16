<?php
/**
 * TrackShield - Database & application configuration.
 * This file is server-side only; access is blocked by config/.htaccess.
 */

// ---- Database credentials (update for your environment) ----
define('DB_HOST', getenv('TS_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('TS_DB_PORT') ?: '3306');
define('DB_NAME', getenv('TS_DB_NAME') ?: 'trackshield_db');
define('DB_USER', getenv('TS_DB_USER') ?: 'root');
define('DB_PASS', getenv('TS_DB_PASS') !== false ? getenv('TS_DB_PASS') : '');

// ---- Application settings ----
define('APP_NAME', 'TrackShield');
define('APP_DEBUG', false);                 // never true in production
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/emergencies/');
define('UPLOAD_MAX_BYTES', 5 * 1024 * 1024); // 5 MB
define('APP_TIMEZONE', 'Asia/Kolkata');

date_default_timezone_set(APP_TIMEZONE);

// Base URL path of the application (e.g. "/TrackShield"), auto-detected.
if (!defined('BASE_URL')) {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $appRoot = realpath(dirname(__DIR__));
    $base = '/TrackShield';
    if ($docRoot && $appRoot && strpos($appRoot, $docRoot) === 0) {
        $base = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
    }
    define('BASE_URL', rtrim($base, '/'));
}

/**
 * Returns a shared PDO connection.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
        } catch (PDOException $e) {
            error_log('TrackShield DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            exit(APP_DEBUG ? 'Database connection failed: ' . htmlspecialchars($e->getMessage())
                           : 'Service temporarily unavailable. Please try again later.');
        }
    }
    return $pdo;
}
