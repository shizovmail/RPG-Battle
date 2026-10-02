<?php
require __DIR__ . '/../inc/boot.php';
$u = require_login('guru');
$tpl = list_templates(true);
$nGame = db_val('SELECT COUNT(*) FROM games WHERE user_id=?', [$u['id']]);
$nMain = db_val('SELECT COUNT(*) FROM skor s JOIN games g ON g.id=s.game_id WHERE g.user_id=?', [$u['id']]);
$baru = db_rows('SELECT * FROM games WHERE user_id=? ORDER BY updated_at DESC LIMIT 4', [$u['id']]);
page_header('Buat game', 'guru/');
?>
<div class="hello">
  <div>
    <h1>Halo, <?= e($u['nama'] ?: $u['username']) ?></h1>
    <p class="muted">Pilih jenis game, isi soal atau daftar nama, lalu bagikan link dan QR code ke murid.</p>
  </div>
  <div class="mini-stats"><span><b><?= (int)$nGame ?></b> game</span><span><b><?= (int)$nMain ?></b> kali dimainkan</span></div>
</div>

<h2>Pilih jenis game</h2>
<div class="tpl-grid">
<?php foreach ($tpl as $t): ?>
  <a class="card tpl pick" href="<?= e(url('guru/buat.php?t=' . $t['kode'])) ?>">
    <div class="tpl-ikon"><?= e($t['ikon']) ?></div>
    <h3><?= e($t['nama']) ?></h3>
    <p class="muted small"><?= e($t['deskripsi'] ?? '') ?></p>
    <span class="tag"><?= e($t['tag'] ?? ($t['mode'] === 'nama' ? 'Pakai daftar nama murid' : 'Pakai soal pilihan ganda')) ?></span>
  </a>
<?php endforeach; ?>
<?php if (!$tpl): ?><p class="muted">Belum ada template yang aktif. Hubungi admin.</p><?php endif; ?>
</div>

<?php if ($baru): ?>
<h2>Terakhir diubah</h2>
<div class="list-simple">
  <?php foreach ($baru as $g): ?>
    <a href="<?= e(url('guru/hasil.php?id=' . $g['id'])) ?>"><span><?= e(($tpl[$g['template']]['ikon'] ?? '🎮') . ' ' . $g['judul']) ?></span><small><?= e($g['kelas']) ?></small></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php page_footer();
