<?php
// Halaman cetak RPG Battle: petunjuk permainan, stat awal, dan penjelasan skill tiap karakter.
// Angka mengikuti stat yang sedang dipakai (sesi aktif/terakhir, atau pengaturan game bila belum ada sesi).
require __DIR__ . '/../inc/boot.php';
require_once __DIR__ . '/../inc/rpg.php';
$u = require_login('guru');
$g = db_row('SELECT * FROM games WHERE id=? AND user_id=?', [(int)($_GET['id'] ?? 0), $u['id']]);
if (!$g || $g['template'] !== 'rpg_battle') { flash('Game tidak ditemukan.', 'err'); redirect('guru/game.php'); }
$s = rpg_sesi_aktif($g['id']) ?: rpg_sesi_terakhir($g['id']);
$data = json_decode((string)$g['data'], true) ?: [];
$cfg = $s ? rpg_cfg_sesi($s) : rpg_cfg_bersih($data['pengaturan'] ?? []);
$p = rpg_petunjuk($cfg);
?><!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Petunjuk RPG Battle — <?= e($g['judul']) ?></title>
<style>
  :root{color-scheme:light}
  *{box-sizing:border-box}
  body{font-family:system-ui,"Segoe UI",sans-serif;color:#1c2436;margin:0;padding:18px;line-height:1.45;background:#fff}
  .wrap{max-width:820px;margin:0 auto}
  h1{margin:0 0 2px;font-size:1.6rem}h2{margin:22px 0 8px;font-size:1.15rem;border-bottom:2px solid #1c2436;padding-bottom:3px}
  .sub{color:#5b6477;margin:0 0 10px}
  ol,ul{margin:6px 0 6px 20px;padding:0}li{margin:3px 0}
  table{border-collapse:collapse;width:100%;margin:6px 0}
  th,td{border:1px solid #9aa3b8;padding:5px 8px;text-align:center}th{background:#eef1f7}
  td.l,th.l{text-align:left}
  .role{border:1.5px solid #9aa3b8;border-radius:10px;padding:8px 12px;margin:10px 0;break-inside:avoid}
  .role h3{margin:0 0 2px;font-size:1.05rem}
  .role .st{font-size:.9rem;color:#33405e;margin-bottom:4px}
  .sk{margin:4px 0;font-size:.92rem}.sk b{font-size:.95rem}.sk .m{color:#5b6477;font-size:.82rem}
  .bar{display:flex;gap:8px;margin-bottom:12px}
  .bar button,.bar a{background:#2c56c9;color:#fff;border:0;border-radius:10px;padding:9px 16px;font:700 .95rem system-ui;cursor:pointer;text-decoration:none}
  .bar a{background:#e8ecf6;color:#1c2436}
  @media print{.bar{display:none}body{padding:0}h2{break-after:avoid}}
  @page{margin:14mm}
</style></head><body><div class="wrap">
<div class="bar"><button onclick="window.print()">🖨️ Cetak</button><a href="javascript:history.back()">← Kembali</a></div>
<h1>⚔️ <?= e($g['judul']) ?></h1>
<p class="sub">Petunjuk permainan RPG Battle · <?= e(rpg_tim_nama(1)) ?> vs <?= e(rpg_tim_nama(2)) ?> · <?= count($p['peran']) ?> peran per tim<?= !empty($cfg['pakai_fighter']) ? ' (5 vs 5, Fighter aktif)' : ' (4 vs 4)' ?></p>

<h2>Petunjuk permainan</h2>
<ol><?php foreach ($p['umum'] as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ol>

<h2>Stat awal karakter</h2>
<table><tr><th class="l">Peran</th><th>HP</th><th>Attack</th><th>Defend</th></tr>
<?php foreach ($p['peran'] as $r): ?><tr><td class="l"><?= e($r['ikon'] . ' ' . $r['nama']) ?></td><td><?= (int)$r['hp'] ?></td><td><?= (int)$r['atk'] ?></td><td><?= (int)$r['def'] ?></td></tr><?php endforeach; ?></table>
<p class="sub">Angka di atas adalah stat yang sedang dipakai pada game ini (sudah mengikuti perubahan guru bila ada).</p>

<h2>Penjelasan skill tiap karakter</h2>
<?php foreach ($p['peran'] as $r): ?>
<div class="role"><h3><?= e($r['ikon'] . ' ' . $r['nama']) ?></h3>
  <div class="st"><?= e($r['ringkas']) ?> · HP <?= (int)$r['hp'] ?> · Attack <?= (int)$r['atk'] ?> · Defend <?= (int)$r['def'] ?></div>
  <?php foreach ($r['skill'] as $k): ?>
    <div class="sk"><b><?= e($k['ikon'] . ' ' . $k['nama']) ?></b> <span class="m">(<?= e($k['tgt']) ?><?= $k['cd'] > 0 ? ', cooldown ' . (int)$k['cd'] : ', tanpa cooldown' ?>)</span><br><?= e($k['desc']) ?><?= !empty($k['angka']) ? ' <i>' . e($k['angka']) . '</i>' : '' ?></div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>
</div></body></html>
