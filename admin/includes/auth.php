<?php
// admin/includes/auth.php – Include this at the top of every admin page
require_once __DIR__ . '/../../includes/functions.php';

function admin_check(): void {
    if (empty($_SESSION['admin_id'])) {
        redirect(APP_URL . '/admin/login.php?redirect=' . urlencode(current_url()));
    }
}

function current_admin(): ?array {
    if (empty($_SESSION['admin_id'])) return null;
    static $admin = null;
    if ($admin === null) {
        $s = db()->prepare("SELECT id,name,email,role,avatar FROM admins WHERE id = ?");
        $s->execute([$_SESSION['admin_id']]);
        $admin = $s->fetch() ?: null;
    }
    return $admin;
}
