<?php
// includes/functions.php — Kumpulan helper functions untuk seluruh aplikasi.

// e() — Escape output user/database sebelum ditampilkan (cegah XSS).
function e($string) {
  return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
}

// csrf_token() — Ambil (atau buat baru) token CSRF di session.
function csrf_token() {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

// csrf_check() — Cocokkan token form dengan token session (aman timing attack).
function csrf_check($token) {
  if (!isset($_SESSION['csrf_token']) || !is_string($token)) {
    return false;
  }
  return hash_equals($_SESSION['csrf_token'], $token);
}

// flash() — Pesan sementara setelah redirect (tampil sekali lalu hilang).
//   Set : flash('success', 'Registrasi berhasil!');
//   Get : flash('success')  → mengambil lalu menghapus dari session.
function flash($key, $value = null) {
  if ($value !== null) {
    $_SESSION['flash'][$key] = $value;
    return '';
  }
  if (isset($_SESSION['flash'][$key])) {
    $tmp = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $tmp;
  }
  return '';
}

// app_root() — Path root aplikasi dari sisi web, contoh: '/Kerjain'.
// Dihitung dari posisi folder includes relatif document root, supaya redirect()
// tetap benar walau dipanggil dari halaman di subfolder (auth/, projects/, dst).
function app_root() {
  static $root = null;
  if ($root !== null) {
    return $root;
  }

  $doc_root    = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
  $project_dir = realpath(dirname(__DIR__));   // folder induk dari includes/
  if ($doc_root !== false && $project_dir !== false) {
    $doc_root    = str_replace('\\', '/', $doc_root);
    $project_dir = str_replace('\\', '/', $project_dir);
    // Pastikan folder project memang berada di dalam document root.
    if ($doc_root === $project_dir || strpos($project_dir, $doc_root . '/') === 0) {
      $root = rtrim(substr($project_dir, strlen($doc_root)), '/');
      return $root;
    }
  }

  // Cadangan: cara lama (benar untuk halaman yang ada di root aplikasi).
  $root = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
  return $root;
}

// is_post() — true kalau request memakai method POST (dipakai untuk aksi ubah data).
function is_post() {
  return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// role_label() — Nama role yang enak dibaca untuk ditampilkan di UI.
function role_label($role) {
  $label = [
    'mahasiswa' => 'Mahasiswa',
    'dosen'     => 'Dosen',
    'admin'     => 'Admin',
  ];
  return $label[$role] ?? ucfirst((string) $role);
}

// status_label() — Nama status project dalam bahasa Indonesia untuk ditampilkan.
//   status_label('in_progress') → 'In Progress'
function status_label($status) {
  $label = [
    'open'        => 'Open',
    'in_progress' => 'In Progress',
    'done'        => 'Selesai',
    'closed'      => 'Ditutup',
  ];
  return $label[$status] ?? ucfirst((string) $status);
}

// redirect() — Pindah halaman lalu berhenti. Path relatif otomatis
// ditempelkan root aplikasi supaya tetap benar dari halaman subfolder.
function redirect($path) {
  if ($path === '' || $path[0] !== '/') {
    $path = app_root() . '/' . ltrim($path, '/');
  }
  header('Location: ' . $path, true, 302);
  exit;
}

// format_rupiah() — Format angka jadi mata uang Rupiah.
//   format_rupiah(1500000) → 'Rp 1.500.000'
function format_rupiah($amount) {
  return 'Rp ' . number_format((int) $amount, 0, ',', '.');
}

// format_tanggal() — Format tanggal (Y-m-d) jadi tanggal Indonesia singkat.
//   format_tanggal('2026-11-30') → '30 Nov 2026'
function format_tanggal($tanggal) {
  if ($tanggal === null || $tanggal === '') {
    return '';
  }
  $ts = strtotime((string) $tanggal);
  if ($ts === false) {
    return (string) $tanggal;
  }
  $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
            'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  return date('d', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}
