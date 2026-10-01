<?php
require __DIR__ . '/../inc/boot.php';
$u = require_login('guru');

$id = (int)($_GET['id'] ?? 0);
$game = null;
if ($id) {
    $game = db_row('SELECT * FROM games WHERE id=? AND user_id=?', [$id, $u['id']]);
    if (!$game) { flash('Game tidak ditemukan.', 'err'); redirect('guru/game.php'); }
    $kode = $game['template'];
} else {
    $kode = (string)($_GET['t'] ?? '');
}
$all = list_templates();
if (!isset($all[$kode]) || (!$game && !$all[$kode]['aktif'])) { flash('Jenis game tidak tersedia.', 'err'); redirect('guru/'); }
$m = $all[$kode];
if (!empty($m['form']) && preg_match('/^[a-z0-9_]+\.php$/', (string)$m['form'])) { require __DIR__ . '/' . $m['form']; exit; } // template dengan formulir sendiri (mis. RPG Battle)
if (!empty($m['live'])) { require __DIR__ . '/buat_live.php'; exit; } // game live punya formulir sendiri
$mode = $m['mode'];
$nOpsi = (int)($m['jumlah_opsi'] ?? 4);

$data = $game ? (json_decode($game['data'], true) ?: []) : [];
$judul = $game['judul'] ?? '';
$kelasLblLama = $game['kelas'] ?? ''; // label lama (kompatibilitas game sebelum fitur kelas dropdown)
$errors = [];
$kelasSaya = [];
foreach (db_rows('SELECT id,nama FROM kelas WHERE user_id=? ORDER BY nama', [$u['id']]) as $k) {
    $kelasSaya[] = ['id' => (int)$k['id'], 'nama' => $k['nama'], 'murid' => array_column(db_rows('SELECT nama FROM murid WHERE kelas_id=? ORDER BY id', [$k['id']]), 'nama')];
}
// kelas yang sedang terpilih di dropdown (untuk ditampilkan kembali)
$kelasIdSelected = 0;
if ($mode === 'soal' && !empty($data['identitas']['kelas_id'])) $kelasIdSelected = (int)$data['identitas']['kelas_id'];
if (!$kelasIdSelected && $kelasLblLama !== '') {
    foreach ($kelasSaya as $k) { if ($k['nama'] === $kelasLblLama) { $kelasIdSelected = $k['id']; break; } }
}
$kelasLbl = $kelasLblLama;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $judul = mb_substr(trim($_POST['judul'] ?? ''), 0, 100);
    $kelasIdSelected = (int)($_POST['kelas_id'] ?? 0);
    $kelasRow = $kelasIdSelected ? db_row('SELECT k.nama, (SELECT COUNT(*) FROM murid m WHERE m.kelas_id=k.id) n FROM kelas k WHERE k.id=? AND k.user_id=?', [$kelasIdSelected, $u['id']]) : null;
    if ($kelasIdSelected && !$kelasRow) { $errors[] = 'Kelas yang dipilih tidak ditemukan.'; $kelasIdSelected = 0; }
    elseif ($kelasRow && (int)$kelasRow['n'] === 0) { $errors[] = 'Kelas yang dipilih belum memiliki murid. Tambahkan dulu di menu Kelas & Murid.'; }
    $kelasValid = $kelasIdSelected && $kelasRow && (int)$kelasRow['n'] > 0;
    $kelasLbl = $kelasValid ? $kelasRow['nama'] : '';
    if ($judul === '') $errors[] = 'Judul game wajib diisi.';
    $new = ['mode' => $mode, 'pengaturan' => parse_pengaturan($m, $_POST['p'] ?? [])];
    if ($mode === 'soal') {
        list($soal, $es) = parse_soal($m, $_POST['soal'] ?? []);
        $errors = array_merge($errors, $es);
        $new['soal'] = $soal;
        $new['identitas'] = ['cara' => $kelasValid ? 'kelas' : 'bebas', 'kelas_id' => $kelasValid ? $kelasIdSelected : null];
    } else {
        $new['nama'] = parse_nama($_POST['nama'] ?? '');
        $min = (int)($m['min_nama'] ?? 2);
        if (count($new['nama']) < $min) $errors[] = "Isi minimal $min nama.";
    }
    $data = $new;
    if (!$errors) {
        $json = json_encode($new, JSON_UNESCAPED_UNICODE);
        if ($game) {
            db_q("UPDATE games SET judul=?, kelas=?, data=?, updated_at=datetime('now','localtime') WHERE id=?", [$judul, $kelasLbl, $json, $game['id']]);
            $gid = (int)$game['id'];
        } else {
            db_q('INSERT INTO games(user_id,template,judul,slug,kelas,data,token) VALUES(?,?,?,?,?,?,?)',
                [$u['id'], $kode, $judul, new_slug($judul), $kelasLbl, $json, bin2hex(random_bytes(8))]);
            $gid = (int)db()->lastInsertId();
        }
        try {
            generate_game($gid);
            flash($game ? 'Perubahan disimpan dan game sudah diperbarui. Link & QR tetap sama.' : 'Game berhasil dibuat!');
            redirect('guru/hasil.php?id=' . $gid);
        } catch (Exception $e) {
            $errors[] = 'Game tersimpan, tetapi file gagal dibuat: ' . $e->getMessage();
        }
    }
}

$peng = $data['pengaturan'] ?? [];
$soalList = $data['soal'] ?? [];
if ($mode === 'soal' && !$soalList) $soalList = [['q' => '', 'o' => [], 'b' => 0]];

function soal_row($i, $s, $m)
{
    $tetap = isset($m['opsi_tetap']) && is_array($m['opsi_tetap']) ? array_values($m['opsi_tetap']) : null;
    $nOpsi = $tetap ? count($tetap) : (int)($m['jumlah_opsi'] ?? 4);
    $labelQ = $m['label_soal'] ?? 'Pertanyaan';
    $labelO = $m['label_opsi'] ?? 'Pilihan';
    $b = isset($s['b']) ? (int)$s['b'] : 0;
    ob_start(); ?>
  <div class="soal<?= (!$tetap && $nOpsi === 1) ? ' soal-pasang' : '' ?>" data-soal>
    <div class="soal-head"><b class="soal-no"></b><button type="button" class="btn small ghost" data-hapus-soal>Hapus</button></div>
    <?php if (!$tetap && $nOpsi === 1): ?>
      <div class="pasang">
        <label><?= e($labelQ) ?><textarea name="soal[<?= $i ?>][q]" rows="2" placeholder="<?= e($m['contoh_soal'] ?? '') ?>"><?= e($s['q'] ?? '') ?></textarea></label>
        <label><?= e($labelO) ?><input name="soal[<?= $i ?>][o][0]" value="<?= e($s['o'][0] ?? '') ?>" placeholder="<?= e($m['contoh_opsi'] ?? '') ?>" data-jawaban></label>
      </div>
    <?php else: ?>
      <textarea name="soal[<?= $i ?>][q]" rows="2" placeholder="<?= e($m['contoh_soal'] ?? ('Tulis ' . strtolower($labelQ) . '…')) ?>" aria-label="<?= e($labelQ) ?>"><?= e($s['q'] ?? '') ?></textarea>
      <?php if ($tetap): ?>
        <div class="tetap-grid">
          <?php foreach ($tetap as $j => $t): ?>
            <label class="tetap"><input type="radio" name="soal[<?= $i ?>][b]" value="<?= $j ?>" <?= $b === $j ? 'checked' : '' ?>> <span><?= e($t) ?></span></label>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="opsi-grid">
          <?php for ($j = 0; $j < $nOpsi; $j++): ?>
            <label class="opsi"><input type="radio" name="soal[<?= $i ?>][b]" value="<?= $j ?>" <?= $b === $j ? 'checked' : '' ?> title="Tandai jawaban benar">
              <span class="huruf"><?= chr(65 + $j) ?></span>
              <input name="soal[<?= $i ?>][o][<?= $j ?>]" value="<?= e($s['o'][$j] ?? '') ?>" placeholder="<?= e($labelO . ' ' . chr(65 + $j)) ?>"></label>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php return ob_get_clean();
}

$tetapJs = isset($m['opsi_tetap']) && is_array($m['opsi_tetap']) ? array_values($m['opsi_tetap']) : [];
$bentuk = $tetapJs ? 'tetap' : ($nOpsi === 1 ? 'tunggal' : 'pilihan');

page_header(($game ? 'Edit ' : 'Buat ') . $m['nama'], 'guru/');
?>
<a class="back" href="<?= e(url($game ? 'guru/game.php' : 'guru/')) ?>">Kembali</a>
<h1><?= e($m['ikon'] . ' ' . ($game ? 'Edit game' : $m['nama'])) ?></h1>
<?php if (!empty($m['petunjuk'])): ?><p class="muted"><?= e($m['petunjuk']) ?></p><?php endif; ?>
<?php if ($errors): ?><div class="flash err"><b>Periksa kembali:</b><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form method="post" class="build" id="form-game">
  <?= csrf_field() ?>
  <section class="card">
    <div class="grid2">
      <label>Judul game<input name="judul" value="<?= e($judul) ?>" maxlength="100" placeholder="contoh: Pecahan Senilai" required></label>
      <label>Kelas / Rombel
        <select name="kelas_id">
          <option value="0"><?= $mode === 'soal' ? '— Ketik manual (murid mengisi nama sendiri) —' : '— Tanpa kelas tertentu —' ?></option>
          <?php foreach ($kelasSaya as $k): ?>
            <option value="<?= $k['id'] ?>" <?= $kelasIdSelected === $k['id'] ? 'selected' : '' ?> <?= !$k['murid'] ? 'disabled' : '' ?>>
              <?= e($k['nama']) ?> (<?= count($k['murid']) ?> murid)<?= !$k['murid'] ? ' — belum ada murid' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if ($mode === 'soal'): ?>
          <small>Pilih kelas agar murid tinggal memilih namanya dari daftar (tanpa mengetik) saat bermain. Jika dibiarkan "Ketik manual", murid akan mengetik namanya sendiri. Kelola daftar di menu <a href="<?= e(url('guru/kelas.php')) ?>">Kelas & Murid</a>.</small>
        <?php else: ?>
          <small>Hanya sebagai label game ini. Kelola daftar kelas di menu <a href="<?= e(url('guru/kelas.php')) ?>">Kelas & Murid</a>.</small>
        <?php endif; ?>
      </label>
    </div>
  </section>

  <?php if ($mode === 'soal'): ?>
  <section class="card">
    <div class="sec-head"><h2><?= e($m['judul_daftar'] ?? 'Soal') ?> <span class="muted" id="jml-soal"></span></h2>
      <details class="menu"><summary class="btn small">Tempel banyak soal sekaligus</summary>
        <div class="menu-box wide-box">
          <?php if ($bentuk === 'tunggal'): ?>
          <p class="small">Satu pasangan per baris, pisahkan dengan tanda <b>=</b> atau <b>|</b>. Bisa juga salin dua kolom langsung dari Excel.</p>
          <pre class="small"><?= e($m['contoh_impor'] ?? "5 × 6 = 30\nIbu kota Jawa Barat | Bandung") ?></pre>
          <?php elseif ($bentuk === 'tetap'): ?>
          <p class="small">Satu pernyataan per baris, akhiri dengan <b>| <?= e(implode(' / ', $tetapJs)) ?></b> (cukup huruf depannya). Bisa juga salin dua kolom dari Excel.</p>
          <pre class="small"><?= e($m['contoh_impor'] ?? "Bilangan 7 adalah bilangan prima | B\n0,5 lebih kecil dari 0,25 | S") ?></pre>
          <?php else: ?>
          <p class="small">Satu soal per blok, pisahkan dengan baris kosong. Baris pertama pertanyaan, baris berikutnya pilihan. Beri tanda <b>*</b> di depan jawaban benar.</p>
          <pre class="small">Hasil 3/4 + 1/4 adalah…
*1
3/8
1/2
4/8</pre>
          <?php endif; ?>
          <textarea id="impor-teks" rows="8"></textarea>
          <button type="button" class="btn small primary" id="impor-go">Tambahkan ke daftar soal</button>
        </div>
      </details>
    </div>
    <?php if ($bentuk === 'pilihan'): ?>
    <p class="small muted">Klik bulatan di samping huruf untuk menandai jawaban benar. Maksimal <?= $nOpsi ?> pilihan, minimal <?= (int)($m['min_opsi'] ?? 2) ?>.</p>
    <?php elseif ($bentuk === 'tetap'): ?>
    <p class="small muted">Tulis pernyataan, lalu pilih jawaban yang tepat.</p>
    <?php endif; ?>
    <div id="daftar-soal" data-nopsi="<?= $nOpsi ?>" data-bentuk="<?= $bentuk ?>" data-tetap="<?= e(json_encode($tetapJs, JSON_UNESCAPED_UNICODE)) ?>">
      <?php foreach (array_values($soalList) as $i => $s) echo soal_row($i, $s, $m); ?>
    </div>
    <template id="tpl-soal"><?= soal_row('__I__', ['q' => '', 'o' => [], 'b' => 0], $m) ?></template>
    <button type="button" class="btn" id="tambah-soal">+ Tambah soal</button>
  </section>
  <?php else: ?>
  <section class="card">
    <h2>Daftar nama</h2>
    <?php if ($kelasSaya): ?>
      <label>Ambil dari kelas tersimpan
        <select id="ambil-kelas"><option value="">— pilih kelas —</option>
          <?php foreach ($kelasSaya as $i => $k): ?><option value="<?= $i ?>"><?= e($k['nama']) ?> (<?= count($k['murid']) ?> murid)</option><?php endforeach; ?>
        </select></label>
      <script>window.KELAS_SAYA = <?= json_encode($kelasSaya, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <?php else: ?>
      <p class="small muted">Tip: simpan daftar murid di menu <a href="<?= e(url('guru/kelas.php')) ?>">Kelas & murid</a> agar tidak perlu mengetik ulang.</p>
    <?php endif; ?>
    <label>Satu nama per baris <span class="muted" id="jml-nama"></span>
      <textarea name="nama" id="isi-nama" rows="12" placeholder="Andi&#10;Budi&#10;Citra"><?= e(implode("\n", $data['nama'] ?? [])) ?></textarea></label>
  </section>
  <?php endif; ?>

  <?php if (!empty($m['pengaturan'])): ?>
  <section class="card">
    <h2>Pengaturan permainan</h2>
    <div class="grid2">
    <?php foreach ($m['pengaturan'] as $f):
        $n = $f['nama']; $t = $f['tipe'] ?? 'text';
        $v = array_key_exists($n, $peng) ? $peng[$n] : ($f['default'] ?? ''); ?>
      <?php if ($t === 'checkbox'): ?>
        <label class="check"><input type="checkbox" name="p[<?= e($n) ?>]" value="1" <?= $v ? 'checked' : '' ?>> <?= e($f['label']) ?></label>
      <?php elseif ($t === 'select'): ?>
        <label><?= e($f['label']) ?><select name="p[<?= e($n) ?>]">
          <?php foreach ($f['opsi'] as $ok => $ol): ?><option value="<?= e($ok) ?>" <?= (string)$v === (string)$ok ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?>
        </select></label>
      <?php elseif ($t === 'number'): ?>
        <label><?= e($f['label']) ?><input type="number" name="p[<?= e($n) ?>]" value="<?= e($v) ?>" <?= isset($f['min']) ? 'min="' . (int)$f['min'] . '"' : '' ?> <?= isset($f['max']) ? 'max="' . (int)$f['max'] . '"' : '' ?>></label>
      <?php else: ?>
        <label><?= e($f['label']) ?><input name="p[<?= e($n) ?>]" value="<?= e($v) ?>"></label>
      <?php endif; ?>
    <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <div class="sticky-save">
    <button class="btn primary big"><?= $game ? 'Simpan & perbarui game' : 'Buat game' ?></button>
  </div>
</form>
<?php page_footer();
