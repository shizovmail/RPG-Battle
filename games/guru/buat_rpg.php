<?php
// Formulir pembuat game RPG BATTLE (dipanggil dari buat.php). Variabel $u, $game, $kode, $m, $all sudah tersedia.
require_once __DIR__ . '/../inc/rpg.php';
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
$cfg = rpg_cfg_bersih($data['pengaturan'] ?? []);
$modeSoal = ($data['mode_soal'] ?? 'biasa') === 'kategori' ? 'kategori' : 'biasa';
$peranInfo = rpg_peran_info();
$bawaan = rpg_stat_bawaan();

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
    $modeSoal = ($_POST['mode_soal'] ?? 'biasa') === 'kategori' ? 'kategori' : 'biasa';
    $soalList = rpg_parse_soal(is_array($raw) ? $raw : [], $jenis, $es, $modeSoal);
    $errors = array_merge($errors, $es);
    $cfg = rpg_cfg_bersih($_POST['p'] ?? []);

    if (!$errors) {
        $new = ['mode' => 'soal', 'jenis' => $jenis, 'mode_soal' => $modeSoal, 'soal' => $soalList, 'pengaturan' => $cfg,
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
        try {
            generate_game($gid);
            flash($game ? 'Perubahan disimpan. Link & QR tetap sama. Sesi yang sedang berjalan tidak terpengaruh.' : 'Game RPG Battle berhasil dibuat! Buka Panel wasit untuk mengatur pemain dan memulai pertandingan.');
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

<form method="post" class="build" id="form-rpg">
  <?= csrf_field() ?>
  <input type="hidden" name="soal_json" id="soal_json">
  <section class="card">
    <div class="grid2">
      <label>Judul game<input name="judul" value="<?= e($judul) ?>" maxlength="100" placeholder="contoh: RPG Battle Sistem Pencernaan" required></label>
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

  <section class="card">
    <h2>1. Tipe soal</h2>
    <p class="small muted">Centang satu tipe, atau keduanya untuk soal campuran (nanti tiap soal bisa dipilih tipenya).</p>
    <div class="actions">
      <label class="check"><input type="checkbox" name="jenis[]" value="pg" id="j-pg" <?= in_array('pg', $jenis, true) ? 'checked' : '' ?>> Pilihan ganda (2–4 pilihan)</label>
      <label class="check"><input type="checkbox" name="jenis[]" value="isian" id="j-isian" <?= in_array('isian', $jenis, true) ? 'checked' : '' ?>> Isian singkat</label>
    </div>
  </section>

  <section class="card">
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
          <p class="small">Soal yang ditempel memakai durasi awal di bawah. Durasi tiap soal bisa diubah setelahnya.</p>
          <label class="small">Durasi awal soal yang ditempel (detik)<input type="number" id="impor-w" min="5" max="180" value="15"></label>
          <textarea id="impor-teks" rows="8"></textarea>
          <button type="button" class="btn small primary" id="impor-go">Tambahkan ke bank soal</button>
        </div>
      </details>
    </div>
    <div class="actions" style="margin-bottom:8px">
      <label class="check"><input type="radio" name="mode_soal" value="biasa" id="m-biasa" <?= $modeSoal === 'biasa' ? 'checked' : '' ?>> <b>Model biasa</b> — satu bank soal untuk semua peran</label>
      <label class="check"><input type="radio" name="mode_soal" value="kategori" id="m-kat" <?= $modeSoal === 'kategori' ? 'checked' : '' ?>> <b>Model kategori peran</b> — bank soal terpisah untuk Tank, Assassin, Mage, dan Healer</label>
    </div>
    <div class="actions" id="tabs" style="display:none;margin-bottom:8px"></div>
    <p class="small muted" id="info-kat" style="display:none">Di model ini tiap peran hanya mendapat soal dari kategorinya (soal kategori yang sama muncul untuk peran yang sama di kedua tim). Tiap kategori minimal 4 soal; jumlah giliran maksimum mengikuti kategori yang soalnya paling sedikit. Kategori <b>Fighter</b> (bila dipakai nanti) akan bergiliran memakai keempat kategori ini.</p>
    <p class="small muted" id="info-biasa">Setiap giliran hanya muncul <b>4 soal yang sama untuk kedua tim</b>; soalnya diacak ke tiap anggota tim. Seorang pemain <b>tidak akan menerima soal yang sama lagi</b> sampai semua soal habis (lalu diulang sesuai pengaturan). Jumlah giliran maksimum = jumlah soal × (pengulangan + 1), jadi <b>20–40 soal</b> sudah cukup untuk pertandingan yang seru.
      Isian singkat dinilai otomatis tanpa memperhatikan huruf besar/kecil, spasi, dan tanda baca (0,5 dianggap sama dengan 0.5).</p>
    <div class="qbox" id="perkiraan"></div>
    <div id="rpg-soal"></div>
    <button type="button" class="btn" id="tambah-soal">+ Tambah soal</button>
  </section>

  <section class="card">
    <h2>3. Waktu & jalannya pertandingan</h2>
    <p class="small muted">Ini nilai awal. Semuanya masih bisa diubah di Panel wasit sebelum pertandingan dimulai.</p>
    <div class="grid2">
      <label>Waktu memilih target &amp; skill (detik)<input type="number" name="p[waktu_pilih]" min="5" max="60" value="<?= (int)$cfg['waktu_pilih'] ?>">
        <small>Jika habis dan murid belum memilih, skill dan targetnya diacak otomatis.</small></label>
      <label>Durasi menjawab soal
        <select name="p[sumber_waktu]" id="sumber-waktu">
          <option value="soal" <?= $cfg['sumber_waktu'] === 'soal' ? 'selected' : '' ?>>Pakai durasi bawaan tiap soal</option>
          <option value="universal" <?= $cfg['sumber_waktu'] === 'universal' ? 'selected' : '' ?>>Pakai durasi universal (sama untuk semua soal)</option>
        </select>
        <small>Guru bebas memilih. Durasi per soal diisi pada bank soal di atas (bawaan 15 detik).</small></label>
      <label>Durasi universal (detik)<input type="number" name="p[waktu_universal]" min="5" max="180" value="<?= (int)$cfg['waktu_universal'] ?>">
        <small>Dipakai hanya jika memilih "durasi universal".</small></label>
      <label>Pengulangan soal jika soal habis (kali)<input type="number" name="p[ulang]" min="0" max="10" value="<?= (int)$cfg['ulang'] ?>">
        <small>Jika soal habis dan belum ada tim yang tumbang, soal diacak dan diulang sebanyak ini. Setelah itu pemenang = tim dengan karakter hidup lebih banyak; jika sama, total HP lebih besar.</small></label>
    </div>
    <input type="hidden" name="p[lanjut_otomatis]" value="0"><label class="check"><input type="checkbox" name="p[lanjut_otomatis]" value="1" <?= $cfg['lanjut_otomatis'] ? 'checked' : '' ?>> Lanjut otomatis ke giliran berikutnya setelah animasi hasil (jika dimatikan, guru menekan tombol lanjut)</label>
    <input type="hidden" name="p[acak_opsi]" value="0"><label class="check"><input type="checkbox" name="p[acak_opsi]" value="1" <?= $cfg['acak_opsi'] ? 'checked' : '' ?>> Acak posisi pilihan jawaban (pilihan ganda)</label>
    <input type="hidden" name="p[peringkat]" value="0"><label class="check"><input type="checkbox" name="p[peringkat]" value="1" <?= $cfg['peringkat'] ? 'checked' : '' ?>> Tampilkan peringkat murid di akhir</label>
  </section>

  <section class="card">
    <div class="sec-head"><h2>4. Stat tiap peran</h2><button type="button" class="btn small" id="stat-reset">Kembalikan stat bawaan</button></div>
    <p class="small muted">HP = darah, Attack = kekuatan serang, Defend = pertahanan, Heal = jumlah pemulihan (hanya dipakai Healer). Damage = <b>(Attack × pengali skill) − Defend lawan</b> (minimal 1).
      Nilai bawaan sudah diseimbangkan lewat simulasi ribuan pertandingan: Assassin damage terbesar tetapi paling rapuh, Mage menyerang area, Healer dan Tank hampir tidak melukai tetapi menopang tim. Selisih Attack − Defend dijaga selalu positif.</p>
    <div style="overflow:auto"><table class="stat-tabel">
      <tr><th>Peran</th><th>HP</th><th>Attack</th><th>Defend</th><th>Heal</th></tr>
      <?php foreach (rpg_peran_list() as $p): ?>
      <tr><td><b><?= e($peranInfo[$p]['ikon'] . ' ' . $peranInfo[$p]['nama']) ?></b></td>
        <?php foreach (['hp' => [20, 5000], 'atk' => [1, 500], 'def' => [0, 300], 'heal' => [0, 1000]] as $k => $b): ?>
          <td><?php if ($k === 'heal' && $p !== 'healer'): ?><span class="muted">—</span><input type="hidden" name="p[stat][<?= $p ?>][heal]" value="0">
          <?php else: ?><input type="number" data-bawaan="<?= (int)$bawaan[$p][$k] ?>" name="p[stat][<?= $p ?>][<?= $k ?>]" min="<?= $b[0] ?>" max="<?= $b[1] ?>" value="<?= (int)$cfg['stat'][$p][$k] ?>"><?php endif; ?></td>
        <?php endforeach; ?></tr>
      <?php endforeach; ?>
    </table></div>
    <details style="margin-top:12px"><summary class="small"><b>Daftar skill & pengali damage</b></summary>
      <ul class="small">
        <li><b>Semua peran</b> – Serangan Dasar: 50% Attack ke 1 lawan, tanpa cooldown.</li>
        <li><b>Tank</b> – Pasang Badan (pindah ke depan 1 anggota; anggota itu 0 damage, tank menerima 10% damage tersebut; tanpa cooldown); Benteng Tim (semua anggota, damage masuk 20%, cooldown 2).</li>
        <li><b>Healer</b> – Penyembuhan (1 anggota = Heal penuh, tanpa cooldown); Hujan Cahaya (semua anggota = 55% Heal, cooldown 3).</li>
        <li><b>Assassin</b> – Tusukan Mematikan (1 lawan, 100% Attack, cooldown 2); Bayangan (tak bisa diserang giliran itu, lalu giliran berikutnya Serangan Bayangan 275% Attack jika benar lagi, cooldown 3).</li>
        <li><b>Mage</b> – Hujan Meteor / Badai Es (semua lawan, 100% Attack, cooldown 3); Kutukan (1 lawan, giliran berikutnya 75% gagal walau benar, tersembunyi dari lawan, cooldown 2).</li>
        <li>Skill yang gagal (jawaban salah / waktu habis) tetap memakai cooldown. Tank &amp; Healer memilih sekutu untuk skill utamanya; Assassin &amp; Mage memilih lawan.</li>
      </ul></details>
  </section>

  <div class="sticky-save"><button class="btn primary big"><?= $game ? 'Simpan & perbarui game' : 'Buat game' ?></button></div>
</form>

<style>
  .lsoal{border:1.5px solid var(--line);border-radius:14px;padding:14px;margin-bottom:12px;background:#fbfcff}
  .lsoal .lhead{display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap}
  .lsoal .lhead b{font-family:var(--head);flex:1}
  .lsoal .lhead select{width:auto;margin:0}
  .lsoal .lhead .lw{display:flex;align-items:center;gap:6px;font-size:.85rem;font-weight:700;margin:0}
  .lsoal .lhead .lw input{width:74px;margin:0;padding:4px 8px}
  .lsoal .lopsi{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px}
  .lsoal .lop{display:flex;align-items:center;gap:8px;border:1.5px solid var(--line);border-radius:10px;padding:4px 8px;background:#fff}
  .lsoal .lop input[type=text]{margin:0;border:0;padding:6px 4px}
  .lsoal .lop .hrf{font-weight:800;color:var(--blue)}
  .qbox{background:#f7f9ff;border:1.5px dashed #9db3e8;border-radius:12px;padding:10px 14px;margin:10px 0;font-weight:700}
  .stat-tabel{border-collapse:collapse;width:100%;max-width:640px}
  .stat-tabel td,.stat-tabel th{border-bottom:1px solid var(--line);padding:6px 8px;text-align:center}
  .stat-tabel input{width:90px;margin:0;text-align:center}
  @media(max-width:700px){.lsoal .lopsi{grid-template-columns:1fr}}
</style>
<script>
(function(){
  var AWAL = <?= json_encode(array_values($soalList), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var PERAN = {tank:'🛡️ Tank', assassin:'🗡️ Assassin', mage:'🔮 Mage', healer:'✨ Healer'}, ORDER = ['tank','assassin','mage','healer'];
  var MODE = <?= json_encode($modeSoal) ?>, KATAKTIF = 'tank';
  var KOSONG = function(){ return {t: <?= json_encode($jenis[0] ?? 'pg') ?>, q: '', o: [], b: 0, w: 15}; };
  var SOAL = [], KAT = {tank:[], assassin:[], mage:[], healer:[]};
  if (MODE === 'kategori') AWAL.forEach(function(x){ (KAT[x.k] || KAT.tank).push(x); });
  else SOAL = AWAL;
  if (!SOAL.length) SOAL = [KOSONG()];
  ORDER.forEach(function(r){ if (!KAT[r].length) { var k = KOSONG(); KAT[r] = [k]; } });
  function ARR(){ return MODE === 'kategori' ? KAT[KATAKTIF] : SOAL; }
  function SEMUA(){ var a = SOAL.slice(); ORDER.forEach(function(r){ a = a.concat(KAT[r]); }); return a; }
  var list = document.getElementById('rpg-soal');
  var cbPg = document.getElementById('j-pg'), cbIs = document.getElementById('j-isian');
  var uid = 0;

  function jenisAktif(){ var a=[]; if(cbPg.checked)a.push('pg'); if(cbIs.checked)a.push('isian'); return a; }
  function tipeBawaan(){ return jenisAktif()[0] || 'pg'; }
  function normalisasi(){
    var ja = jenisAktif();
    SEMUA().forEach(function(s){ if (ja.indexOf(s.t) < 0) s.t = ja[0] || 'pg'; if (!s.w) s.w = 15; });
  }
  function hitung(arr){ return arr.filter(function(s){ return (s.q||'').trim(); }).length; }
  function perkiraan(){
    var ul = +document.querySelector('[name="p[ulang]"]').value || 0, h, maks, kurang;
    if (MODE === 'kategori') {
      var c = ORDER.map(function(r){ return hitung(KAT[r]); }), n = Math.min.apply(null, c); maks = n * (ul + 1);
      h = 'Soal per kategori: ' + ORDER.map(function(r, i){ return PERAN[r] + ' <b>' + c[i] + '</b>'; }).join(' · ') + ' ⇒ pertandingan paling lama <b>' + maks + ' giliran</b> (dibatasi kategori tersedikit, ' + n + ' soal).';
      kurang = n < 4 ? ' <span style="color:#d64545">Tiap kategori minimal 4 soal.</span>' : (n < 12 ? ' <span style="color:#b98410">Disarankan minimal 12–15 soal per kategori.</span>' : '');
    } else {
      var n2 = hitung(SOAL); maks = n2 * (ul + 1);
      h = 'Bank soal: <b>' + n2 + '</b> soal · dengan ' + ul + ' kali pengulangan ⇒ pertandingan paling lama <b>' + maks + ' giliran</b> (tiap pemain mendapat ' + n2 + ' soal berbeda per putaran).';
      kurang = n2 < 8 ? ' <span style="color:#d64545">Minimal 8 soal.</span>' : (n2 < 16 ? ' <span style="color:#b98410">Disarankan minimal 16–20 soal agar pertarungan cukup panjang.</span>' : '');
    }
    document.getElementById('perkiraan').innerHTML = h + kurang;
  }
  function tabs(){
    var el = document.getElementById('tabs');
    el.style.display = MODE === 'kategori' ? 'flex' : 'none';
    document.getElementById('info-kat').style.display = MODE === 'kategori' ? 'block' : 'none';
    document.getElementById('info-biasa').style.display = MODE === 'kategori' ? 'none' : 'block';
    el.innerHTML = '';
    ORDER.forEach(function(r){
      var b = document.createElement('button'); b.type = 'button';
      b.className = 'btn small' + (KATAKTIF === r ? ' primary' : '');
      b.textContent = PERAN[r] + ' (' + hitung(KAT[r]) + ')';
      b.onclick = function(){ KATAKTIF = r; render(); };
      el.appendChild(b);
    });
  }
  function buatBaris(s, i){
    var el = document.createElement('div'); el.className = 'lsoal';
    var ja = jenisAktif(), name = 'r' + (uid++);
    var head = '<div class="lhead"><b>Soal ' + (i+1) + '</b>';
    head += '<label class="lw">⏱ Durasi <input type="number" data-w min="5" max="180"> detik</label>';
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
    var w = el.querySelector('[data-w]'); w.value = s.w || 15; w.oninput = function(){ s.w = +this.value || 15; };
    el.querySelector('[data-q]').value = s.q || '';
    el.querySelector('[data-q]').oninput = function(){ s.q = this.value; perkiraan(); };
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
      if (ARR().length <= 1) { alert('Minimal harus ada satu soal.'); return; }
      ARR().splice(i,1); render();
    };
    return el;
  }
  function render(){
    normalisasi();
    list.innerHTML = '';
    tabs();
    ARR().forEach(function(s,i){ list.appendChild(buatBaris(s,i)); });
    document.getElementById('jml-soal').textContent = MODE === 'kategori' ? '— ' + PERAN[KATAKTIF] + ' (' + ARR().length + ')' : '(' + ARR().length + ')';
    perkiraan();
  }
  function tambah(s){ ARR().push(s); }
  [cbPg, cbIs].forEach(function(cb){
    cb.onchange = function(){
      if (!cbPg.checked && !cbIs.checked) { cb.checked = true; alert('Pilih minimal satu tipe soal.'); return; }
      render();
    };
  });
  document.querySelector('[name="p[ulang]"]').oninput = perkiraan;
  document.getElementById('tambah-soal').onclick = function(){
    tambah({t: tipeBawaan(), q: '', o: [], b: 0, j: '', w: 15}); render();
    var t = list.querySelectorAll('[data-q]'); t[t.length-1].focus();
  };
  document.getElementById('impor-go').onclick = function(){
    var teks = document.getElementById('impor-teks').value.trim(), n = 0, ditolak = 0;
    var w0 = Math.max(5, Math.min(180, +document.getElementById('impor-w').value || 15));
    var kosong = ARR().length === 1 && !(ARR()[0].q||'').trim();
    teks.split(/\n\s*\n/).forEach(function(blok){
      var baris = blok.split('\n').map(function(x){return x.trim();}).filter(Boolean);
      if (!baris.length) return;
      if (baris.length === 1) {
        var b = baris[0], parts = b.indexOf('\t')>=0 ? b.split('\t') : (b.indexOf('|')>=0 ? b.split('|') : (b.indexOf('=')>=0 ? [b.slice(0,b.lastIndexOf('=')), b.slice(b.lastIndexOf('=')+1)] : null));
        if (!parts || parts.length < 2) return;
        if (!cbIs.checked) { ditolak++; return; }
        var q = parts.shift().trim(), j = parts.map(function(x){return x.trim();}).filter(Boolean);
        if (q && j.length) { tambah({t:'isian', q:q, j:j, o:[], b:0, w:w0}); n++; }
      } else {
        if (!cbPg.checked) { ditolak++; return; }
        var q2 = baris.shift(), o = [], benar = 0;
        baris.slice(0,4).forEach(function(t,i){ if (t.charAt(0)==='*'){ benar=i; t=t.slice(1).trim(); } o.push(t.replace(/^[A-Da-d][.)]\s+/,'')); });
        if (o.length >= 2) { tambah({t:'pg', q:q2, o:o, b:benar, j:'', w:w0}); n++; }
      }
    });
    if (n && kosong) ARR().shift();
    render();
    alert((n ? n + ' soal ditambahkan.' : 'Tidak ada soal yang terbaca. Periksa formatnya.') + (ditolak ? ' ' + ditolak + ' blok dilewati karena tipe soalnya belum dicentang.' : ''));
    if (n) { document.getElementById('impor-teks').value = ''; this.closest('details').removeAttribute('open'); }
  };
  document.getElementById('stat-reset').onclick = function(){
    document.querySelectorAll('[data-bawaan]').forEach(function(i){ i.value = i.dataset.bawaan; });
  };
  document.getElementById('form-rpg').addEventListener('submit', function(){
    normalisasi();
    var out = [];
    if (MODE === 'kategori') ORDER.forEach(function(r){ KAT[r].forEach(function(x){ x.k = r; out.push(x); }); });
    else out = SOAL;
    document.getElementById('soal_json').value = JSON.stringify(out);
  });
  [document.getElementById('m-biasa'), document.getElementById('m-kat')].forEach(function(r){
    r.onchange = function(){ MODE = document.getElementById('m-kat').checked ? 'kategori' : 'biasa'; render(); };
  });
  render();
})();
</script>
<?php page_footer();
