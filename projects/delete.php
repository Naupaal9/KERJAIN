<?php
// projects/delete.php — Hapus project.
// Aturan: hanya lewat POST + CSRF. Pemilik boleh hapus projectnya; admin boleh
// menghapus project siapa pun. Request GET langsung ditolak.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';   // sudah berisi session_start()

require_login();

// 1. Tolak aksi lewat GET / URL langsung.
if (!is_post()) {
  flash('alert', 'Hapus project harus lewat tombol di halaman project.');
  redirect('projects/my.php');
}

// 2. Cek token CSRF.
if (!csrf_check($_POST['csrf_token'] ?? '')) {
  flash('alert', 'Token keamanan tidak valid. Project tidak dihapus.');
  redirect('projects/my.php');
}

// 3. Validasi ID project.
$id_raw = $_POST['project_id'] ?? '';
$id     = (ctype_digit((string) $id_raw) && (int) $id_raw > 0) ? (int) $id_raw : 0;

if ($id === 0) {
  flash('alert', 'Project tidak ditemukan.');
  redirect('projects/my.php');
}

$stmt = $pdo->prepare("SELECT project_id, owner_id, title FROM projects WHERE project_id = ? LIMIT 1");
$stmt->execute([$id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
  flash('alert', 'Project tidak ditemukan atau sudah dihapus.');
  redirect('projects/my.php');
}

// 4. Cek hak akses: pemilik ATAU admin.
$user     = current_user($pdo);
$is_owner = $user && ((int) $user['user_id'] === (int) $project['owner_id']);
$is_admin = $user && ($user['role'] === 'admin');

if (!$is_owner && !$is_admin) {
  flash('alert', 'Kamu tidak berhak menghapus project ini.');
  redirect('projects/show.php?id=' . (int) $project['project_id']);
}

// 5. Hapus project (lamaran terkait ikut terhapus lewat ON DELETE CASCADE).
try {
  $del = $pdo->prepare("DELETE FROM projects WHERE project_id = ?");
  $del->execute([(int) $project['project_id']]);
  flash('success', 'Project "' . $project['title'] . '" berhasil dihapus.');
} catch (PDOException $ex) {
  flash('alert', 'Project gagal dihapus. Silakan coba lagi.');
}

// Admin yang menghapus project orang lain kembali ke daftar umum.
redirect($is_owner ? 'projects/my.php' : 'projects/index.php');
