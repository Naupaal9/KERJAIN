<?php
// admin/categories.php — CRUD kategori (HANYA admin).
// Semua perubahan data lewat POST + token CSRF. Kategori yang masih dipakai
// project tidak boleh dihapus.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';   // sudah berisi session_start()

require_login();
require_role('admin');   // non-admin langsung diarahkan ke beranda

// ---------------------------------------------------------------------
// Proses aksi POST (create / update / delete)
// ---------------------------------------------------------------------
if (is_post()) {
  if (!csrf_check($_POST['csrf_token'] ?? '')) {
    flash('alert', 'Token keamanan tidak valid. Perubahan dibatalkan.');
    redirect('admin/categories.php');
  }

  $aksi = $_POST['aksi'] ?? '';

  // ---- TAMBAH kategori ----
  if ($aksi === 'create') {
    $nama        = trim($_POST['category_name'] ?? '');
    $deskripsi   = trim($_POST['description'] ?? '');

    if ($nama === '') {
      flash('alert', 'Nama kategori wajib diisi.');
    } elseif (mb_strlen($nama) > 80) {
      flash('alert', 'Nama kategori maksimal 80 karakter.');
    } elseif (mb_strlen($deskripsi) > 255) {
      flash('alert', 'Deskripsi maksimal 255 karakter.');
    } else {
      // Cegah nama duplikat (kolom category_name UNIQUE).
      $cek = $pdo->prepare("SELECT category_id FROM categories WHERE category_name = ? LIMIT 1");
      $cek->execute([$nama]);
      if ($cek->fetch()) {
        flash('alert', 'Kategori "' . $nama . '" sudah ada.');
      } else {
        try {
          $stmt = $pdo->prepare("INSERT INTO categories (category_name, description) VALUES (?, ?)");
          $stmt->execute([$nama, $deskripsi !== '' ? $deskripsi : null]);
          flash('success', 'Kategori "' . $nama . '" berhasil ditambahkan.');
        } catch (PDOException $ex) {
          flash('alert', 'Kategori gagal disimpan. Silakan coba lagi.');
        }
      }
    }
    redirect('admin/categories.php');
  }

  // ---- EDIT kategori ----
  if ($aksi === 'update') {
    $id_raw      = $_POST['category_id'] ?? '';
    $id          = (ctype_digit((string) $id_raw) && (int) $id_raw > 0) ? (int) $id_raw : 0;
    $nama        = trim($_POST['category_name'] ?? '');
    $deskripsi   = trim($_POST['description'] ?? '');

    if ($id === 0) {
      flash('alert', 'Kategori tidak ditemukan.');
    } elseif ($nama === '') {
      flash('alert', 'Nama kategori wajib diisi.');
    } elseif (mb_strlen($nama) > 80) {
      flash('alert', 'Nama kategori maksimal 80 karakter.');
    } elseif (mb_strlen($deskripsi) > 255) {
      flash('alert', 'Deskripsi maksimal 255 karakter.');
    } else {
      // Nama tidak boleh sama dengan kategori LAIN.
      $cek = $pdo->prepare("SELECT category_id FROM categories WHERE category_name = ? AND category_id <> ? LIMIT 1");
      $cek->execute([$nama, $id]);
      if ($cek->fetch()) {
        flash('alert', 'Nama kategori "' . $nama . '" sudah dipakai kategori lain.');
      } else {
        try {
          $stmt = $pdo->prepare("UPDATE categories SET category_name = ?, description = ? WHERE category_id = ?");
          $stmt->execute([$nama, $deskripsi !== '' ? $deskripsi : null, $id]);
          flash('success', 'Kategori berhasil diperbarui.');
        } catch (PDOException $ex) {
          flash('alert', 'Kategori gagal diperbarui. Silakan coba lagi.');
        }
      }
    }
    redirect('admin/categories.php');
  }

  // ---- HAPUS kategori ----
  if ($aksi === 'delete') {
    $id_raw = $_POST['category_id'] ?? '';
    $id     = (ctype_digit((string) $id_raw) && (int) $id_raw > 0) ? (int) $id_raw : 0;

    if ($id === 0) {
      flash('alert', 'Kategori tidak ditemukan.');
      redirect('admin/categories.php');
    }

    // Tolak kalau masih dipakai project (FK juga ON DELETE RESTRICT).
    $cek = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE category_id = ?");
    $cek->execute([$id]);
    $dipakai = (int) $cek->fetchColumn();

    if ($dipakai > 0) {
      flash('alert', 'Kategori tidak bisa dihapus karena masih dipakai oleh ' . $dipakai . ' project.');
    } else {
      try {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = ?");
        $stmt->execute([$id]);
        flash('success', 'Kategori berhasil dihapus.');
      } catch (PDOException $ex) {
        flash('alert', 'Kategori gagal dihapus karena masih terhubung dengan data lain.');
      }
    }
    redirect('admin/categories.php');
  }

  // Aksi tidak dikenal
  flash('alert', 'Aksi tidak dikenal.');
  redirect('admin/categories.php');
}

// ---------------------------------------------------------------------
// Ambil daftar kategori + jumlah project yang memakainya
// ---------------------------------------------------------------------
$categories = $pdo->query("
  SELECT c.category_id, c.category_name, c.description,
         (SELECT COUNT(*) FROM projects p WHERE p.category_id = c.category_id) AS jumlah_project
  FROM categories c
  ORDER BY c.category_name
")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Kelola Kategori — Kerjain!';
$base = '../';
require __DIR__ . '/../includes/header.php';
?>

<header class="page-header">
  <h1>Kelola Kategori</h1>
  <p>Halaman khusus admin. Tambah, ubah, atau hapus kategori project.</p>
</header>

<!-- Form tambah kategori -->
<div class="form-card">
  <h2 class="card-title">Tambah Kategori Baru</h2>
  <form method="POST" action="categories.php" class="form-inline" data-validate>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
    <input type="hidden" name="aksi" value="create">

    <div class="form-group">
      <label class="form-label" for="new_name">Nama Kategori <span class="wajib">*</span></label>
      <input class="form-control" type="text" id="new_name" name="category_name" maxlength="80" required>
    </div>
    <div class="form-group">
      <label class="form-label" for="new_desc">Deskripsi</label>
      <input class="form-control" type="text" id="new_desc" name="description" maxlength="255"
             placeholder="Opsional">
    </div>
    <div class="filter-actions">
      <button class="btn btn-primary" type="submit">Tambah</button>
    </div>
  </form>
</div>

<h2 class="section-title">Daftar Kategori (<?= count($categories); ?>)</h2>

<?php if (empty($categories)): ?>
  <p class="kosong">Belum ada kategori. Tambahkan kategori pertama di atas.</p>
<?php else: ?>
  <div class="cat-list">
    <?php foreach ($categories as $cat): ?>
      <?php $dipakai = (int) $cat['jumlah_project'] > 0; ?>

      <div class="cat-row">
        <!-- Form edit kategori (inline) -->
        <form method="POST" action="categories.php" class="cat-edit-form" data-validate>
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
          <input type="hidden" name="aksi" value="update">
          <input type="hidden" name="category_id" value="<?= (int) $cat['category_id']; ?>">

          <div class="form-group">
            <label class="form-label" for="name_<?= (int) $cat['category_id']; ?>">Nama Kategori</label>
            <input class="form-control" type="text" id="name_<?= (int) $cat['category_id']; ?>"
                   name="category_name" value="<?= e($cat['category_name']); ?>" maxlength="80" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="desc_<?= (int) $cat['category_id']; ?>">Deskripsi</label>
            <input class="form-control" type="text" id="desc_<?= (int) $cat['category_id']; ?>"
                   name="description" value="<?= e($cat['description']); ?>" maxlength="255">
          </div>

          <div class="cat-actions">
            <span class="badge <?= $dipakai ? 'badge-open' : 'badge-closed'; ?>">
              <?= (int) $cat['jumlah_project']; ?> project
            </span>
            <button class="btn btn-sm btn-outline" type="submit">Simpan</button>
          </div>
        </form>

        <!-- Form hapus (harus form terpisah, tidak boleh nested) -->
        <form method="POST" action="categories.php" class="cat-delete-form"
              data-confirm="Hapus kategori '<?= e($cat['category_name']); ?>'?">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
          <input type="hidden" name="aksi" value="delete">
          <input type="hidden" name="category_id" value="<?= (int) $cat['category_id']; ?>">
          <button class="btn btn-sm btn-danger" type="submit" <?= $dipakai ? 'disabled title="Masih dipakai project"' : ''; ?>>
            Hapus
          </button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
