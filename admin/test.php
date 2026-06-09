<?php
require_once __DIR__ . '/../config/database.php';

echo "<h2>1. Koneksi DB</h2>";
try {
    $pdo = db();
    echo "✅ Koneksi berhasil ke database: <b>" . DB_NAME . "</b><br>";
} catch (Exception $e) {
    echo "❌ Gagal: " . $e->getMessage();
    die();
}

echo "<h2>2. Cek tabel admins</h2>";
$rows = $pdo->query("SELECT id, name, email, password FROM admins")->fetchAll();
if ($rows) {
    foreach ($rows as $r) {
        echo "ID: {$r['id']} | Email: {$r['email']}<br>";
    }
} else {
    echo "❌ Tabel admins kosong!";
    die();
}

echo "<h2>3. Cek Hash</h2>";
$hash = $rows[0]['password'] ?? '';
echo "Hash dari DB (full): <b>" . htmlspecialchars($hash) . "</b><br>";
echo "Panjang hash: <b>" . strlen($hash) . "</b> karakter<br><br>";

$tests = ['password', 'BerkahTani2025!', 'admin', 'admin123'];
foreach ($tests as $p) {
    $ok = password_verify($p, $hash) ? '✅ COCOK' : '❌ salah';
    echo "$p → $ok<br>";
}

echo "<h2>4. Reset Password Langsung</h2>";
$new_hash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE email = 'admin@berkahtani.com'");
$stmt->execute([$new_hash]);
echo "✅ Password berhasil direset ke: <b>admin123</b><br>";
echo "Hash baru: <b>" . htmlspecialchars($new_hash) . "</b><br>";

echo "<h2>5. Verifikasi ulang</h2>";
$row = $pdo->query("SELECT password FROM admins WHERE email = 'admin@berkahtani.com'")->fetch();
$ok = password_verify('admin123', $row['password']) ? '✅ COCOK' : '❌ masih salah';
echo "Cek password 'admin123' → $ok<br>";

echo "<br><hr><b>Sekarang coba login dengan password: admin123</b>";