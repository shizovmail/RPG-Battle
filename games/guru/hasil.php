<?php
require __DIR__ . '/../inc/boot.php';
$u = require_login('guru');
$g = db_row('SELECT * FROM games WHERE id=? AND user_id=?', [(int)($_GET['id'] ?? 0), $u['id']]);
if (!$g) { flash('Game tidak ditemukan.', 'err'); redirect('guru/game.php'); }
$tpl = list_templates();
$t = $tpl[$g['template']] ?? ['nama' => $g['template'], 'ikon' => '🎮', 'skor' => false];
$link = game_link($u['username'], $g['slug']);
page_header('Bagikan game', 'guru/game.php');
?>
<a class="back" href="<?= e(url('guru/game.php')) ?>">Game saya</a>
<div class="share">
  <div class="card share-card" id="kartu-qr">
    <div class="share-sek"><?= e(setting('nama_sekolah')) ?></div>
    <h1><?= e($g['judul']) ?></h1>
    <p class="muted"><?= e($t['ikon'] . ' ' . $t['nama']) ?><?= $g['kelas'] ? ' · Kelas ' . e($g['kelas']) : '' ?></p>
    <div id="qr" class="qr" data-link="<?= e($link) ?>"></div>
    <p class="scan"><?= !empty($t['live']) ? 'Pindai untuk masuk lobi' : 'Pindai untuk bermain' ?></p>
    <p class="link-print"><?= e($link) ?></p>
  </div>
  <div class="card">
    <?php if (!$g['aktif']): ?><div class="flash err">Game ini sedang <b>ditutup</b>. Murid belum bisa memainkannya.</div><?php endif; ?>
    <h2>Link game</h2>
    <div class="copy-row"><input id="link" value="<?= e($link) ?>" readonly><button class="btn primary" data-copy="#link">Salin</button></div>
    <?php if (!empty($t['live'])): ?>
      <div class="flash ok"><b>Game live.</b> Murid masuk lewat link/QR ini, tetapi pertandingan dimulai dan dikendalikan dari <b>Panel wasit</b>.
        <div class="actions" style="margin-top:10px"><a class="btn primary big" href="<?= e(url('guru/live.php?id=' . $g['id'])) ?>">🪢 Buka panel wasit</a></div></div>
    <?php endif; ?>
    <div class="actions">
      <?php if (empty($t['live'])): ?><a class="btn" href="<?= e($link) ?>" target="_blank" rel="noopener">Coba main</a><?php endif; ?>
      <button class="btn" id="unduh-qr">Unduh QR (PNG)</button>
      <button class="btn" onclick="window.print()">Cetak kartu QR</button>
      <a class="btn" href="https://wa.me/?text=<?= rawurlencode('Ayo main: ' . $g['judul'] . ' ' . $link) ?>" target="_blank" rel="noopener">Kirim ke WhatsApp</a>
    </div>
    <h2>Kelola</h2>
    <div class="actions">
      <a class="btn" href="<?= e(url('guru/buat.php?id=' . $g['id'])) ?>">Edit isi game</a>
      <?php if (!empty($t['skor'])): ?><a class="btn" href="<?= e(url('guru/skor.php?id=' . $g['id'])) ?>">Lihat nilai murid</a><?php endif; ?>
      <?php if (($t['mode'] ?? 'soal') === 'soal'): ?><a class="btn" href="<?= e(url('guru/konversi.php?id=' . $g['id'])) ?>">🔁 Konversi ke jenis lain</a>
      <form method="post" action="<?= e(url('guru/aksi.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $g['id'] ?>"><input type="hidden" name="act" value="visibilitas"><input type="hidden" name="back" value="hasil">
        <button class="btn"><?= $g['visibilitas'] === 'public' ? '🌐 Publik · jadikan privat' : '🔒 Privat · jadikan publik' ?></button></form><?php endif; ?>
      <form method="post" action="<?= e(url('guru/aksi.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $g['id'] ?>"><input type="hidden" name="act" value="toggle"><input type="hidden" name="back" value="hasil">
        <button class="btn"><?= $g['aktif'] ? 'Tutup game' : 'Buka game' ?></button></form>
    </div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function(){
  var box = document.getElementById('qr');
  if (typeof QRCode === 'undefined') {
    box.innerHTML = '<img alt="QR code" width="260" height="260" src="https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' + encodeURIComponent(box.dataset.link) + '">';
    document.getElementById('unduh-qr').onclick = function(){ window.open(box.querySelector('img').src); };
    return;
  }
  new QRCode(box, {text: box.dataset.link, width: 260, height: 260, correctLevel: QRCode.CorrectLevel.M});
  document.getElementById('unduh-qr').onclick = function(){
    var c = box.querySelector('canvas'), img = box.querySelector('img');
    var src = c ? c.toDataURL('image/png') : (img ? img.src : '');
    if (!src) return;
    var a = document.createElement('a'); a.href = src; a.download = 'qr-<?= e($g['slug']) ?>.png'; a.click();
  };
})();
</script>
<?php page_footer();
