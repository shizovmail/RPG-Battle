<?php
function page_header($title, $active = '', $nav = true)
{
    $u = current_user();
    $sekolah = setting('nama_sekolah', 'Sekolah');
    $logo = setting('logo');
    $menu = [];
    if ($u && $nav) {
        $menu = $u['role'] === 'admin'
            ? ['admin/' => 'Ringkasan', 'admin/guru.php' => 'Akun guru', 'admin/template.php' => 'Template game', 'admin/pengaturan.php' => 'Pengaturan']
            : ['guru/' => 'Buat game', 'guru/game.php' => 'Game saya', 'guru/bank.php' => 'Bank soal', 'guru/kelas.php' => 'Kelas & murid'];
    }
    ?><!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> | <?= e($sekolah) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head><body>
<header class="top">
  <a class="brand" href="<?= e(url($u ? home_for($u) : 'index.php')) ?>">
    <?php if ($logo): ?><img src="<?= e(url($logo)) ?>" alt=""><?php else: ?><span class="brand-mark">G</span><?php endif; ?>
    <span><b><?= e($sekolah) ?></b><small><?= e(APP_NAME) ?></small></span>
  </a>
  <?php if ($menu): ?>
  <nav class="nav">
    <?php foreach ($menu as $href => $label): ?>
      <a href="<?= e(url($href)) ?>" class="<?= $active === $href ? 'on' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="who"><a href="<?= e(url('akun.php')) ?>"><?= e($u['nama'] ?: $u['username']) ?></a> <a class="out" href="<?= e(url('logout.php')) ?>">Keluar</a></div>
  <?php endif; ?>
</header>
<main class="page">
<?php foreach (take_flash() as $f): ?>
  <div class="flash <?= e($f[0]) ?>"><?= e($f[1]) ?></div>
<?php endforeach;
}

function page_footer()
{
    ?></main>
<footer class="foot"><?= e(setting('nama_sekolah')) ?> — <?= e(setting('tagline')) ?></footer>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body></html><?php
}
