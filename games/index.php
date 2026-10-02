<?php
require __DIR__ . '/inc/boot.php';
$u = current_user();
if ($u) redirect(home_for($u));
$err = '';
$un = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $un = strtolower(trim($_POST['username'] ?? ''));
    $pw = (string)($_POST['password'] ?? '');
    $row = db_row('SELECT * FROM users WHERE username=?', [$un]);
    if ($row && $row['aktif'] && password_verify($pw, $row['password'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$row['id'];
        redirect(home_for($row));
    }
    usleep(400000);
    $err = 'Username atau password salah, atau akun sedang dinonaktifkan.';
}
page_header('Masuk', '', false);
?>
<section class="login">
  <div class="login-art" aria-hidden="true">
    <span>A</span><span>?</span><span>7</span><span>✓</span><span>π</span><span>B</span>
  </div>
  <form method="post" class="card login-card">
    <h1>Masuk ke ruang guru</h1>
    <p class="muted">Buat game kuis, labirin, dan roda nama untuk kelas Anda.</p>
    <?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>Username<input name="username" value="<?= e($un) ?>" autocomplete="username" required autofocus></label>
    <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
    <button class="btn primary wide">Masuk</button>
    <p class="muted small">Belum punya akun? Minta admin sekolah untuk membuatkannya.</p>
  </form>
</section>
<?php page_footer();
