<?php
require __DIR__ . '/../inc/boot.php';
$u = require_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($act === 'tambah') {
        $un = strtolower(trim($_POST['username'] ?? ''));
        $nama = mb_substr(trim($_POST['nama'] ?? ''), 0, 80);
        $pw = (string)($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? 'guru') === 'admin' ? 'admin' : 'guru';
        if (!valid_username($un)) flash('Username 3–30 karakter: huruf kecil, angka, atau garis bawah (_). Username dipakai sebagai nama folder game.', 'err');
        elseif (strlen($pw) < 6) flash('Password minimal 6 karakter.', 'err');
        elseif (db_val('SELECT COUNT(*) FROM users WHERE username=?', [$un])) flash("Username $un sudah dipakai.", 'err');
        else {
            db_q('INSERT INTO users(username,password,nama,role) VALUES(?,?,?,?)', [$un, password_hash($pw, PASSWORD_DEFAULT), $nama, $role]);
            flash("Akun $un berhasil dibuat.");
        }
    } elseif ($act === 'reset' && $id) {
        $pw = (string)($_POST['password'] ?? '');
        if (strlen($pw) < 6) flash('Password baru minimal 6 karakter.', 'err');
        else { db_q('UPDATE users SET password=? WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $id]); flash('Password berhasil direset.'); }
    } elseif ($act === 'toggle' && $id && $id !== (int)$u['id']) {
        db_q('UPDATE users SET aktif=1-aktif WHERE id=?', [$id]);
        flash('Status akun diubah.');
    } elseif ($act === 'nama' && $id) {
        db_q('UPDATE users SET nama=? WHERE id=?', [mb_substr(trim($_POST['nama'] ?? ''), 0, 80), $id]);
        flash('Nama diperbarui.');
    } elseif ($act === 'hapus' && $id && $id !== (int)$u['id']) {
        $x = db_row('SELECT * FROM users WHERE id=?', [$id]);
        if ($x && ($_POST['konfirmasi'] ?? '') === $x['username']) {
            foreach (db_rows('SELECT slug FROM games WHERE user_id=?', [$id]) as $g) delete_game_folder($x['username'], $g['slug']);
            if (valid_username($x['username'])) @rmdir(APP_DIR . '/games/' . $x['username']);
            db_q('DELETE FROM users WHERE id=?', [$id]);
            flash('Akun beserta semua game-nya dihapus.');
        } else flash('Ketik username dengan benar untuk menghapus.', 'err');
    }
    redirect('admin/guru.php');
}

$list = db_rows("SELECT u.*, (SELECT COUNT(*) FROM games g WHERE g.user_id=u.id) n FROM users u ORDER BY u.role, u.nama, u.username");
page_header('Akun guru', 'admin/guru.php');
?>
<h1>Akun guru</h1>
<div class="grid-side">
  <form method="post" class="card">
    <h2>Tambah akun</h2>
    <?= csrf_field() ?><input type="hidden" name="act" value="tambah">
    <label>Username<input name="username" pattern="[a-z0-9_]{3,30}" placeholder="contoh: bu_rina" required>
      <small>Huruf kecil, angka, _ . Muncul di link game.</small></label>
    <label>Nama lengkap<input name="nama" placeholder="Rina Wulandari, S.Pd." required></label>
    <label>Password awal<input name="password" minlength="6" required></label>
    <label>Peran<select name="role"><option value="guru">Guru</option><option value="admin">Admin</option></select></label>
    <button class="btn primary wide">Buat akun</button>
  </form>

  <div class="card table-wrap">
    <table class="tbl">
      <thead><tr><th>Nama</th><th>Username</th><th>Peran</th><th>Game</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($list as $r): ?>
        <tr class="<?= $r['aktif'] ? '' : 'dim' ?>">
          <td><?= e($r['nama']) ?></td>
          <td><code><?= e($r['username']) ?></code></td>
          <td><?= $r['role'] === 'admin' ? 'Admin' : 'Guru' ?></td>
          <td><?= (int)$r['n'] ?></td>
          <td><?= $r['aktif'] ? '<span class="tag ok">Aktif</span>' : '<span class="tag off">Nonaktif</span>' ?></td>
          <td>
            <details class="menu"><summary class="btn small">Kelola</summary>
              <div class="menu-box">
                <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="nama"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <label>Ubah nama<input name="nama" value="<?= e($r['nama']) ?>"></label><button class="btn small">Simpan nama</button></form>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="reset"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <label>Password baru<input name="password" minlength="6" required></label><button class="btn small">Reset password</button></form>
                <?php if ($r['id'] != $u['id']): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <button class="btn small"><?= $r['aktif'] ? 'Nonaktifkan akun' : 'Aktifkan akun' ?></button></form>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="hapus"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <label>Ketik <b><?= e($r['username']) ?></b> untuk menghapus akun dan semua game-nya<input name="konfirmasi"></label>
                  <button class="btn small danger">Hapus permanen</button></form>
                <?php endif; ?>
              </div>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php page_footer();
