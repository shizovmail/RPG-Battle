<?php
require __DIR__ . '/inc/boot.php';
$u = require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $nama = mb_substr(trim($_POST['nama'] ?? ''), 0, 80);
    $lama = (string)($_POST['lama'] ?? '');
    $baru = (string)($_POST['baru'] ?? '');
    $ulang = (string)($_POST['ulang'] ?? '');
    if ($nama !== '' && $nama !== $u['nama']) {
        db_q('UPDATE users SET nama=? WHERE id=?', [$nama, $u['id']]);
        flash('Nama tampilan diperbarui.');
    }
    if ($baru !== '') {
        if (!password_verify($lama, $u['password'])) flash('Password lama tidak cocok.', 'err');
        elseif (strlen($baru) < 6) flash('Password baru minimal 6 karakter.', 'err');
        elseif ($baru !== $ulang) flash('Ulangi password baru dengan sama persis.', 'err');
        else { db_q('UPDATE users SET password=? WHERE id=?', [password_hash($baru, PASSWORD_DEFAULT), $u['id']]); flash('Password berhasil diganti.'); }
    }
    redirect('akun.php');
}
page_header('Akun saya');
?>
<h1>Akun saya</h1>
<form method="post" class="card narrow">
  <?= csrf_field() ?>
  <label>Username<input value="<?= e($u['username']) ?>" disabled></label>
  <label>Nama tampilan (muncul di game)<input name="nama" value="<?= e($u['nama']) ?>" maxlength="80"></label>
  <h3>Ganti password</h3>
  <p class="muted small">Kosongkan jika tidak ingin mengganti.</p>
  <label>Password lama<input type="password" name="lama" autocomplete="current-password"></label>
  <label>Password baru<input type="password" name="baru" minlength="6" autocomplete="new-password"></label>
  <label>Ulangi password baru<input type="password" name="ulang" autocomplete="new-password"></label>
  <button class="btn primary">Simpan perubahan</button>
</form>
<?php page_footer();
