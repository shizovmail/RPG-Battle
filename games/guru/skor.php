<?php
require __DIR__ . '/../inc/boot.php';
$u = require_login('guru');
$g = db_row('SELECT * FROM games WHERE id=? AND user_id=?', [(int)($_GET['id'] ?? 0), $u['id']]);
if (!$g) { flash('Game tidak ditemukan.', 'err'); redirect('guru/game.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['act'] ?? '') === 'hapus_semua') { db_q('DELETE FROM skor WHERE game_id=?', [$g['id']]); flash('Semua nilai untuk game ini dihapus.'); }
    if (($_POST['act'] ?? '') === 'hapus' ) { db_q('DELETE FROM skor WHERE id=? AND game_id=?', [(int)$_POST['sid'], $g['id']]); flash('Satu nilai dihapus.'); }
    redirect('guru/skor.php?id=' . $g['id']);
}

$mode = $_GET['m'] ?? 'semua';
$rows = $mode === 'terbaik'
    ? db_rows('SELECT s.* FROM skor s WHERE s.game_id=? AND s.id = (SELECT s2.id FROM skor s2 WHERE s2.game_id=s.game_id AND lower(s2.nama)=lower(s.nama) ORDER BY s2.skor*1.0/s2.maks DESC, s2.durasi ASC LIMIT 1) ORDER BY lower(s.nama)', [$g['id']])
    : db_rows('SELECT * FROM skor WHERE game_id=? ORDER BY waktu DESC', [$g['id']]);

if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="nilai-' . $g['slug'] . '.csv"');
    echo "\xEF\xBB\xBF";
    $o = fopen('php://output', 'w');
    fputcsv($o, ['Nama', 'Benar', 'Jumlah soal', 'Nilai', 'Durasi (detik)', 'Waktu'], ';');
    foreach ($rows as $r) fputcsv($o, [$r['nama'], $r['skor'], $r['maks'], round($r['skor'] / $r['maks'] * 100), $r['durasi'], $r['waktu']], ';');
    exit;
}

$n = count($rows);
$nilai = array_map(function ($r) { return $r['skor'] / $r['maks'] * 100; }, $rows);
page_header('Nilai murid', 'guru/game.php');
?>
<a class="back" href="<?= e(url('guru/game.php')) ?>">Game saya</a>
<div class="sec-head"><h1>Nilai: <?= e($g['judul']) ?></h1>
  <a class="btn" href="?id=<?= $g['id'] ?>&m=<?= e($mode) ?>&csv=1">Unduh Excel (CSV)</a></div>
<div class="stats">
  <div class="stat"><b><?= $n ?></b><span><?= $mode === 'terbaik' ? 'Murid' : 'Kali dimainkan' ?></span></div>
  <div class="stat"><b><?= $n ? round(array_sum($nilai) / $n) : 0 ?></b><span>Rata-rata nilai</span></div>
  <div class="stat"><b><?= $n ? round(max($nilai)) : 0 ?></b><span>Nilai tertinggi</span></div>
  <div class="stat"><b><?= $n ? round(min($nilai)) : 0 ?></b><span>Nilai terendah</span></div>
</div>
<div class="tabs">
  <a href="?id=<?= $g['id'] ?>&m=semua" class="<?= $mode !== 'terbaik' ? 'on' : '' ?>">Semua percobaan</a>
  <a href="?id=<?= $g['id'] ?>&m=terbaik" class="<?= $mode === 'terbaik' ? 'on' : '' ?>">Nilai terbaik per murid</a>
</div>
<div class="card table-wrap">
<?php if (!$rows): ?><p class="muted">Belum ada murid yang menyelesaikan game ini. Bagikan link atau QR dari halaman <a href="<?= e(url('guru/hasil.php?id=' . $g['id'])) ?>">Link & QR</a>.</p><?php else: ?>
<table class="tbl">
  <thead><tr><th>Nama</th><th>Benar</th><th>Nilai</th><th>Durasi</th><th>Waktu</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $v = round($r['skor'] / $r['maks'] * 100); ?>
    <tr><td><?= e($r['nama']) ?></td><td><?= (int)$r['skor'] ?>/<?= (int)$r['maks'] ?></td>
      <td><span class="nilai <?= $v >= 75 ? 'hi' : ($v >= 50 ? 'mid' : 'lo') ?>"><?= $v ?></span></td>
      <td><?= $r['durasi'] ? floor($r['durasi'] / 60) . ':' . str_pad($r['durasi'] % 60, 2, '0', STR_PAD_LEFT) : '-' ?></td>
      <td><?= e(date('d/m H:i', strtotime($r['waktu']))) ?></td>
      <td><form method="post" onsubmit="return confirm('Hapus nilai ini?')"><?= csrf_field() ?><input type="hidden" name="act" value="hapus"><input type="hidden" name="sid" value="<?= $r['id'] ?>"><button class="btn small ghost">Hapus</button></form></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<form method="post" onsubmit="return confirm('Hapus SEMUA nilai game ini?')"><?= csrf_field() ?><input type="hidden" name="act" value="hapus_semua"><button class="btn small danger">Hapus semua nilai</button></form>
<?php endif; ?>
</div>
<?php page_footer();
