<?php
require __DIR__ . '/../inc/boot.php';
$u = require_login('admin');
$st = [
    'Guru aktif' => db_val("SELECT COUNT(*) FROM users WHERE role='guru' AND aktif=1"),
    'Game dibuat' => db_val('SELECT COUNT(*) FROM games'),
    'Game publik (Bank Soal)' => db_val("SELECT COUNT(*) FROM games WHERE visibilitas='public'"),
    'Permainan tercatat' => db_val('SELECT COUNT(*) FROM skor'),
    'Template aktif' => count(list_templates(true)),
];
$pakai = db_rows('SELECT template, COUNT(*) n FROM games GROUP BY template ORDER BY n DESC');
$tpl = list_templates();
$guruTop = db_rows("SELECT u.nama, u.username, COUNT(g.id) n FROM users u LEFT JOIN games g ON g.user_id=u.id WHERE u.role='guru' GROUP BY u.id ORDER BY n DESC LIMIT 8");
$defaultPw = password_verify('admin123', $u['password']);
page_header('Ringkasan admin', 'admin/');
?>
<h1>Ringkasan</h1>
<?php if ($defaultPw): ?>
  <div class="flash err">Password admin masih bawaan (admin123). <a href="<?= e(url('akun.php')) ?>">Ganti sekarang</a> sebelum aplikasi dipakai guru.</div>
<?php endif; ?>
<div class="stats">
  <?php foreach ($st as $k => $v): ?><div class="stat"><b><?= (int)$v ?></b><span><?= e($k) ?></span></div><?php endforeach; ?>
</div>
<div class="grid2">
  <section class="card">
    <h2>Template paling sering dipakai</h2>
    <?php if (!$pakai): ?><p class="muted">Belum ada game yang dibuat.</p><?php endif; ?>
    <?php foreach ($pakai as $p): ?>
      <div class="row-line"><span><?= e(($tpl[$p['template']]['ikon'] ?? '🎮') . ' ' . ($tpl[$p['template']]['nama'] ?? $p['template'])) ?></span><b><?= (int)$p['n'] ?></b></div>
    <?php endforeach; ?>
  </section>
  <section class="card">
    <h2>Game per guru</h2>
    <?php if (!$guruTop): ?><p class="muted">Belum ada akun guru. <a href="<?= e(url('admin/guru.php')) ?>">Tambah guru</a></p><?php endif; ?>
    <?php foreach ($guruTop as $g): ?>
      <div class="row-line"><span><?= e($g['nama'] ?: $g['username']) ?></span><b><?= (int)$g['n'] ?></b></div>
    <?php endforeach; ?>
  </section>
</div>
<?php page_footer();
