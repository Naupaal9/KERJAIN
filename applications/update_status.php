<?php
// applications/update_status.php — Perbarui status lamaran (accept/reject).
// Gunakan transaksi untuk menjaga konsistensi: saat accept -> set accepted, reject others, set project to in_progress.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php'; // sudah berisi session_start()

require_login();

if (!is_post()) {
  flash('alert', 'Perubahan status harus lewat form yang tersedia.');
  redirect('projects/my.php');
}

if (!csrf_check($_POST['csrf_token'] ?? '')) {
  flash('alert', 'Token keamanan tidak valid.');
  redirect('projects/my.php');
}

$app_id_raw = $_POST['application_id'] ?? '';
$app_id = (ctype_digit((string) $app_id_raw) && (int) $app_id_raw > 0) ? (int) $app_id_raw : 0;

$pid_raw = $_POST['project_id'] ?? '';
$project_id = (ctype_digit((string) $pid_raw) && (int) $pid_raw > 0) ? (int) $pid_raw : 0;

$action = $_POST['action'] ?? '';
if (!in_array($action, ['accept', 'reject'], true)) {
  flash('alert', 'Aksi tidak valid.');
  redirect('projects/my.php');
}

if ($app_id === 0 || $project_id === 0) {
  flash('alert', 'Data tidak valid.');
  redirect('projects/my.php');
}

$user = current_user($pdo);
if (!$user) {
  redirect('projects/my.php');
}

// Ambil project dan pastikan owner
$stmt = $pdo->prepare("
  SELECT project_id, owner_id, status
  FROM projects
  WHERE project_id = ?
  LIMIT 1
");
$stmt->execute([$project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
  flash('alert', 'Project tidak ditemukan.');
  redirect('projects/my.php');
}

if ((int) $project['owner_id'] !== (int) $user['user_id']) {
  flash('alert', 'Kamu hanya boleh mengelola lamaran project milikmu.');
  redirect('projects/show.php?id=' . (int) $project['project_id']);
}

// Project harus open untuk menerima keputusan baru
if ($project['status'] !== 'open') {
  flash('alert', 'Project sudah tidak berstatus open, tidak bisa memilih pelamar baru.');
  redirect('applications/manage.php?project_id=' . (int) $project['project_id']);
}

// Ambil lamaran yang dituju
$stmt = $pdo->prepare("
  SELECT application_id, project_id, applicant_id, status
  FROM applications
  WHERE application_id = ?
  LIMIT 1
");
$stmt->execute([$app_id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
  flash('alert', 'Lamaran tidak ditemukan.');
  redirect('applications/manage.php?project_id=' . (int) $project['project_id']);
}

// Verifikasi konsistensi
if ((int) $app['project_id'] !== (int) $project_id) {
  flash('alert', 'Data tidak konsisten.');
  redirect('applications/manage.php?project_id=' . (int) $project['project_id']);
}

if ($app['status'] !== 'pending') {
  flash('alert', 'Lamaran ini sudah tidak berstatus pending.');
  redirect('applications/manage.php?project_id=' . (int) $project['project_id']);
}

try {
  $pdo->beginTransaction();

  if ($action === 'reject') {
    $stmt = $pdo->prepare("UPDATE applications SET status = 'rejected' WHERE application_id = ? AND status = 'pending'");
    $stmt->execute([$app_id]);
    $pdo->commit();
    flash('success', 'Lamaran berhasil ditolak.');
    redirect('applications/manage.php?project_id=' . (int) $project['project_id']);
  } elseif ($action === 'accept') {
    // PENTING: pastikan tidak ada accepted lain
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE project_id = ? AND status = 'accepted'");
    $stmt->execute([$project_id]);
    if ((int) $stmt->fetchColumn() > 0) {
      $pdo->rollBack();
      flash('alert', 'Sudah ada pelamar yang diterima untuk project ini.');
      redirect('applications/manage.php?project_id=' . (int) $project['project_id']);
    }

    // Set lamaran ini jadi accepted
    $stmt = $pdo->prepare("UPDATE applications SET status = 'accepted' WHERE application_id = ? AND status = 'pending'");
    $stmt->execute([$app_id]);

    // Set project jadi in_progress
    $stmt = $pdo->prepare("UPDATE projects SET status = 'in_progress' WHERE project_id = ? AND status = 'open'");
    $stmt->execute([$project_id]);

    // Reject semua pending lain di project ini
    $stmt = $pdo->prepare("UPDATE applications SET status = 'rejected' WHERE project_id = ? AND status = 'pending' AND application_id != ?");
    $stmt->execute([$project_id, $app_id]);

    $pdo->commit();
    flash('success', 'Lamaran diterima! Project kini berstatus In Progress.');
    redirect('applications/manage.php?project_id=' . (int) $project['project_id']);
  } else {
    $pdo->rollBack();
    flash('alert', 'Aksi tidak valid.');
    redirect('applications/manage.php?project_id=' . (int) $project['project_id']);
  }
} catch (PDOException $ex) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  flash('alert', 'Terjadi kesalahan saat memperbarui status. Silakan coba lagi.');
  redirect('applications/manage.php?project_id=' . (int) $project['project_id']);
}
