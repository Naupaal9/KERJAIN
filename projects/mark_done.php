<?php
// projects/mark_done.php — Pemilik menandai project in_progress sebagai done (POST + CSRF).

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php'; // sudah berisi session_start()

require_login();

if (!is_post()) {
  flash('alert', 'Tindakan ini harus lewat tombol yang tersedia.');
  redirect('projects/my.php');
}

if (!csrf_check($_POST['csrf_token'] ?? '')) {
  flash('alert', 'Token keamanan tidak valid.');
  redirect('projects/my.php');
}

$pid_raw = $_POST['project_id'] ?? '';
$project_id = (ctype_digit((string) $pid_raw) && (int) $pid_raw > 0) ? (int) $pid_raw : 0;

if ($project_id === 0) {
  flash('alert', 'Project tidak ditemukan.');
  redirect('projects/my.php');
}

$user = current_user($pdo);
if (!$user) {
  redirect('projects/my.php');
}

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
  flash('alert', 'Kamu hanya boleh menandai project milikmu sebagai selesai.');
  redirect('projects/show.php?id=' . (int) $project['project_id']);
}

if ($project['status'] !== 'in_progress') {
  flash('alert', 'Hanya project berstatus In Progress yang bisa ditandai selesai.');
  redirect('projects/show.php?id=' . (int) $project['project_id']);
}

try {
  $stmt = $pdo->prepare("UPDATE projects SET status = 'done' WHERE project_id = ? AND owner_id = ? AND status = 'in_progress'");
  $stmt->execute([$project_id, (int) $user['user_id']]);
  if ($stmt->rowCount() > 0) {
    flash('success', 'Project berhasil ditandai sebagai selesai (Done).');
  } else {
    flash('alert', 'Gagal mengubah status project.');
  }
} catch (PDOException $ex) {
  flash('alert', 'Terjadi kesalahan. Silakan coba lagi.');
}

redirect('projects/show.php?id=' . (int) $project['project_id']);
