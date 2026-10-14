<?php
// projects/index.php — Daftar project + pencarian judul + filter kategori + pagination.
// Halaman ini boleh dibuka guest (hanya melihat), tanpa aksi yang butuh login.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';   // sudah berisi session_start()

// ---------------------------------------------------------------------
// 1. Baca & rapikan filter dari URL (GET)
// ---------------------------------------------------------------------
$q = trim($_GET['q'] ?? '');

// Filter kategori: hanya terima angka, selain itu dianggap "semua".
$kategori = $_GET['kategori'] ?? '';
$kategori = (ctype_digit((string) $kategori) && (int) $kategori > 0) ? (int) $kategori : 0;

// Nomor halaman: minimal 1.
$page = $_GET['page'] ?? '1';
$page = (ctype_digit((string) $page) && (int) $page > 0) ? (int) $page : 1;

// Jumlah project per halaman.
$per_page = 6;

// ---------------------------------------------------------------------
// 2. Susun kondisi WHERE — semua nilai lewat placeholder (prepared statement)
// ---------------------------------------------------------------------
$where  = [];
$params = [];   // nama placeholder => nilai

if ($q !== '') {
  $where[]        = 'p.title LIKE :q';
  $params[':q']   = '%' . $q . '%';
}
if ($kategori > 0) {
  $where[]             = 'p.category_id = :kategori';
  $params[':kategori'] = $kategori;
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ---------------------------------------------------------------------
// 3. Hitung total data (untuk pagination)
// ---------------------------------------------------------------------
$stmt = $pdo->prepare("SELECT COUNT(*) FROM projects p $where_sql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$total_pages = max(1, (int) ceil($total / $per_page));
if ($page > $total_pages) {
  $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

// ---------------------------------------------------------------------
// 4. Ambil project untuk halaman ini
// ---------------------------------------------------------------------
$sql = "
  SELECT p.project_id, p.title, p.description, p.budget, p.deadline, p.status, p.created_at,
         u.full_name AS owner_name,
         c.category_name,
         (SELECT COUNT(*) FROM applications a WHERE a.project_id = p.project_id) AS jumlah_pelamar
  FROM projects p
  JOIN users u      ON p.owner_id    = u.user_id
  JOIN categories c ON p.category_id = c.category_id
  $where_sql
  ORDER BY p.created_at DESC, p.project_id DESC
  LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $nama => $nilai) {
  $stmt->bindValue($nama, $nilai);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$daftar_project = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------------------------------
// 5. Data pendukung tampilan
// ---------------------------------------------------------------------
$categories = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name")
                  ->fetchAll(PDO::FETCH_ASSOC);

// Query string dasar untuk link pagination (filter tetap terbawa).
$query_dasar = [];
if ($q !== '') {
  $query_dasar['q'] = $q;
}
if ($kategori > 0) {
  $query_dasar['kategori'] = $kategori;
}

$mulai  = $total > 0 ? $offset + 1 : 0;
$sampai = min($offset + $per_page, $total);

$page_title = 'Cari Project — Kerjain!';
$base = '../';
require __DIR__ . '/../includes/header.php';
?>

<header class="page-header">
  <h1>Cari Project</h1>
  <p>Temukan project kampus yang cocok dengan keahlianmu.</p>
</header>

<!-- Form filter: pencarian judul + kategori. Method GET supaya bisa di-bookmark. -->
<form class="filter-bar" method="GET" action="index.php" role="search">
  <div class="filter-group filter-search">
    <label class="form-label" for="q">Cari judul project</label>
    <input class="form-control" type="search" id="q" name="q"
           value="<?= e($q); ?>" placeholder="Contoh: desain logo" maxlength="150">
  </div>

  <div class="filter-group">
    <label class="form-label" for="kategori">Kategori</label>
    <select class="form-control" id="kategori" name="kategori" data-autosubmit>
      <option value="">Semua kategori</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= (int) $cat['category_id']; ?>"
          <?= $kategori === (int) $cat['category_id'] ? 'selected' : ''; ?>>
          <?= e($cat['category_name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="filter-actions">
    <button class="btn btn-primary" type="submit">Terapkan</button>
    <?php if ($q !== '' || $kategori > 0): ?>
      <a class="btn btn-outline" href="index.php">Reset</a>
    <?php endif; ?>
  </div>
</form>

<p class="hasil-info">
  <?php if ($total === 0): ?>
    Tidak ada project yang cocok.
  <?php else: ?>
    Menampilkan <?= $mulai; ?>–<?= $sampai; ?> dari <?= $total; ?> project
    <?= ($q !== '' || $kategori > 0) ? '(hasil filter)' : ''; ?>
  <?php endif; ?>
</p>

<?php if (empty($daftar_project)): ?>
  <p class="kosong">
    Belum ada project yang cocok dengan pencarianmu.<br>
    Coba kata kunci lain atau <a href="index.php"><strong>reset filter</strong></a>.
  </p>
<?php else: ?>
  <div class="project-grid">
    <?php foreach ($daftar_project as $project): ?>
      <article class="project-card">
        <a class="card-link" href="show.php?id=<?= (int) $project['project_id']; ?>">
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
                <?= (int) $project['jumlah_pelamar']; ?> pelamar ·
                Deadline: <?= $project['deadline'] ? e(format_tanggal($project['deadline'])) : 'bebas'; ?>
              </span>
            </div>
          </div>
        </a>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($total_pages > 1): ?>
  <nav class="pagination" aria-label="Navigasi halaman">
    <?php if ($page > 1): ?>
      <?php $link_prev = array_merge($query_dasar, ['page' => $page - 1]); ?>
      <a class="page-link" href="index.php?<?= e(http_build_query($link_prev)); ?>">&laquo; Sebelumnya</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <?php if ($i === $page): ?>
        <span class="page-link is-active"><?= $i; ?></span>
      <?php else: ?>
        <?php $link_page = array_merge($query_dasar, ['page' => $i]); ?>
        <a class="page-link" href="index.php?<?= e(http_build_query($link_page)); ?>"><?= $i; ?></a>
      <?php endif; ?>
    <?php endfor; ?>

    <?php if ($page < $total_pages): ?>
      <?php $link_next = array_merge($query_dasar, ['page' => $page + 1]); ?>
      <a class="page-link" href="index.php?<?= e(http_build_query($link_next)); ?>">Berikutnya &raquo;</a>
    <?php endif; ?>
  </nav>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
