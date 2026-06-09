<?php
require_once __DIR__ . '/includes/functions.php';

// Hapus remember token dari DB
if (!empty($_SESSION['user_id'])) {
    db()->prepare("UPDATE users SET remember_token = NULL WHERE id = ?")
         ->execute([$_SESSION['user_id']]);
}

// Hapus cookie remember me
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
}

// Hapus session
session_unset();
session_destroy();

// Redirect ke login
header('Location: ' . APP_URL . '/login.php?logged_out=1');
exit;
