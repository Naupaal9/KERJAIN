<?php
// index.php — Landing page: sapaan + daftar project terbaru dari database.

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';   // sudah berisi session_start()

// Ambil 8 project terbaru beserta nama owner, kategori, dan jumlah lamaran pending.
$stmt = $pdo->prepare("
  SELECT p.project_id, p.title, p.description, p.budget, p.deadline, p.status,
         u.full_name  AS owner_name,
         c.category_name,
         COUNT(a.application_id) AS jumlah_pelamar
  FROM projects p
  JOIN users u      ON p.owner_id    = u.user_id
  JOIN categories c ON p.category_id = c.category_id
  LEFT JOIN applications a
         ON a.project_id = p.project_id AND a.status = 'pending'
  GROUP BY p.project_id
  ORDER BY p.created_at DESC
  LIMIT 8
");
$stmt->execute();
$daftar_project = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Kerjain! — Platform Freelance Kampus';
// Landing page: <main> tanpa .container supaya hero & section membentang penuh
// (lihat variabel $main_class di includes/header.php).
$main_class = 'landing';

// Ambil semua kategori beserta jumlah projectnya — dipakai sebagai chips
// navigasi di bawah hero.
$kat_stmt = $pdo->query("
  SELECT c.category_id, c.category_name, COUNT(p.project_id) AS jumlah_project
  FROM categories c
  LEFT JOIN projects p ON p.category_id = c.category_id
  GROUP BY c.category_id, c.category_name
  ORDER BY c.category_name
");
$daftar_kategori = $kat_stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
?>

<!-- Hero: latar gambar langit dari assets/img/background-landingpage.png -->
<section class="hero">
  <div class="container">
    <div class="hero-copy">
      <h1>Cari proyek kampus, asah keahlian, <span class="aksen">bangun portofolio</span>.</h1>
      <p class="hero-desc">
        Kerjain! mempertemukan dosen dan mahasiswa yang butuh bantuan proyek kecil
        dengan mahasiswa yang siap mengerjakannya. Pasang proyek, kirim lamaran,
        kerjakan bersama, selesai.
      </p>
      <div class="btn-row">
        <a class="btn btn-primary" href="projects/index.php">Cari Proyek</a>
        <?php if ($current_user): ?>
          <a class="btn btn-light" href="projects/create.php">Pasang Proyek</a>
        <?php else: ?>
          <a class="btn btn-light" href="auth/register.php">Daftar &amp; Pasang Proyek</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- Section putih: kategori dari database + project terbaru dari database -->
<section class="landing-band landing-band--putih">
  <div class="container">

    <?php if (!empty($daftar_kategori)): ?>
      <div class="section-head">
        <h2 class="section-title">Jelajahi Kategori</h2>
        <p class="section-sub">Bidang yang bisa kamu butuhkan atau yang bisa kamu kerjakan.</p>
      </div>
      <ul class="kategori-list">
        <?php foreach ($daftar_kategori as $kat): ?>
          <li>
            <a class="kategori-chip" href="projects/index.php?kategori=<?= (int) $kat['category_id']; ?>">
              <?= e($kat['category_name']); ?>
              <span class="jumlah"><?= (int) $kat['jumlah_project']; ?> proyek</span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <div class="section-head">
      <h2 class="section-title">Project Terbaru</h2>
      <p class="section-sub">Project yang baru dipasang oleh dosen dan mahasiswa.</p>
    </div>

    <?php if (empty($daftar_project)): ?>
      <p class="kosong">
        Belum ada project sama sekali.<br>
        Yuk <a href="auth/register.php"><strong>daftar</strong></a> dan jadi yang pertama memposting!
      </p>
    <?php else: ?>
      <div class="project-grid">
        <?php foreach ($daftar_project as $project): ?>
          <article class="project-card">
            <a class="card-link" href="projects/show.php?id=<?= (int) $project['project_id']; ?>">
              <span class="card-tab"><?= e($project['category_name']); ?></span>

              <div class="card-body">
                <h3><?= e($project['title']); ?></h3>
                <p class="card-desc"><?= e($project['description']); ?></p>

                <div class="card-meta">
                  <span class="card-budget"><?= e(format_rupiah($project['budget'])); ?></span>
                  <span>
                    Oleh <?= e($project['owner_name']); ?> ·
                    <span class="badge badge-<?= e($project['status']); ?>">
                      <?= e(status_label($project['status'])); ?>
                    </span>
                  </span>
                  <span>
                    <?= (int) $project['jumlah_pelamar']; ?> lamaran pending ·
                    Deadline: <?= $project['deadline'] ? e(format_tanggal($project['deadline'])) : 'bebas'; ?>
                  </span>
                </div>
              </div>
            </a>
          </article>
        <?php endforeach; ?>
      </div>

      <div class="section-cta">
        <a class="btn btn-outline" href="projects/index.php">Lihat Semua Project</a>
      </div>
    <?php endif; ?>

  </div>
</section>

<!-- Section abu terang: penjelasan singkat Kerjain! + alur Post → Apply → Accept → Done -->
<section class="landing-band landing-band--abu">
  <div class="container cara-grid">

    <div class="cara-teks">
      <h2 class="section-title">Kerjain! itu sederhana</h2>
      <p>
        Kerjain! adalah platform micro-freelance kampus untuk TMM PENS: dosen dan
        mahasiswa memasang proyek kecil, mahasiswa lain menawarkan keahliannya,
        dan proyeknya dikerjakan sampai selesai — tanpa ribet.
      </p>
      <div class="landing-cta">
        <?php if ($current_user): ?>
          <a class="btn btn-primary" href="projects/create.php">Pasang Proyek</a>
        <?php else: ?>
          <a class="btn btn-primary" href="auth/register.php">Daftar Sekarang</a>
        <?php endif; ?>
        <a class="btn btn-outline" href="projects/index.php">Cari Proyek</a>
      </div>
    </div>

    <ol class="langkah-list">
      <li class="langkah">
        <span class="langkah-no">01</span>
        <div>
          <h3>Post</h3>
          <p>Owner memasang proyek: judul, deskripsi, kategori, budget, dan deadline.</p>
        </div>
      </li>
      <li class="langkah">
        <span class="langkah-no">02</span>
        <div>
          <h3>Apply</h3>
          <p>Mahasiswa mengirim lamaran berisi cover letter dan usulan fee.</p>
        </div>
      </li>
      <li class="langkah">
        <span class="langkah-no">03</span>
        <div>
          <h3>Accept</h3>
          <p>Owner memilih satu pelamar; lamaran pending lain otomatis ditolak.</p>
        </div>
      </li>
      <li class="langkah">
        <span class="langkah-no">04</span>
        <div>
          <h3>Done</h3>
          <p>Proyek dikerjakan, lalu ditandai selesai oleh owner.</p>
        </div>
      </li>
    </ol>

  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
