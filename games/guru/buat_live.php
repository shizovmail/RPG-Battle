<?php
// Formulir pembuat game LIVE (dipanggil dari buat.php). Variabel $u, $game, $kode, $m, $all sudah tersedia.
$data = $game ? (json_decode($game['data'], true) ?: []) : [];
$judul = $game['judul'] ?? '';
$errors = [];
$kelasSaya = [];
foreach (db_rows('SELECT id,nama FROM kelas WHERE user_id=? ORDER BY nama', [$u['id']]) as $k) {
    $kelasSaya[] = ['id' => (int)$k['id'], 'nama' => $k['nama'], 'n' => (int)db_val('SELECT COUNT(*) FROM murid WHERE kelas_id=?', [$k['id']])];
}
$kelasIdSelected = !empty($data['identitas']['kelas_id']) ? (int)$data['identitas']['kelas_id'] : 0;
$jenis = $data['jenis'] ?? ['pg'];
$soalList = $data['soal'] ?? [];
$cfg = live_cfg_bersih($data['pengaturan'] ?? []);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $judul = mb_substr(trim($_POST['judul'] ?? ''), 0, 100);
    if ($judul === '') $errors[] = 'Judul game wajib diisi.';
    $kelasIdSelected = (int)($_POST['kelas_id'] ?? 0);
    $kelasRow = $kelasIdSelected ? db_row('SELECT k.nama, (SELECT COUNT(*) FROM murid m WHERE m.kelas_id=k.id) n FROM kelas k WHERE k.id=? AND k.user_id=?', [$kelasIdSelected, $u['id']]) : null;
    if ($kelasIdSelected && !$kelasRow) { $errors[] = 'Kelas yang dipilih tidak ditemukan.'; $kelasIdSelected = 0; }
    elseif ($kelasRow && (int)$kelasRow['n'] === 0) $errors[] = 'Kelas yang dipilih belum memiliki murid.';
    $kelasValid = $kelasIdSelected && $kelasRow && (int)$kelasRow['n'] > 0;
    $kelasLbl = $kelasValid ? $kelasRow['nama'] : '';

    $jenis = array_values(array_intersect((array)($_POST['jenis'] ?? []), ['pg', 'isian']));
    if (!$jenis) { $errors[] = 'Pilih minimal satu tipe soal (pilihan ganda dan/atau isian singkat).'; $jenis = ['pg']; }
    $raw = json_decode((string)($_POST['soal_json'] ?? '[]'), true);
    $soalList = live_parse_soal(is_array($raw) ? $raw : [], $jenis, $es);
    $errors = array_merge($errors, $es);
    $cfg = live_cfg_bersih($_POST['p'] ?? []);
    if ($cfg['jumlah'] > count($soalList)) $cfg['jumlah'] = 0;

    if (!$errors) {
        $new = ['mode' => 'soal', 'jenis' => $jenis, 'soal' => $soalList, 'pengaturan' => $cfg,
            'identitas' => ['cara' => $kelasValid ? 'kelas' : 'bebas', 'kelas_id' => $kelasValid ? $kelasIdSelected : null]];
        $json = json_encode($new, JSON_UNESCAPED_UNICODE);
        if ($game) {
            db_q("UPDATE games SET judul=?, kelas=?, data=?, updated_at=datetime('now','localtime') WHERE id=?", [$judul, $kelasLbl, $json, $game['id']]);
            $gid = (int)$game['id'];
        } else {
            db_q('INSERT INTO games(user_id,template,judul,slug,kelas,data,token) VALUES(?,?,?,?,?,?,?)',
                [$u['id'], $kode, $judul, new_slug($judul), $kelasLbl, $json, bin2hex(random_bytes(8))]);
            $gid = (int)db()->lastInsertId();
        }
        simpan_visibilitas($gid, $u['id']);
        try {
            generate_game($gid);
            flash($game ? 'Perubahan disimpan. Link & QR tetap sama. Sesi yang sedang berjalan tidak terpengaruh.' : 'Game live berhasil dibuat! Buka Panel wasit untuk memulai sesi.');
            redirect('guru/hasil.php?id=' . $gid);
        } catch (Exception $e) {
            $errors[] = 'Game tersimpan, tetapi file gagal dibuat: ' . $e->getMessage();
        }
    }
}

page_header(($game ? 'Edit ' : 'Buat ') . $m['nama'], 'guru/');
?>
<a class="back" href="<?= e(url($game ? 'guru/game.php' : 'guru/')) ?>">Kembali</a>
<h1><?= e($m['ikon'] . ' ' . ($game ? 'Edit game' : $m['nama'])) ?></h1>
<p class="muted"><?= e($m['petunjuk']) ?></p>
<?php if ($errors): ?><div class="flash err"><b>Periksa kembali:</b><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form method="post" class="build" id="form-live">
  <?= csrf_field() ?>
  <input type="hidden" name="soal_json" id="soal_json">
  <section class="card">
    <div class="grid2">
      <label>Judul game<input name="judul" value="<?= e($judul) ?>" maxlength="100" placeholder="contoh: Lomba Tarik Tambang Pecahan" required></label>
      <label>Kelas / Rombel
        <select name="kelas_id">
          <option value="0">— Ketik manual (murid mengisi nama sendiri) —</option>
          <?php foreach ($kelasSaya as $k): ?>
            <option value="<?= $k['id'] ?>" <?= $kelasIdSelected === $k['id'] ? 'selected' : '' ?> <?= !$k['n'] ? 'disabled' : '' ?>><?= e($k['nama']) ?> (<?= $k['n'] ?> murid)<?= !$k['n'] ? ' — belum ada murid' : '' ?></option>
          <?php endforeach; ?>
        </select>
        <small>Pilih kelas agar murid tinggal memilih namanya dari daftar. Kelola di menu <a href="<?= e(url('guru/kelas.php')) ?>">Kelas & Murid</a>.</small>
      </label>
    </div>
  </section>

  <?php bank_vis_card($game); ?>

  <section class="card">
    <h2>1. Tipe soal</h2>
    <p class="small muted">Centang satu tipe, atau keduanya untuk soal campuran (nanti tiap soal bisa dipilih tipenya).</p>
    <div class="actions">
      <label class="check"><input type="checkbox" name="jenis[]" value="pg" id="j-pg" <?= in_array('pg', $jenis, true) ? 'checked' : '' ?>> Pilihan ganda (2–4 pilihan)</label>
      <label class="check"><input type="checkbox" name="jenis[]" value="isian" id="j-isian" <?= in_array('isian', $jenis, true) ? 'checked' : '' ?>> Isian singkat</label>
    </div>
  </section>

  <section class="card">
    <?php bank_panel($kode); ?>
    <div class="sec-head"><h2>2. Bank soal <span class="muted" id="jml-soal"></span></h2>
      <details class="menu"><summary class="btn small">Tempel banyak soal sekaligus</summary>
        <div class="menu-box wide-box">
          <p class="small"><b>Pilihan ganda</b>: satu blok per soal (pisahkan dengan baris kosong). Baris pertama pertanyaan, baris berikutnya pilihan, beri <b>*</b> di depan jawaban benar.</p>
          <pre class="small">Hasil 3/4 + 1/4 adalah…
*1
3/8
1/2</pre>
          <p class="small"><b>Isian singkat</b>: satu baris per soal, pisahkan dengan <b>|</b>. Beberapa jawaban yang diterima dipisah <b>|</b> juga.</p>
          <pre class="small">Bangun datar bersisi tiga | segitiga
Bilangan prima terkecil | 2 | dua</pre>
          <textarea id="impor-teks" rows="8"></textarea>
          <button type="button" class="btn small primary" id="impor-go">Tambahkan ke bank soal</button>
        </div>
      </details>
    </div>
    <p class="small muted">Isian singkat dinilai otomatis tanpa memperhatikan huruf besar/kecil, spasi, dan tanda baca (0,5 dianggap sama dengan 0.5). Tambahkan variasi jawaban bila perlu.</p>
    <div id="live-soal"></div>
    <button type="button" class="btn" id="tambah-soal">+ Tambah soal</button>
  </section>

  <section class="card">
    <h2>3. Pengaturan bawaan</h2>
    <p class="small muted">Ini nilai awal. Semuanya masih bisa diubah di Panel wasit sebelum pertandingan dimulai.</p>
    <div class="grid2">
      <label>Waktu menjawab per soal (detik, 0 = manual)<input type="number" name="p[waktu]" min="0" max="300" value="<?= (int)$cfg['waktu'] ?>">
        <small>Jika diisi, semua murid otomatis pindah ke soal berikutnya. Jika 0, guru yang menekan tombol lanjut.</small></label>
      <label>Selisih jawaban benar untuk menang<input type="number" name="p[selisih]" min="1" max="30" value="<?= (int)$cfg['selisih'] ?>">
        <small>Makin kecil, tali makin cepat menuju tiang.</small></label>
      <label>Jumlah soal per pertandingan (0 = semua)<input type="number" name="p[jumlah]" min="0" value="<?= (int)$cfg['jumlah'] ?>">
        <small>Kalau bank soal 20 dan diisi 10, setiap sesi memakai 10 soal.</small></label>
      <label>Durasi layar hasil tiap tarikan (detik)<input type="number" name="p[durasi_hasil]" min="2" max="20" value="<?= (int)$cfg['durasi_hasil'] ?>"></label>
      <label>Pengulangan soal jika selisih belum tercapai (kali)<input type="number" name="p[ulang]" min="0" max="10" value="<?= (int)$cfg['ulang'] ?>">
        <small>Jika soal habis dan belum ada tim yang mencapai selisih, soal diulang sebanyak ini (mengikuti cara soal muncul yang dipilih; mode acak diacak lagi). Sesudahnya, pemenang = sisi tempat tali lebih jauh bergeser. Jika tali tepat di tengah, ada 1 tarikan penentu. 0 = tidak mengulang.</small></label>
    </div>
    <label>Cara soal muncul di HP murid
      <select name="p[mode]">
        <?php foreach (live_mode_opsi() as $k => $v): ?><option value="<?= e($k) ?>" <?= $cfg['mode'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></label>
    <label class="check"><input type="checkbox" name="p[acak_opsi]" value="1" <?= $cfg['acak_opsi'] ? 'checked' : '' ?>> Acak posisi pilihan jawaban (pilihan ganda)</label>
    <label class="check"><input type="checkbox" name="p[hindari_ulang]" value="1" <?= $cfg['hindari_ulang'] ? 'checked' : '' ?>> Sesi baru mengutamakan soal yang belum dipakai di sesi sebelumnya</label>
    <label class="check"><input type="checkbox" name="p[peringkat]" value="1" <?= $cfg['peringkat'] ? 'checked' : '' ?>> Tampilkan peringkat murid di akhir (matikan agar tim tidak saling menyalahkan)</label>
  </section>

  <div class="sticky-save"><button class="btn primary big"><?= $game ? 'Simpan & perbarui game' : 'Buat game' ?></button></div>
</form>

<style>
  .lsoal{border:1.5px solid var(--line);border-radius:14px;padding:14px;margin-bottom:12px;background:#fbfcff}
  .lsoal .lhead{display:flex;align-items:center;gap:10px;margin-bottom:8px}
  .lsoal .lhead b{font-family:var(--head);flex:1}
  .lsoal .lhead select{width:auto;margin:0}
  .lsoal .lopsi{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px}
  .lsoal .lop{display:flex;align-items:center;gap:8px;border:1.5px solid var(--line);border-radius:10px;padding:4px 8px;background:#fff}
  .lsoal .lop input[type=text]{margin:0;border:0;padding:6px 4px}
  .lsoal .lop .hrf{font-weight:800;color:var(--blue)}
  @media(max-width:700px){.lsoal .lopsi{grid-template-columns:1fr}}
</style>
<script>
(function(){
  var SOAL = <?= json_encode(array_values($soalList) ?: [['t' => $jenis[0] ?? 'pg', 'q' => '', 'o' => [], 'b' => 0]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var list = document.getElementById('live-soal');
  var cbPg = document.getElementById('j-pg'), cbIs = document.getElementById('j-isian');
  var uid = 0;

  function jenisAktif(){ var a=[]; if(cbPg.checked)a.push('pg'); if(cbIs.checked)a.push('isian'); return a; }
  function tipeBawaan(){ return jenisAktif()[0] || 'pg'; }
  function normalisasi(){
    var ja = jenisAktif();
    SOAL.forEach(function(s){ if (ja.indexOf(s.t) < 0) s.t = ja[0] || 'pg'; });
  }
  function buatBaris(s, i){
    var el = document.createElement('div'); el.className = 'lsoal';
    var ja = jenisAktif(), name = 'r' + (uid++);
    var head = '<div class="lhead"><b>Soal ' + (i+1) + '</b>';
    if (ja.length > 1) head += '<select data-t><option value="pg"' + (s.t==='pg'?' selected':'') + '>Pilihan ganda</option><option value="isian"' + (s.t==='isian'?' selected':'') + '>Isian singkat</option></select>';
    head += '<button type="button" class="btn small ghost" data-del>Hapus</button></div>';
    var body = '<textarea rows="2" data-q placeholder="Tulis pertanyaan…"></textarea>';
    if (s.t === 'pg') {
      body += '<div class="lopsi">';
      for (var j=0;j<4;j++) body += '<label class="lop"><input type="radio" name="' + name + '" value="' + j + '" title="Tandai jawaban benar"><span class="hrf">' + 'ABCD'.charAt(j) + '</span><input type="text" data-o="' + j + '" placeholder="Pilihan ' + 'ABCD'.charAt(j) + '"></label>';
      body += '</div>';
    } else {
      body += '<label style="margin:10px 0 0">Jawaban benar <small>Beberapa jawaban yang diterima dipisah dengan tanda |</small><input type="text" data-j placeholder="contoh: segitiga | tiga sisi"></label>';
    }
    el.innerHTML = head + body;
    el.querySelector('[data-q]').value = s.q || '';
    el.querySelector('[data-q]').oninput = function(){ s.q = this.value; };
    if (s.t === 'pg') {
      s.o = s.o || [];
      el.querySelectorAll('[data-o]').forEach(function(inp){
        var k = +inp.dataset.o; inp.value = s.o[k] || '';
        inp.oninput = function(){ s.o[k] = this.value; };
      });
      var rs = el.querySelectorAll('input[type=radio]');
      var b = +s.b || 0; if (rs[b]) rs[b].checked = true;
      rs.forEach(function(r){ r.onchange = function(){ s.b = +this.value; }; });
    } else {
      var ji = el.querySelector('[data-j]');
      ji.value = Array.isArray(s.j) ? s.j.join(' | ') : (s.j || '');
      ji.oninput = function(){ s.j = this.value; };
    }
    var sel = el.querySelector('[data-t]');
    if (sel) sel.onchange = function(){ s.t = this.value; render(); };
    el.querySelector('[data-del]').onclick = function(){
      if (SOAL.length <= 1) { alert('Minimal harus ada satu soal.'); return; }
      SOAL.splice(i,1); render();
    };
    return el;
  }
  function render(){
    normalisasi();
    list.innerHTML = '';
    SOAL.forEach(function(s,i){ list.appendChild(buatBaris(s,i)); });
    document.getElementById('jml-soal').textContent = '(' + SOAL.length + ')';
  }
  function tambah(s){ SOAL.push(s); }
  [cbPg, cbIs].forEach(function(cb){
    cb.onchange = function(){
      if (!cbPg.checked && !cbIs.checked) { cb.checked = true; alert('Pilih minimal satu tipe soal.'); return; }
      render();
    };
  });
  document.getElementById('tambah-soal').onclick = function(){
    tambah({t: tipeBawaan(), q: '', o: [], b: 0, j: ''}); render();
    var t = list.querySelectorAll('[data-q]'); t[t.length-1].focus();
  };
  document.getElementById('impor-go').onclick = function(){
    var teks = document.getElementById('impor-teks').value.trim(), n = 0, ditolak = 0;
    var kosong = SOAL.length === 1 && !(SOAL[0].q||'').trim();
    teks.split(/\n\s*\n/).forEach(function(blok){
      var baris = blok.split('\n').map(function(x){return x.trim();}).filter(Boolean);
      if (!baris.length) return;
      if (baris.length === 1) {
        var b = baris[0], parts = b.indexOf('\t')>=0 ? b.split('\t') : (b.indexOf('|')>=0 ? b.split('|') : (b.indexOf('=')>=0 ? [b.slice(0,b.lastIndexOf('=')), b.slice(b.lastIndexOf('=')+1)] : null));
        if (!parts || parts.length < 2) return;
        if (!cbIs.checked) { ditolak++; return; }
        var q = parts.shift().trim(), j = parts.map(function(x){return x.trim();}).filter(Boolean);
        if (q && j.length) { tambah({t:'isian', q:q, j:j, o:[], b:0}); n++; }
      } else {
        if (!cbPg.checked) { ditolak++; return; }
        var q2 = baris.shift(), o = [], benar = 0;
        baris.slice(0,4).forEach(function(t,i){ if (t.charAt(0)==='*'){ benar=i; t=t.slice(1).trim(); } o.push(t.replace(/^[A-Da-d][.)]\s+/,'')); });
        if (o.length >= 2) { tambah({t:'pg', q:q2, o:o, b:benar, j:''}); n++; }
      }
    });
    if (n && kosong) SOAL.shift();
    render();
    alert((n ? n + ' soal ditambahkan.' : 'Tidak ada soal yang terbaca. Periksa formatnya.') + (ditolak ? ' ' + ditolak + ' blok dilewati karena tipe soalnya belum dicentang.' : ''));
    if (n) { document.getElementById('impor-teks').value = ''; this.closest('details').removeAttribute('open'); }
  };
  // dipakai jendela "Ambil dari Bank Soal": soal sudah disesuaikan dengan jenis game ini oleh server
  window.RGBankAdd = function(items){
    var kosong = SOAL.length === 1 && !(SOAL[0].q || '').trim();
    items.forEach(function(it){
      if (it.t === 'isian') cbIs.checked = true; else cbPg.checked = true;
      tambah({t: it.t, q: it.q, o: it.o || [], b: it.b || 0, j: it.j || ''});
    });
    if (kosong) SOAL.shift();
    render();
    return items.length;
  };
  document.getElementById('form-live').addEventListener('submit', function(){
    normalisasi();
    document.getElementById('soal_json').value = JSON.stringify(SOAL);
  });
  render();
})();
</script>
<?php page_footer();
