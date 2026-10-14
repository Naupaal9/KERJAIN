<?php
// projects/edit.php — Edit project MILIK SENDIRI, plus aksi tutup/buka kembali.
// Pemeriksaan kepemilikan dilakukan di server (bukan hanya menyembunyikan tombol).
// Status project tidak bisa diubah bebas dari sini (hanya open <-> closed).

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';   // sudah berisi session_start()

require_login();

// ID bisa datang dari URL (?id=) atau dari form POST (hidden project_id).
$id_raw = $_GET['id'] ?? ($_POST['project_id'] ?? '');
$id     = (ctype_digit((string) $id_raw) && (int) $id_raw > 0) ? (int) $id_raw : 0;

// Ambil project tanpa syarat owner dulu, supaya bisa membedakan
// "tidak ada" dan "bukan milikmu".
$project = null;
if ($id > 0) {
  $stmt = $pdo->prepare("SELECT * FROM projects WHERE project_id = ? LIMIT 1");
  $stmt->execute([$id]);
  $project = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ---------------------------------------------------------------------
// 1. Project tidak ditemukan
// ---------------------------------------------------------------------
if (!$project) {
  flash('alert', 'Project tidak ditemukan.');
  redirect('projects/my.php');
}

// ---------------------------------------------------------------------
// 2. Cek kepemilikan (hanya pemilik yang boleh mengedit/menutup)
// ---------------------------------------------------------------------
$user = current_user($pdo);
if (!$user || (int) $user['user_id'] !== (int) $project['owner_id']) {
  flash('alert', 'Kamu hanya boleh mengedit project milikmu sendiri.');
  redirect('projects/show.php?id=' . (int) $project['project_id']);
}

$categories = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name")
                  ->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
// Nilai awal form diambil dari data project, lalu ditimpa input POST saat error.
$input = [
  'title'       => $project['title'],
  'description' => $project['description'],
  'category_id' => (string) $project['category_id'],
  'budget'      => (string) $project['budget'],
  'deadline'    => (string) ($project['deadline'] ?? ''),
];

// ---------------------------------------------------------------------
// 3. Proses form (POST)
// ---------------------------------------------------------------------
if (is_post()) {
  if (!csrf_check($_POST['csrf_token'] ?? '')) {
    $errors['umum'] = 'Token keamanan tidak valid. Silakan coba lagi.';
  } else {
    $aksi = $_POST['aksi'] ?? 'update';

    // --- Aksi tutup / buka kembali project (tanpa mengubah data lain) ---
    if ($aksi === 'close' || $aksi === 'reopen') {
      if ($aksi === 'close') {
        if ($project['status'] !== 'open') {
          flash('alert', 'Hanya project berstatus open yang bisa ditutup.');
        } else {
          $stmt = $pdo->prepare("UPDATE projects SET status = 'closed' WHERE project_id = ?");
          $stmt->execute([(int) $project['project_id']]);
          flash('success', 'Project berhasil ditutup.');
        }
      } else { // reopen
        if ($project['status'] !== 'closed') {
          flash('alert', 'Hanya project yang sudah ditutup yang bisa dibuka kembali.');
        } else {
          $stmt = $pdo->prepare("UPDATE projects SET status = 'open' WHERE project_id = ?");
          $stmt->execute([(int) $project['project_id']]);
          flash('success', 'Project dibuka kembali dan siap menerima lamaran.');
        }
      }
      redirect('projects/show.php?id=' . (int) $project['project_id']);
    }

    // --- Aksi update data project ---
    $input['title']       = trim($_POST['title'] ?? '');
    $input['description'] = trim($_POST['description'] ?? '');
    $input['category_id'] = trim($_POST['category_id'] ?? '');
    $input['budget']      = trim($_POST['budget'] ?? '');
    $input['deadline']    = trim($_POST['deadline'] ?? '');

    if ($input['title'] === '') {
      $errors['title'] = 'Judul project wajib diisi.';
    } elseif (mb_strlen($input['title']) > 150) {
      $errors['title'] = 'Judul project maksimal 150 karakter.';
    }

    if ($input['description'] === '') {
      $errors['description'] = 'Deskripsi project wajib diisi.';
    } elseif (mb_strlen($input['description']) > 5000) {
      $errors['description'] = 'Deskripsi maksimal 5000 karakter.';
    }

    if (!ctype_digit((string) $input['category_id']) || (int) $input['category_id'] < 1) {
      $errors['category_id'] = 'Pilih kategori yang tersedia.';
    } else {
      $cek = $pdo->prepare("SELECT category_id FROM categories WHERE category_id = ? LIMIT 1");
      $cek->execute([(int) $input['category_id']]);
      if (!$cek->fetch()) {
        $errors['category_id'] = 'Kategori tidak ditemukan di database.';
      }
    }

    if ($input['budget'] === '') {
      $errors['budget'] = 'Budget wajib diisi.';
    } elseif (!ctype_digit($input['budget'])) {
      $errors['budget'] = 'Budget harus angka bulat tanpa titik/koma dan tidak boleh negatif.';
    } elseif (strlen($input['budget']) > 12) {
      $errors['budget'] = 'Budget terlalu besar (maksimal 12 angka).';
    }

    if ($input['deadline'] !== '') {
      $tanggal = DateTime::createFromFormat('Y-m-d', $input['deadline']);
      if (!$tanggal || $tanggal->format('Y-m-d') !== $input['deadline']) {
        $errors['deadline'] = 'Format deadline tidak valid.';
      }
    }

    if (empty($errors)) {
      try {
        $stmt = $pdo->prepare("
          UPDATE projects
          SET category_id = ?, title = ?, description = ?, budget = ?, deadline = ?
          WHERE project_id = ?
        ");
        $stmt->execute([
          (int) $input['category_id'],
          $input['title'],
          $input['description'],
          (int) $input['budget'],
          $input['deadline'] !== '' ? $input['deadline'] : null,
          (int) $project['project_id'],
        ]);

        flash('success', 'Project berhasil diperbarui.');
        redirect('projects/show.php?id=' . (int) $project['project_id']);
      } catch (PDOException $ex) {
        $errors['umum'] = 'Project gagal diperbarui. Silakan coba lagi.';
      }
    }
  }
}

$page_title = 'Edit Project — Kerjain!';
$base = '../';
require __DIR__ . '/../includes/header.php';
?>

<header class="page-header">
  <p class="breadcrumb">
    <a href="my.php">Project Saya</a> /
    <a href="show.php?id=<?= (int) $project['project_id']; ?>">Detail</a> /
    Edit
  </p>
  <h1>Edit Project</h1>
  <p>
    Status saat ini:
    <span class="badge badge-<?= e($project['status']); ?>"><?= e(status_label($project['status'])); ?></span>
  </p>
</header>

<div class="form-wrap">
  <div class="form-card">
    <?php if (!empty($errors['umum'])): ?>
      <p class="alert alert-error"><?= e($errors['umum']); ?></p>
    <?php endif; ?>

    <form method="POST" action="edit.php?id=<?= (int) $project['project_id']; ?>" data-validate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
      <input type="hidden" name="aksi" value="update">

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
        <textarea class="form-control" id="description" name="description" maxlength="5000" required><?= e($input['description']); ?></textarea>
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
                 value="<?= e($input['budget']); ?>" min="0" step="1000" required>
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
        <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
        <a class="btn btn-outline" href="show.php?id=<?= (int) $project['project_id']; ?>">Batal</a>
      </div>
    </form>

    <hr class="divider">

    <!-- Panel tutup/buka project, terpisah dari form edit data. -->
    <h2 class="card-title">Status Project</h2>
    <?php if ($project['status'] === 'open'): ?>
      <p class="form-hint">Project kamu masih open dan menerima lamaran.</p>
      <form method="POST" action="edit.php?id=<?= (int) $project['project_id']; ?>"
            data-confirm="Tutup project ini? Project tidak akan menerima lamaran baru.">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <input type="hidden" name="aksi" value="close">
        <button class="btn btn-outline" type="submit">Tutup Project</button>
      </form>
    <?php elseif ($project['status'] === 'closed'): ?>
      <p class="form-hint">Project ini sudah ditutup dan tidak menerima lamaran.</p>
      <form method="POST" action="edit.php?id=<?= (int) $project['project_id']; ?>"
            data-confirm="Buka kembali project ini supaya bisa dilamar lagi?">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <input type="hidden" name="aksi" value="reopen">
        <button class="btn btn-outline" type="submit">Buka Kembali</button>
      </form>
    <?php elseif ($project['status'] === 'in_progress'): ?>
      <p class="form-hint">
        Project sedang berjalan (In Progress). Kamu bisa menandainya selesai dari halaman Detail Project atau Kelola Pelamar.
      </p>
      <form method="POST" action="mark_done.php"
            data-confirm="Tandai project ini sebagai selesai (Done)?">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <input type="hidden" name="project_id" value="<?= (int) $project['project_id']; ?>">
        <button class="btn btn-primary" type="submit">Tandai Selesai</button>
      </form>
    <?php else: // done ?>
      <p class="form-hint">Project ini sudah selesai.</p>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
