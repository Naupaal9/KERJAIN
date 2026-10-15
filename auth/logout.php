<?php
// auth/logout.php — Keluar dari aplikasi.
// Menghapus session = aksi ubah data, jadi wajib lewat POST + token CSRF.
// Tidak ada tampilan di halaman ini: proses lalu langsung redirect.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';   // sudah berisi session_start()

// Tolak akses lewat URL/GET langsung atau token CSRF yang tidak cocok.
if (!is_post() || !csrf_check($_POST['csrf_token'] ?? '')) {
  flash('alert', 'Permintaan keluar tidak valid. Gunakan tombol "Keluar" di navbar.');
  redirect('index.php');
}

// Hapus semua data session (termasuk user_id dan role).
$_SESSION = [];

// Ganti ID session: ID lama jadi tidak bisa dipakai lagi (cegah session fixation),
// sekaligus cookie baru otomatis dikirim ke browser.
session_regenerate_id(true);

// Pesan sukses ditulis ke session baru supaya bisa tampil di halaman tujuan.
flash('success', 'Kamu sudah keluar. Sampai jumpa!');
redirect('index.php');
