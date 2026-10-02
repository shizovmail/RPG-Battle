<?php
require __DIR__ . '/../inc/boot.php';
$u = require_login('admin');

if (isset($_GET['backup'])) {
    $tmp = tempnam(sys_get_temp_dir(), 'bk');
    @unlink($tmp);
    db()->exec('VACUUM INTO ' . db()->quote($tmp));
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="backup-game-edukasi-' . date('Ymd-His') . '.sqlite"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    set_setting('nama_sekolah', mb_substr(trim($_POST['nama_sekolah'] ?? ''), 0, 100));
    set_setting('tagline', mb_substr(trim($_POST['tagline'] ?? ''), 0, 150));
    $pu = rtrim(trim($_POST['url_publik'] ?? ''), '/');
    if ($pu !== '' && !preg_match('#^https?://#i', $pu)) flash('Alamat publik harus diawali http:// atau https://', 'err');
    else set_setting('url_publik', $pu);

    if (!empty($_POST['hapus_logo'])) {
        $old = setting('logo');
        if ($old && strpos($old, 'uploads/') === 0) @unlink(APP_DIR . '/' . $old);
        set_setting('logo', '');
    }
    $f = $_FILES['logo'] ?? null;
    if ($f && $f['error'] === UPLOAD_ERR_OK) {
        $info = @getimagesize($f['tmp_name']);
        $ext = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
        if (!$ext) flash('Logo harus berupa gambar PNG, JPG, GIF, atau WEBP.', 'err');
        elseif ($f['size'] > 1024 * 1024) flash('Ukuran logo maksimal 1 MB.', 'err');
        else {
            foreach (glob(APP_DIR . '/uploads/logo.*') as $old) @unlink($old);
            move_uploaded_file($f['tmp_name'], APP_DIR . "/uploads/logo.$ext");
            set_setting('logo', "uploads/logo.$ext");
        }
    }
    if (!empty($_POST['regen_semua'])) {
        $n = 0;
        foreach (db_rows('SELECT id FROM games') as $g) { try { generate_game($g['id']); $n++; } catch (Exception $e) {} }
        flash("$n game diperbarui dengan nama dan logo sekolah terbaru.");
    }
    flash('Pengaturan disimpan.');
    redirect('admin/pengaturan.php');
}
page_header('Pengaturan', 'admin/pengaturan.php');
$logo = setting('logo');
?>
<h1>Pengaturan</h1>
<div class="grid2">
<form method="post" enctype="multipart/form-data" class="card">
  <h2>Identitas sekolah</h2>
  <?= csrf_field() ?>
  <label>Nama sekolah<input name="nama_sekolah" value="<?= e(setting('nama_sekolah')) ?>" required></label>
  <label>Slogan<input name="tagline" value="<?= e(setting('tagline')) ?>"></label>
  <label>Alamat publik aplikasi
    <input name="url_publik" value="<?= e(setting('url_publik', DEFAULT_PUBLIC_URL)) ?>">
    <small>Dipakai untuk link dan QR code game. Contoh: https://smp-kartini-dua.my.id/app</small></label>
  <label>Logo sekolah<input type="file" name="logo" accept="image/*"></label>
  <?php if ($logo): ?>
    <div class="logo-prev"><img src="<?= e(url($logo)) ?>" alt="Logo"><label class="check"><input type="checkbox" name="hapus_logo" value="1"> Hapus logo</label></div>
  <?php endif; ?>
  <label class="check"><input type="checkbox" name="regen_semua" value="1"> Perbarui juga semua game yang sudah dibuat (nama & logo sekolah)</label>
  <button class="btn primary">Simpan pengaturan</button>
</form>
<section class="card">
  <h2>Database & cadangan</h2>
  <p class="small">Semua data tersimpan dalam satu file:</p>
  <p><code class="break"><?= e(db_file()) ?></code></p>
  <p class="small muted">Ukuran: <?= number_format(filesize(db_file()) / 1024, 1) ?> KB. Unduh cadangan secara berkala dan simpan di flashdisk atau Google Drive.
    Untuk memulihkan, matikan Apache, ganti file di atas dengan file cadangan (ubah namanya menjadi <?= e(DB_FILE) ?>), lalu nyalakan lagi.</p>
  <a class="btn" href="?backup=1">Unduh cadangan database</a>
  <p class="small muted">File game murid ada di <code>htdocs/app/games/</code>. Jika folder ini hilang, semua game bisa dibuat ulang lewat Template game → "Terapkan ke semua game".</p>
</section>
</div>
<?php page_footer();
