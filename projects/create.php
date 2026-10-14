<?php
// projects/create.php — Buat project baru (mahasiswa & dosen, admin juga boleh).
// Status project selalu 'open' saat dibuat. Perubahan ke in_progress/done
// hanya lewat alur lamaran (Modul C), bukan dari form ini.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';   // sudah berisi session_start()

require_login();
require_role(['mahasiswa', 'dosen', 'admin']);   // guest tidak boleh

// Daftar kategori untuk dropdown.
$categories = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name")
                  ->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$input  = [
  'title'       => '',
  'description' => '',
  'category_id' => '',
  'budget'      => '',
  'deadline'    => '',
];

// ---------------------------------------------------------------------
// Proses form (POST)
// ---------------------------------------------------------------------
if (is_post()) {
  if (!csrf_check($_POST['csrf_token'] ?? '')) {
    $errors['umum'] = 'Token keamanan tidak valid. Silakan coba lagi.';
  } else {
    $input['title']       = trim($_POST['title'] ?? '');
    $input['description'] = trim($_POST['description'] ?? '');
    $input['category_id'] = trim($_POST['category_id'] ?? '');
    $input['budget']      = trim($_POST['budget'] ?? '');
    $input['deadline']    = trim($_POST['deadline'] ?? '');

    // 1. Judul
    if ($input['title'] === '') {
      $errors['title'] = 'Judul project wajib diisi.';
    } elseif (mb_strlen($input['title']) > 150) {
      $errors['title'] = 'Judul project maksimal 150 karakter.';
    }

    // 2. Deskripsi
    if ($input['description'] === '') {
      $errors['description'] = 'Deskripsi project wajib diisi.';
    } elseif (mb_strlen($input['description']) > 5000) {
      $errors['description'] = 'Deskripsi maksimal 5000 karakter.';
    }

    // 3. Kategori harus benar-benar ada di database.
    if (!ctype_digit((string) $input['category_id']) || (int) $input['category_id'] < 1) {
      $errors['category_id'] = 'Pilih kategori yang tersedia.';
    } else {
      $cek = $pdo->prepare("SELECT category_id FROM categories WHERE category_id = ? LIMIT 1");
      $cek->execute([(int) $input['category_id']]);
      if (!$cek->fetch()) {
        $errors['category_id'] = 'Kategori tidak ditemukan di database.';
      }
    }

    // 4. Budget: wajib, angka bulat, tidak negatif.
    if ($input['budget'] === '') {
      $errors['budget'] = 'Budget wajib diisi.';
    } elseif (!ctype_digit($input['budget'])) {
      $errors['budget'] = 'Budget harus angka bulat tanpa titik/koma dan tidak boleh negatif.';
    } elseif (strlen($input['budget']) > 12) {
      $errors['budget'] = 'Budget terlalu besar (maksimal 12 angka).';
    }

    // 5. Deadline (opsional) harus format YYYY-MM-DD.
    if ($input['deadline'] !== '') {
      $tanggal = DateTime::createFromFormat('Y-m-d', $input['deadline']);
      if (!$tanggal || $tanggal->format('Y-m-d') !== $input['deadline']) {
        $errors['deadline'] = 'Format deadline tidak valid.';
      }
    }

    // 6. Simpan kalau tidak ada error.
    if (empty($errors)) {
      try {
        $stmt = $pdo->prepare("
          INSERT INTO projects (owner_id, category_id, title, description, budget, deadline, status)
          VALUES (?, ?, ?, ?, ?, ?, 'open')
        ");
        $stmt->execute([
          (int) $_SESSION['user_id'],
          (int) $input['category_id'],
          $input['title'],
          $input['description'],
          (int) $input['budget'],
          $input['deadline'] !== '' ? $input['deadline'] : null,
        ]);
        $project_id = (int) $pdo->lastInsertId();

        flash('success', 'Project berhasil dibuat.');
        redirect('projects/show.php?id=' . $project_id);
      } catch (PDOException $ex) {
        $errors['umum'] = 'Project gagal disimpan. Silakan coba lagi.';
      }
    }
  }
}

$page_title = 'Buat Project — Kerjain!';
$base = '../';
require __DIR__ . '/../includes/header.php';
?>

<header class="page-header">
  <h1>Buat Project</h1>
  <p>Ceritakan project kecilmu, lalu tunggu mahasiswa melamar.</p>
</header>

<div class="form-wrap">
  <div class="form-card">
    <?php if (!empty($errors['umum'])): ?>
      <p class="alert alert-error"><?= e($errors['umum']); ?></p>
    <?php endif; ?>

    <form method="POST" action="create.php" data-validate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

      <div class="form-group">
        <label class="form-label" for="title">Judul Project <span class="wajib">*</span></label>
        <input class="form-control" type="text" id="title" name="title"
               value="<?= e($input['title']); ?>" maxlength="150" required>
        <?php if (isset($errors['title'])): ?>
          <span class="form-error"><?= e($errors['title']); ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="description">Deskripsi <span class="wajib">*</span></label>
        <textarea class="form-control" id="description" name="description" maxlength="5000"
                  placeholder="Jelaskan pekerjaan, hasil yang diharapkan, dan ketentuannya..." required><?= e($input['description']); ?></textarea>
        <span class="form-hint">Maksimal 5000 karakter.</span>
        <?php if (isset($errors['description'])): ?>
          <span class="form-error"><?= e($errors['description']); ?></span>
        <?php endif; ?>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="category_id">Kategori <span class="wajib">*</span></label>
          <select class="form-control" id="category_id" name="category_id" required>
            <option value="">— Pilih kategori —</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= (int) $cat['category_id']; ?>"
                <?= (string) $input['category_id'] === (string) $cat['category_id'] ? 'selected' : ''; ?>>
                <?= e($cat['category_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['category_id'])): ?>
            <span class="form-error"><?= e($errors['category_id']); ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="budget">Budget (Rp) <span class="wajib">*</span></label>
          <input class="form-control" type="number" id="budget" name="budget"
                 value="<?= e($input['budget']); ?>" min="0" step="1000" placeholder="750000" required>
          <span class="form-hint">Angka saja, contoh: 750000.</span>
          <?php if (isset($errors['budget'])): ?>
            <span class="form-error"><?= e($errors['budget']); ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="deadline">Deadline</label>
        <input class="form-control" type="date" id="deadline" name="deadline"
               value="<?= e($input['deadline']); ?>">
        <span class="form-hint">Opsional. Kosongkan kalau belum ada tenggat.</span>
        <?php if (isset($errors['deadline'])): ?>
          <span class="form-error"><?= e($errors['deadline']); ?></span>
        <?php endif; ?>
      </div>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Simpan Project</button>
        <a class="btn btn-outline" href="my.php">Batal</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
