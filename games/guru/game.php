<?php
require __DIR__ . '/../inc/boot.php';
$u = require_login('guru');
$tpl = list_templates();
$q = trim($_GET['q'] ?? '');
$ft = $_GET['t'] ?? '';
$fk = $_GET['k'] ?? '';
$fv = in_array($_GET['v'] ?? '', ['public', 'private'], true) ? $_GET['v'] : '';
$sql = 'SELECT g.*, (SELECT COUNT(*) FROM skor s WHERE s.game_id=g.id) n_main FROM games g WHERE g.user_id=?';
$p = [$u['id']];
if ($q !== '') { $sql .= ' AND g.judul LIKE ?'; $p[] = '%' . $q . '%'; }
if ($ft !== '') { $sql .= ' AND g.template=?'; $p[] = $ft; }
if ($fk !== '') { $sql .= ' AND g.kelas=?'; $p[] = $fk; }
if ($fv !== '') { $sql .= ' AND g.visibilitas=?'; $p[] = $fv; }
$list = db_rows($sql . ' ORDER BY g.updated_at DESC', $p);
$kelasOpt = array_column(db_rows("SELECT DISTINCT kelas FROM games WHERE user_id=? AND kelas<>'' ORDER BY kelas", [$u['id']]), 'kelas');
page_header('Game saya', 'guru/game.php');
?>
<div class="sec-head"><h1>Game saya</h1><a class="btn primary" href="<?= e(url('guru/')) ?>">+ Buat game baru</a></div>
<form class="filter" method="get">
  <input name="q" value="<?= e($q) ?>" placeholder="Cari judul…">
  <select name="t"><option value="">Semua jenis</option><?php foreach ($tpl as $t): ?><option value="<?= e($t['kode']) ?>" <?= $ft === $t['kode'] ? 'selected' : '' ?>><?= e($t['nama']) ?></option><?php endforeach; ?></select>
  <select name="k"><option value="">Semua kelas</option><?php foreach ($kelasOpt as $k): ?><option <?= $fk === $k ? 'selected' : '' ?>><?= e($k) ?></option><?php endforeach; ?></select>
  <select name="v"><option value="">Semua visibilitas</option><option value="private" <?= $fv === 'private' ? 'selected' : '' ?>>🔒 Privat</option><option value="public" <?= $fv === 'public' ? 'selected' : '' ?>>🌐 Publik</option></select>
  <button class="btn">Tampilkan</button>
</form>

<?php if (!$list): ?>
  <div class="card empty"><p>Belum ada game<?= ($q || $ft || $fk || $fv) ? ' yang cocok dengan filter' : '' ?>.</p><a class="btn primary" href="<?= e(url('guru/')) ?>">Buat game pertama</a></div>
<?php endif; ?>

<div class="game-list">
<?php foreach ($list as $g): $t = $tpl[$g['template']] ?? ['nama' => $g['template'], 'ikon' => '🎮']; $link = game_link($u['username'], $g['slug']); ?>
  <article class="card game <?= $g['aktif'] ? '' : 'dim' ?>">
    <div class="game-ikon"><?= e($t['ikon']) ?></div>
    <div class="game-info">
      <h3><?= e($g['judul']) ?> <?= $g['aktif'] ? '' : '<span class="tag off">Ditutup</span>' ?> <?= bank_badge($g['visibilitas']) ?></h3>
      <p class="muted small"><?= e($t['nama']) ?> · <?= e(jumlah_isi($g)) ?><?= $g['kelas'] ? ' · ' . e($g['kelas']) : '' ?><?= !empty($t['skor']) ? ' · dimainkan ' . (int)$g['n_main'] . '×' : '' ?> · diubah <?= e(date('d/m/Y H:i', strtotime($g['updated_at']))) ?></p>
    </div>
    <div class="actions">
      <?php if (!empty($t['live'])): ?><a class="btn small primary" href="<?= e(url('guru/live.php?id=' . $g['id'])) ?>">Panel wasit</a>
      <?php else: ?><a class="btn small" href="<?= e($link) ?>" target="_blank" rel="noopener">Main</a><?php endif; ?>
      <a class="btn small" href="<?= e(url('guru/hasil.php?id=' . $g['id'])) ?>">Link & QR</a>
      <a class="btn small" href="<?= e(url('guru/buat.php?id=' . $g['id'])) ?>">Edit</a>
      <?php if (!empty($t['skor'])): ?><a class="btn small" href="<?= e(url('guru/skor.php?id=' . $g['id'])) ?>">Nilai</a><?php endif; ?>
      <details class="menu"><summary class="btn small">Lainnya</summary>
        <div class="menu-box">
          <form method="post" action="<?= e(url('guru/aksi.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $g['id'] ?>"><input type="hidden" name="act" value="toggle">
            <button class="btn small wide"><?= $g['aktif'] ? 'Tutup game' : 'Buka game' ?></button></form>
          <form method="post" action="<?= e(url('guru/aksi.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $g['id'] ?>"><input type="hidden" name="act" value="duplikat">
            <button class="btn small wide">Duplikat</button></form>
          <?php if (($t['mode'] ?? 'soal') === 'soal'): ?>
          <a class="btn small wide" href="<?= e(url('guru/konversi.php?id=' . $g['id'])) ?>">🔁 Konversi ke jenis game lain</a>
          <form method="post" action="<?= e(url('guru/aksi.php')) ?>" <?= $g['visibilitas'] === 'public' ? '' : 'onsubmit="return confirm(\'Jadikan publik? Guru lain yang punya akun akan bisa melihat soal beserta jawabannya dan memakainya di game mereka.\')"' ?>><?= csrf_field() ?><input type="hidden" name="id" value="<?= $g['id'] ?>"><input type="hidden" name="act" value="visibilitas">
            <button class="btn small wide"><?= $g['visibilitas'] === 'public' ? '🔒 Jadikan privat' : '🌐 Jadikan publik' ?></button></form>
          <?php endif; ?>
          <form method="post" action="<?= e(url('guru/aksi.php')) ?>" onsubmit="return confirm('Hapus game ini beserta nilai murid? Link dan QR tidak akan berfungsi lagi.')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $g['id'] ?>"><input type="hidden" name="act" value="hapus">
            <button class="btn small wide danger">Hapus</button></form>
        </div>
      </details>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php page_footer();
