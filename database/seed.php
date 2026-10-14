<?php
// database/seed.php — Data demo untuk pengujian Kerjain!
// Aman dijalankan berulang: berhenti jika data user demo sudah ada.
// Kategori awal sudah disediakan oleh schema.sql (jangan diduplikasi).

require_once __DIR__ . '/../config/db.php';

// Cek apakah seed sudah pernah jalan (lihat user demo admin@kerjain.test)
$check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
$check->execute(['admin@kerjain.test']);
if ($check->fetchColumn() > 0) {
  echo "Seed sudah pernah dijalankan. Tidak ada data yang ditambahkan.\n";
  echo "Jalankan ulang hanya jika tabel sudah dikosongkan.\n";
  exit(0);
}

$passwordHash = password_hash('password123', PASSWORD_DEFAULT);
$users = []; // simpan user_id hasil insert

// =====================================================================
// 1. USER DEMO — 1 admin, 2 dosen, 4 mahasiswa
// =====================================================================
$pdo->beginTransaction();

// Admin
$stmt = $pdo->prepare(
  "INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'admin')"
);
$stmt->execute(['Admin Kerjain', 'admin@kerjain.test', $passwordHash]);
$users['admin'] = (int) $pdo->lastInsertId();

// Dosen
$stmt = $pdo->prepare(
  "INSERT INTO users (full_name, email, password, role, nim_nip, prodi) VALUES (?, ?, ?, 'dosen', ?, ?)"
);
$stmt->execute(['Dr. Budi Santoso, S.Kom., M.T.', 'dosen1@kerjain.test', $passwordHash,
  '197801012005011001', 'Teknik Informatika']);
$users['dosen1'] = (int) $pdo->lastInsertId();

$stmt->execute(['Dr. Sari Wulandari, S.Ds., M.Ds.', 'dosen2@kerjain.test', $passwordHash,
  '198203022008012002', 'Desain Komunikasi Visual']);
$users['dosen2'] = (int) $pdo->lastInsertId();

// Mahasiswa
$stmt = $pdo->prepare(
  "INSERT INTO users (full_name, email, password, role, nim_nip, prodi) VALUES (?, ?, ?, 'mahasiswa', ?, ?)"
);
$stmt->execute(['Andi Wijaya', 'mahasiswa1@kerjain.test', $passwordHash,
  '2110561023', 'Teknik Informatika']);
$users['mhs1'] = (int) $pdo->lastInsertId();

$stmt->execute(['Rina Amelia Putri', 'mahasiswa2@kerjain.test', $passwordHash,
  '2110561044', 'Desain Komunikasi Visual']);
$users['mhs2'] = (int) $pdo->lastInsertId();

$stmt->execute(['Dimas Prayoga', 'mahasiswa3@kerjain.test', $passwordHash,
  '2110561055', 'Sistem Informasi Bisnis']);
$users['mhs3'] = (int) $pdo->lastInsertId();

$stmt->execute(['Nadia Safitri', 'mahasiswa4@kerjain.test', $passwordHash,
  '2110561066', 'Teknologi Informasi']);
$users['mhs4'] = (int) $pdo->lastInsertId();

echo "7 user demo dibuat (1 admin, 2 dosen, 4 mahasiswa).\n";

// =====================================================================
// 2. PROJECT CONTOH — 8 project, owner sudah ditentukan
// =====================================================================
$catStmt = $pdo->query("SELECT category_id, category_name FROM categories");
$categoryMap = [];
foreach ($catStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
  $categoryMap[$c['category_name']] = (int) $c['category_id'];
}

$projStmt = $pdo->prepare(
  "INSERT INTO projects (owner_id, category_id, title, description, budget, deadline, status)
   VALUES (?, ?, ?, ?, ?, ?, ?)"
);

// [owner_key, category_name, title, description, budget, deadline, status]
$projectsData = [
  ['dosen1', 'Desain Grafis', 'Desain Logo HUT Teknik',
   'Desain logo untuk acara ulang tahun jurusan Teknik Informatika.',
   750000, '2026-11-30', 'open'],

  ['dosen2', 'UI/UX', 'Prototype Aplikasi E-Learning',
   'Wireframe dan prototype Figma untuk aplikasi e-learning jurusan.',
   1500000, '2026-12-15', 'open'],

  ['mhs1', 'Web Development', 'Website Profil UKM Robotik',
   'Website sederhana profil UKM: berita, jadwal, dan galeri.',
   1200000, '2026-11-20', 'open'],

  ['dosen1', 'Penulisan', 'Artikel Jurnal Pendidikan',
   'Bantu penulisan dan edit bahasa artikel jurnal pendidikan.',
   600000, '2026-10-30', 'open'],

  ['mhs2', 'Video & Animasi', 'Video Promosi Open Recruitment',
   'Edit video promosi open recruitment organisasi mahasiswa.',
   1000000, '2026-11-10', 'open'],

  ['mhs3', 'Sosial Media', 'Kelola Konten Instagram Acara',
   'Buat 12 konten feed + 20 story untuk promosi acara kampus.',
   800000, '2026-12-01', 'open'],

  ['dosen2', 'Desain Grafis', 'Poster Seminar Nasional',
   'Desain poster dan feed promosi seminar nasional.',
   500000, '2026-10-25', 'open'],

  ['mhs4', 'Penelitian & Data', 'Olah Data Survei Kepuasan',
   'Bantu input, olah, dan visualisasi data survei kepuasan mahasiswa.',
   700000, '2026-11-05', 'open'],
];

$projectIds = [];
foreach ($projectsData as $p) {
  list($ownerKey, $catName, $title, $desc, $budget, $deadline, $status) = $p;
  $catId = $categoryMap[$catName] ?? null;
  if ($catId === null) {
    echo "PERINGATAN: kategori '$catName' tidak ditemukan, project '$title' dilewati.\n";
    continue;
  }
  $projStmt->execute([
    $users[$ownerKey], $catId, $title, $desc, $budget, $deadline, $status,
  ]);
  $projectIds[$title] = (int) $pdo->lastInsertId();
  echo "Project dibuat: $title (owner=$ownerKey, kategori=$catName).\n";
}

// =====================================================================
// 3. LAMARAN CONTOH — status campuran: pending / accepted / rejected
// =====================================================================
$appStmt = $pdo->prepare(
  "INSERT INTO applications (project_id, applicant_id, cover_letter, proposed_fee, status)
   VALUES (?, ?, ?, ?, ?)"
);

// [project_title, applicant_key, cover_letter, proposed_fee, status]
$applicationsData = [
  ['Desain Logo HUT Teknik', 'mhs2',
   'Halo Pak, saya biasa bikin logo vektor. Portofolio saya bisa saya kirim. Siap revisi sampai cocok.',
   700000, 'pending'],

  ['Website Profil UKM Robotik', 'mhs3',
   'Saya pernah buat website profil UKM pakai HTML/CSS/PHP. Bisa selesai sesuai deadline.',
   1100000, 'accepted'],  // satu-satunya accepted → project jadi in_progress

  ['Poster Seminar Nasional', 'mhs1',
   'Saya bisa desain poster dengan tools desain. Contoh karya saya ada di portofolio.',
   450000, 'rejected'],

  ['Kelola Konten Instagram Acara', 'mhs4',
   'Saya punya pengalaman manage IG UKM selama satu semester. Siap produksi konten rutin.',
   750000, 'pending'],
];

$acceptedProjects = [];
foreach ($applicationsData as $a) {
  list($projTitle, $applicantKey, $letter, $fee, $status) = $a;
  if (!isset($projectIds[$projTitle])) {
    echo "PERINGATAN: project '$projTitle' tidak ada, lamaran dilewati.\n";
    continue;
  }
  $appStmt->execute([$projectIds[$projTitle], $users[$applicantKey], $letter, $fee, $status]);
  echo "Lamaran dibuat: $applicantKey → '$projTitle' ($status).\n";
  if ($status === 'accepted') {
    $acceptedProjects[] = $projectIds[$projTitle];
  }
}

// =====================================================================
// 4. Project dengan lamaran accepted → status in_progress
// =====================================================================
$upd = $pdo->prepare("UPDATE projects SET status = 'in_progress' WHERE project_id = ?");
foreach ($acceptedProjects as $pid) {
  $upd->execute([$pid]);
  echo "Project #$pid → in_progress.\n";
}

$pdo->commit();

// =====================================================================
echo "\nSeed selesai!\n";
echo "Login (password semua akun: password123):\n";
echo "  Admin     : admin@kerjain.test\n";
echo "  Dosen 1   : dosen1@kerjain.test\n";
echo "  Dosen 2   : dosen2@kerjain.test\n";
echo "  Mahasiswa 1: mahasiswa1@kerjain.test\n";
echo "  Mahasiswa 2: mahasiswa2@kerjain.test\n";
echo "  Mahasiswa 3: mahasiswa3@kerjain.test\n";
echo "  Mahasiswa 4: mahasiswa4@kerjain.test\n";
