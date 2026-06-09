<?php
// ============================================================
//  BERKAH TANI – Database Configuration
// ============================================================

define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'berkah_tani');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

// Midtrans
define('MIDTRANS_SERVER_KEY', getenv('MIDTRANS_SERVER_KEY') ?: '');
define('MIDTRANS_CLIENT_KEY', getenv('MIDTRANS_CLIENT_KEY') ?: '');
define('MIDTRANS_IS_SANDBOX', true); // set false for production

// App
define('APP_URL',    rtrim(getenv('APP_URL') ?: 'http://localhost/berkah-tani', '/'));
define('APP_NAME',   'Berkah Tani');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');

// Session
define('SESSION_LIFETIME', 86400 * 30); // 30 hari remember me

/**
 * Singleton PDO connection
 */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        } catch (PDOException $e) {
            // Di production, jangan tampilkan pesan error detail
            error_log('DB Connection Error: ' . $e->getMessage());
            die(json_encode(['error' => 'Database connection failed']));
        }
    }
    return $pdo;
}
