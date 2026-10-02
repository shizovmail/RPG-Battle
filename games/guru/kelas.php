<?php
require __DIR__ . '/../inc/boot.php';
$u = require_login('guru');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($id && !db_val('SELECT COUNT(*) FROM kelas WHERE id=? AND user_id=?', [$id, $u['id']])) redirect('guru/kelas.php');
    if ($act === 'simpan') {
        $nama = mb_substr(trim($_POST['nama'] ?? ''), 0, 40);
        $murid = parse_nama($_POST['murid'] ?? '');
        if ($nama === '') { flash('Nama kelas wajib diisi.', 'err'); redirect('guru/kelas.php' . ($id ? '?edit=' . $id : '')); }
        db()->beginTransaction();
        if ($id) db_q('UPDATE kelas SET nama=? WHERE id=?', [$nama, $id]);
        else { db_q('INSERT INTO kelas(user_id,nama) VALUES(?,?)', [$u['id'], $nama]); $id = (int)db()->lastInsertId(); }
        db_q('DELETE FROM murid WHERE kelas_id=?', [$id]);
        foreach ($murid as $m) db_q('INSERT INTO murid(kelas_id,nama) VALUES(?,?)', [$id, $m]);
        db()->commit();
        $pesan = "Kelas $nama disimpan dengan " . count($murid) . ' murid.';
        $terpakai = games_pakai_kelas($u['id'], $id);
        if ($terpakai) { regen_ids($terpakai); $pesan .= ' ' . count($terpakai) . ' game yang memakai daftar ini ikut diperbarui.'; }
        flash($pesan);
    } elseif ($act === 'hapus' && $id) {
        $terpakai = games_pakai_kelas($u['id'], $id);
        foreach ($terpakai as $gid) {
            $gg = db_row('SELECT data FROM games WHERE id=?', [$gid]);
            $d = json_decode((string)$gg['data'], true) ?: [];
            $d['identitas'] = ['cara' => 'bebas', 'kelas_id' => null];
            db_q('UPDATE games SET data=? WHERE id=?', [json_encode($d, JSON_UNESCAPED_UNICODE), $gid]);
        }
        db_q('DELETE FROM kelas WHERE id=?', [$id]);
        regen_ids($terpakai);
        flash('Kelas dihapus.' . ($terpakai ? ' ' . count($terpakai) . ' game yang memakainya dialihkan ke "ketik bebas".' : ''));
    }
    redirect('guru/kelas.php');
}

$edit = null;
if (!empty($_GET['edit'])) {
    $edit = db_row('SELECT * FROM kelas WHERE id=? AND user_id=?', [(int)$_GET['edit'], $u['id']]);
    if ($edit) $edit['murid'] = array_column(db_rows('SELECT nama FROM murid WHERE kelas_id=? ORDER BY id', [$edit['id']]), 'nama');
}
$list = db_rows('SELECT k.*, (SELECT COUNT(*) FROM murid m WHERE m.kelas_id=k.id) n FROM kelas k WHERE k.user_id=? ORDER BY k.nama', [$u['id']]);
page_header('Kelas & murid', 'guru/kelas.php');
?>
<h1>Kelas & murid</h1>
<p class="muted">Daftarkan semua kelas beserta anggotanya di sini. Selain untuk roda nama acak dan pembagian
  kelompok, daftar ini juga bisa dijadikan "akun tanpa password" — saat membuat kuis, labirin, atau game
  lain, pilih "Pilih dari daftar kelas" pada bagian Nama murid agar murid tinggal memilih namanya sendiri,
  tanpa perlu mengetik.</p>
<div class="grid-side">
  <form method="post" class="card">
    <h2><?= $edit ? 'Edit kelas' : 'Tambah kelas' ?></h2>
    <?= csrf_field() ?><input type="hidden" name="act" value="simpan"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <label>Nama kelas<input name="nama" value="<?= e($edit['nama'] ?? '') ?>" placeholder="contoh: 7A" required></label>
    <label>Nama murid, satu per baris
      <textarea name="murid" rows="14" placeholder="Salin dari Excel lalu tempel di sini"><?= e(implode("\n", $edit['murid'] ?? [])) ?></textarea></label>
    <button class="btn primary wide">Simpan kelas</button>
    <?php if ($edit): ?><a class="btn ghost wide" href="<?= e(url('guru/kelas.php')) ?>">Batal</a><?php endif; ?>
  </form>
  <div>
    <?php if (!$list): ?><div class="card empty"><p>Belum ada kelas tersimpan.</p></div><?php endif; ?>
    <div class="list-simple">
    <?php foreach ($list as $k): $pakai = count(games_pakai_kelas($u['id'], $k['id'])); ?>
      <div class="list-item">
        <span><b><?= e($k['nama']) ?></b> <small class="muted"><?= (int)$k['n'] ?> murid</small>
          <?php if ($pakai): ?><span class="kelas-badge"><?= $pakai ?> game pakai daftar ini</span><?php endif; ?></span>
        <span class="actions">
          <a class="btn small" href="?edit=<?= $k['id'] ?>">Edit</a>
          <form method="post" onsubmit="return confirm('Hapus kelas <?= e($k['nama']) ?>?<?= $pakai ? ' ' . $pakai . ' game yang memakainya akan dialihkan ke ketik bebas.' : '' ?>')"><?= csrf_field() ?><input type="hidden" name="act" value="hapus"><input type="hidden" name="id" value="<?= $k['id'] ?>"><button class="btn small ghost">Hapus</button></form>
        </span>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
</div>
<?php page_footer();
