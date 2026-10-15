<?php
// applications/cancel.php — Membatalkan lamaran (hanya pemilik, status pending, POST + CSRF).

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php'; // sudah berisi session_start()

require_login();

if (!is_post()) {
  flash('alert', 'Pembatalan lamaran harus lewat tombol yang tersedia.');
  redirect('applications/my.php');
}

if (!csrf_check($_POST['csrf_token'] ?? '')) {
  flash('alert', 'Token keamanan tidak valid.');
  redirect('applications/my.php');
}

$app_id_raw = $_POST['application_id'] ?? '';
$app_id = (ctype_digit((string) $app_id_raw) && (int) $app_id_raw > 0) ? (int) $app_id_raw : 0;

if ($app_id === 0) {
  flash('alert', 'Lamaran tidak ditemukan.');
  redirect('applications/my.php');
}

$user = current_user($pdo);
if (!$user) {
  redirect('applications/my.php');
}

$stmt = $pdo->prepare("
  SELECT application_id, applicant_id, status, project_id
  FROM applications
  WHERE application_id = ?
  LIMIT 1
");
$stmt->execute([$app_id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
  flash('alert', 'Lamaran tidak ditemukan atau sudah dihapus.');
  redirect('applications/my.php');
}

if ((int) $app['applicant_id'] !== (int) $user['user_id']) {
  flash('alert', 'Kamu tidak berhak membatalkan lamaran ini.');
  redirect('applications/my.php');
}

if ($app['status'] !== 'pending') {
  flash('alert', 'Lamaran yang sudah diterima/ditolak tidak dapat dibatalkan.');
  redirect('applications/my.php');
}

try {
  $stmt = $pdo->prepare("DELETE FROM applications WHERE application_id = ? AND applicant_id = ? AND status = 'pending'");
  $stmt->execute([$app_id, (int) $user['user_id']]);
  if ($stmt->rowCount() > 0) {
    flash('success', 'Lamaran berhasil dibatalkan.');
  } else {
    flash('alert', 'Lamaran tidak bisa dibatalkan.');
  }
} catch (PDOException $ex) {
  flash('alert', 'Lamaran gagal dibatalkan. Silakan coba lagi.');
}

redirect('applications/my.php');
