<?php
require __DIR__ . '/../inc/boot.php';
$u = require_login('admin');

function pasang_zip($tmp, &$pesan)
{
    if (!class_exists('ZipArchive')) { $pesan = 'Ekstensi PHP zip belum aktif (extension=zip di php.ini).'; return false; }
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) { $pesan = 'File zip tidak bisa dibuka.'; return false; }
    $prefix = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $n = str_replace('\\', '/', $zip->getNameIndex($i));
        if (strpos($n, '..') !== false || $n[0] === '/') { $pesan = 'Zip berisi jalur file tidak aman.'; return false; }
        if (preg_match('/\.(php\d?|phtml|phar|htaccess)$/i', $n)) { $pesan = 'Zip tidak boleh berisi file PHP/.htaccess.'; return false; }
        if (preg_match('#^(.*?)manifest\.json$#', $n, $mm) && substr_count($mm[1], '/') <= 1) $prefix = $mm[1];
    }
    if ($prefix === null) { $pesan = 'manifest.json tidak ditemukan di dalam zip.'; return false; }
    $m = json_decode((string)$zip->getFromName($prefix . 'manifest.json'), true);
    $kode = $m['kode'] ?? '';
    if (!is_array($m) || !preg_match('/^[a-z0-9_-]{2,30}$/', $kode)) { $pesan = 'manifest.json harus berisi "kode" (huruf kecil/angka/-/_).'; return false; }
    $html = $zip->getFromName($prefix . 'template.html');
    if ($html === false || strpos($html, '/*__GAME_DATA__*/') === false) { $pesan = 'template.html tidak ada atau tanpa penanda /*__GAME_DATA__*/.'; return false; }
    $tmpDir = APP_DIR . '/templates/.tmp_' . bin2hex(random_bytes(4));
    mkdir($tmpDir, 0775, true);
    $zip->extractTo($tmpDir);
    $zip->close();
    $src = rtrim($tmpDir . '/' . $prefix, '/');
    $dst = APP_DIR . '/templates/' . $kode;
    $ganti = is_dir($dst);
    if ($ganti) rrmdir($dst);
    copy_dir($src, $dst);
    rrmdir($tmpDir);
    $pesan = ($ganti ? 'Template diperbarui: ' : 'Template dipasang: ') . ($m['nama'] ?? $kode);
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    $kode = $_POST['kode'] ?? '';
    if ($act === 'toggle' && preg_match('/^[a-z0-9_-]+$/', $kode)) {
        db_q('UPDATE templates SET aktif=1-aktif WHERE kode=?', [$kode]);
        flash('Status template diubah.');
    } elseif ($act === 'regen' && preg_match('/^[a-z0-9_-]+$/', $kode)) {
        $n = 0; $gagal = 0;
        foreach (db_rows('SELECT id FROM games WHERE template=?', [$kode]) as $g) {
            try { generate_game($g['id']); $n++; } catch (Exception $e) { $gagal++; }
        }
        flash("$n game dibuat ulang dengan versi template terbaru" . ($gagal ? ", $gagal gagal." : '.'));
    } elseif ($act === 'upload') {
        $f = $_FILES['zip'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) flash('Pilih file .zip template terlebih dahulu.', 'err');
        elseif ($f['size'] > 20 * 1024 * 1024) flash('Ukuran zip maksimal 20 MB.', 'err');
        else { $p = ''; $ok = pasang_zip($f['tmp_name'], $p); flash($p, $ok ? 'ok' : 'err'); }
    }
    redirect('admin/template.php');
}

$tpl = list_templates();
$pakai = [];
foreach (db_rows('SELECT template, COUNT(*) n FROM games GROUP BY template') as $r) $pakai[$r['template']] = $r['n'];
page_header('Template game', 'admin/template.php');
?>
<h1>Template game</h1>
<p class="muted">Template adalah game "master" tanpa soal. Guru memilih template, mengisi soal, lalu sistem membuat salinan game miliknya.</p>
<div class="tpl-grid">
<?php foreach ($tpl as $t): ?>
  <div class="card tpl <?= $t['aktif'] ? '' : 'dim' ?>">
    <div class="tpl-ikon"><?= e($t['ikon']) ?></div>
    <h3><?= e($t['nama']) ?></h3>
    <p class="muted small"><?= e($t['deskripsi'] ?? '') ?></p>
    <p class="small"><span class="tag"><?= $t['mode'] === 'nama' ? 'Isi: daftar nama' : 'Isi: soal & jawaban' ?></span>
       <span class="tag"><?= (int)($pakai[$t['kode']] ?? 0) ?> game</span>
       <code>templates/<?= e($t['kode']) ?></code></p>
    <div class="actions">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="toggle"><input type="hidden" name="kode" value="<?= e($t['kode']) ?>">
        <button class="btn small"><?= $t['aktif'] ? 'Sembunyikan dari guru' : 'Tampilkan ke guru' ?></button></form>
      <form method="post" onsubmit="return confirm('Buat ulang semua game dari template ini? Link dan QR tetap sama.')"><?= csrf_field() ?><input type="hidden" name="act" value="regen"><input type="hidden" name="kode" value="<?= e($t['kode']) ?>">
        <button class="btn small">Terapkan ke semua game</button></form>
    </div>
  </div>
<?php endforeach; ?>
</div>

<form method="post" enctype="multipart/form-data" class="card narrow">
  <h2>Pasang atau perbarui template</h2>
  <?= csrf_field() ?><input type="hidden" name="act" value="upload">
  <p class="small muted">Unggah file .zip berisi <code>manifest.json</code>, <code>template.html</code>, dan folder <code>assets/</code> (opsional).
    Jika kode template sudah ada, template lama akan diganti. Setelah memperbarui, tekan "Terapkan ke semua game" agar game guru ikut berubah.
    Cara lain: salin folder template langsung ke <code>htdocs/app/templates/</code>.</p>
  <input type="file" name="zip" accept=".zip" required>
  <button class="btn primary">Unggah template</button>
</form>
<?php page_footer();
