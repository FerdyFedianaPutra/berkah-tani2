<?php
require_once __DIR__ . '/includes/auth.php';
session_unset();
session_destroy();
header('Location: ' . APP_URL . '/admin/login.php');
exit;
