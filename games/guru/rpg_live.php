<?php
require __DIR__ . '/../inc/boot.php';
require_once __DIR__ . '/../inc/rpg.php';
$u = require_login('guru');
$g = db_row('SELECT * FROM games WHERE id=? AND user_id=?', [(int)($_GET['id'] ?? 0), $u['id']]);
if (!$g) { flash('Game tidak ditemukan.', 'err'); redirect('guru/game.php'); }
if ($g['template'] !== 'rpg_battle') { redirect('guru/live.php?id=' . $g['id']); }
$link = game_link($u['username'], $g['slug']);
$bank = count((json_decode((string)$g['data'], true) ?: [])['soal'] ?? []);
page_header('Panel wasit', 'guru/game.php');
?>
<style>
  .lv-top{display:flex;gap:12px;align-items:center;flex-wrap:wrap;justify-content:space-between;margin-bottom:14px}
  .lv-top h1{margin:0}
  .cols{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  @media(max-width:900px){.cols{grid-template-columns:1fr}}
  .kol{border-radius:16px;border:2px solid var(--line);background:#fff;padding:12px;min-height:120px}
  .kol h3{margin:0 0 8px;display:flex;justify-content:space-between;align-items:center}
  .kol.t1{border-color:#f6c400;background:#fffbea}.kol.t1 h3{color:#8a6a00}
  .kol.t2{border-color:#b9783a;background:#fbf3ea}.kol.t2 h3{color:#6b4120}
  .slot{display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:10px;background:#fff;border:1px solid var(--line);margin-bottom:6px}
  .slot .ik{font-size:1.4rem;width:34px;text-align:center}
  .slot .rl{font-weight:800;min-width:78px}
  .slot select{flex:1;margin:0;padding:5px 6px;min-width:0}
  .slot.kosong{background:#fff8e1;border-style:dashed}
  .pm{display:flex;align-items:center;gap:8px;padding:5px 8px;border-radius:10px;background:#fff;border:1px solid var(--line);margin:0 6px 6px 0;font-weight:700;font-size:.9rem}
  .pm.penonton{background:#f1f4fb}
  .pmwrap{display:flex;flex-wrap:wrap;margin-top:8px}
  .dot{width:10px;height:10px;border-radius:50%;background:#1f9d6b;display:inline-block}
  .dot.off{background:#d64545}
  .st-ok{color:#1f9d6b;font-weight:900}.st-wait{color:#b98410}.st-off{color:#d64545;font-weight:800}
  .lstat{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0}
  .lstat .box{background:#fff;border:1.5px solid var(--line);border-radius:14px;padding:10px 16px;text-align:center;min-width:120px}
  .lstat .box b{display:block;font:800 1.7rem var(--head)}
  .aksi-besar{font-size:1.1rem;padding:14px 24px}
  .glow{animation:glow 1s ease-in-out infinite;background:#1f9d6b!important;border-color:#1f9d6b!important;color:#fff!important}
  @keyframes glow{0%,100%{box-shadow:0 0 0 0 rgba(31,157,107,.6)}50%{box-shadow:0 0 0 12px rgba(31,157,107,0)}}
  .warn{background:#fff8e1;border:1.5px solid #f0d78a;border-radius:12px;padding:10px 14px;margin:10px 0}
  table.tb{border-collapse:collapse;width:100%}
  table.tb td,table.tb th{border-bottom:1px solid var(--line);padding:6px 8px;text-align:center;font-size:.9rem}
  table.tb td.l,table.tb th.l{text-align:left}
  table.tb tr.mati td{opacity:.45}
  .hpbar{height:12px;border-radius:99px;background:#e6eaf3;overflow:hidden;min-width:90px;position:relative}
  .hpbar i{display:block;height:100%;background:#2ecc71;transition:width .4s}
  .hpbar i.m{background:#f1c40f}.hpbar i.k{background:#e74c3c}
  .cfg-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 18px}
  @media(max-width:700px){.cfg-grid{grid-template-columns:1fr}}
  .stat-tabel{border-collapse:collapse;width:100%;max-width:640px}
  .stat-tabel td,.stat-tabel th{border-bottom:1px solid var(--line);padding:5px 8px;text-align:center}
  .stat-tabel input{width:84px;margin:0;text-align:center}
  .qbox{background:#f7f9ff;border:1.5px dashed #9db3e8;border-radius:12px;padding:10px 14px;margin:10px 0;font-weight:700}
  .lg{max-height:260px;overflow:auto;font-size:.88rem;line-height:1.5}
  .lg div{border-bottom:1px dotted var(--line);padding:2px 0}
  .lg .rh{font-weight:900;background:#eef1f7;border-radius:8px;padding:2px 8px;margin-top:6px}
  .mandiri{margin:6px 0 10px;padding:8px 10px;background:#eef3ff;border-radius:10px}
  .gagal{color:#b0524f}.heal{color:#12704a}.dmg{color:#a12a2a;font-weight:700}
  .chip{display:inline-block;padding:2px 9px;border-radius:999px;background:#eef1f7;font-size:.78rem;font-weight:700;margin:1px}
  .chip.k{background:#f3e5ff;color:#5b1b8a}.chip.s{background:#e3f1ff;color:#17508a}
</style>

<a class="back" href="<?= e(url('guru/game.php')) ?>">Game saya</a>
<div class="lv-top">
  <h1>⚔️ <?= e($g['judul']) ?></h1>
  <div class="actions">
    <button class="btn primary" id="btn-layar">🖥️ Buka layar proyektor</button>
    <a class="btn" href="<?= e(url('guru/buat.php?id=' . $g['id'])) ?>">Edit soal & stat</a>
    <a class="btn" target="_blank" href="<?= e(url('guru/rpg_cetak.php?id=' . $g['id'])) ?>">🖨️ Cetak petunjuk</a>
    <a class="btn" href="<?= e(url('guru/skor.php?id=' . $g['id'])) ?>">Nilai</a>
  </div>
</div>
<div class="card" style="padding:12px 16px">
  <div class="copy-row"><input id="link" value="<?= e($link) ?>" readonly><button class="btn primary" data-copy="#link">Salin link murid</button></div>
  <p class="small muted" style="margin:6px 0 0">Bank soal: <b><?= $bank ?></b> soal. Murid membuka link/QR ini di HP. Permainan ini <b>4 lawan 4</b> (atau <b>5 lawan 5</b> bila karakter Fighter dihidupkan di pengaturan): pilih murid sebagai Tank, Assassin, Mage, Healer (dan Fighter) di tiap tim; murid lain menjadi penonton (bisa menggantikan bila perlu).</p>
</div>
<div id="panel"><div class="card">Memuat…</div></div>

<p class="small muted" style="text-align:center;margin-top:18px">Dibuat oleh Subrata Pratama, S.Pd. - SMP Kartini 2 Batam</p>
<script>
(function(){
  var GAME = <?= (int)$g['id'] ?>, CSRF = <?= json_encode(csrf_token()) ?>, BANK = <?= (int)$bank ?>;
  var API = <?= json_encode(url('api_rpg.php')) ?>;
  var LAYAR = <?= json_encode(url('guru/rpg_layar.php?id=' . $g['id'])) ?>;
  var root = document.getElementById('panel');
  var ST = null, keyTampil = '', sisaBase = null, sisaT0 = 0, sibuk = false, lastHash = {}, nRiw = -1, RIW = [];
  var PERAN = {tank:['Tank','🛡️'], fighter:['Fighter','🥊'], assassin:['Assassin','🗡️'], mage:['Mage','🔮'], healer:['Healer','✨']};
  var BASEORD = ['tank','assassin','mage','healer'];                    // urutan indeks unit 0..7 (Fighter = 8 dan 9)
  var ORDER = ['tank','fighter','assassin','mage','healer'];             // urutan tampil
  var TIMN = {1:'Sky Heaven Guardians', 2:'Dark Earth Warriors'};
  function TIMOF(i){ return i < 8 ? (i < 4 ? 1 : 2) : i - 7; }
  function ROLEOF(i){ return i < 8 ? BASEORD[i % 4] : 'fighter'; }
  function UIDX(t, r){ return r === 'fighter' ? 8 + t - 1 : (t - 1) * 4 + BASEORD.indexOf(r); }
  var FASE = {pilih:'Murid memilih skill & target', soal:'Murid menjawab soal', hasil:'Animasi hasil giliran', jeda:'Dijeda'};

  function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
  function get(){ return fetch(API+'?a=gstate&game='+GAME,{cache:'no-store'}).then(function(r){return r.json();}); }
  function aksi(d, extra){
    if (sibuk) return Promise.resolve();
    sibuk = true;
    var body = Object.assign({do:d}, extra||{});
    return fetch(API+'?a=aksi&game='+GAME,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF':CSRF},body:JSON.stringify(body)})
      .then(function(r){return r.json();}).then(function(j){
        sibuk = false;
        if (j.ok === false) { alert(j.pesan || 'Gagal'); return; }
        terima(j);
      }).catch(function(){ sibuk = false; alert('Koneksi bermasalah.'); });
  }
  document.getElementById('btn-layar').onclick = function(){ window.open(LAYAR,'layar_rpg','width=1280,height=720'); };

  function loop(){
    get().then(function(j){ if (j.ok === false) { root.innerHTML = '<div class="warn">'+esc(j.pesan||'Terputus')+'</div>'; return; } terima(j); })
      .catch(function(){}).then(function(){ setTimeout(loop, 1000); });
  }
  function terima(j){
    ST = j; sisaBase = j.sisa; sisaT0 = Date.now();
    var k = j.st === 'lobi' ? 'lobi' : (j.st === 'kosong' || j.st === 'selesai') ? j.st + j.sesi : 'main';
    if (k !== keyTampil) { keyTampil = k; lastHash = {}; bangun(j); }
    perbarui(j);
    if (j.n_riw !== nRiw) {
      nRiw = j.n_riw;
      fetch(API+'?a=riwayat&game='+GAME,{cache:'no-store'}).then(function(r){return r.json();}).then(function(x){ RIW = x.riwayat || []; lastHash.riw = null; riwayat(); });
    }
  }

  // ---------- kerangka ----------
  function bangun(j){
    if (j.st === 'kosong') {
      root.innerHTML = '<div class="card"><h2>Belum ada sesi</h2><p>Buat sesi untuk membuka lobi. Setelah itu murid bisa masuk lewat link/QR.</p>'
        +'<button class="btn primary big" id="b-baru">➕ Buat sesi baru</button></div>';
      document.getElementById('b-baru').onclick = function(){ aksi('sesi_baru'); };
    } else if (j.st === 'lobi') {
      root.innerHTML = '<div class="card"><div class="sec-head"><h2>Lobi <span class="muted" id="jml"></span></h2><div class="actions"><button class="btn" id="b-acak">🎲 Acak peran</button><button class="btn danger small" id="b-batal">Batalkan sesi</button></div></div>'
        +'<div class="mandiri" id="mandiri"></div>'
        +'<div class="cols"><div class="kol t1"><h3>🟡 Sky Heaven Guardians</h3><div id="k1"></div></div><div class="kol t2"><h3>🟤 Dark Earth Warriors</h3><div id="k2"></div></div></div>'
        +'<h3 style="margin-top:12px">Murid yang masuk <span class="muted small">(✕ untuk mengeluarkan)</span></h3><div class="pmwrap" id="pm"></div>'
        +'<div id="syarat"></div><div class="actions" style="margin-top:8px"><button class="btn primary big" id="b-mulai">▶ Mulai pertandingan</button></div></div>'
        +'<div class="card"><h2>Pengaturan pertandingan</h2><div id="cfg"></div></div>';
      document.getElementById('b-acak').onclick = function(){ aksi('acak'); };
      document.getElementById('b-batal').onclick = function(){ if (confirm('Batalkan sesi ini? Murid yang sudah masuk harus masuk ulang.')) aksi('sesi_baru'); };
      document.getElementById('b-mulai').onclick = function(){ aksi('mulai'); };
      bangunCfg(j);
    } else if (j.st === 'selesai') {
      root.innerHTML = '<div class="card" id="akhir"></div><div class="card"><h2>Unit akhir</h2><div id="unit"></div></div><div class="card"><h2>Riwayat giliran</h2><div class="lg" id="riw"></div></div>';
    } else {
      root.innerHTML = '<div class="card"><div class="sec-head"><h2 id="jdl"></h2><div class="actions" id="ctl"></div></div><div class="lstat" id="stat"></div><div id="info"></div>'
        +'<div style="overflow:auto"><table class="tb"><thead><tr><th class="l">Karakter</th><th class="l">Murid</th><th>HP</th><th>Cooldown</th><th>Status giliran</th><th>Ganti pemain</th></tr></thead><tbody id="unit"></tbody></table></div></div>'
        +'<div class="card"><h2>Riwayat giliran</h2><div class="lg" id="riw"></div></div>';
    }
  }

  function bangunCfg(j){
    var c = j.cfg, st = c.stat;
    var h = '<div class="cfg-grid">'
      +'<label>Waktu memilih target & skill (detik)<input type="number" data-c="waktu_pilih" min="5" max="60" value="'+c.waktu_pilih+'"></label>'
      +'<label>Durasi menjawab soal<select data-c="sumber_waktu"><option value="soal"'+(c.sumber_waktu==='soal'?' selected':'')+'>Pakai durasi bawaan tiap soal</option><option value="universal"'+(c.sumber_waktu==='universal'?' selected':'')+'>Pakai durasi universal</option></select></label>'
      +'<label>Durasi universal (detik)<input type="number" data-c="waktu_universal" min="5" max="180" value="'+c.waktu_universal+'"></label>'
      +'<label>Jumlah soal dipakai tiap putaran (0 = semua)<input type="number" data-c="soal_per_putaran" min="0" max="500" value="'+c.soal_per_putaran+'"></label>'
      +'<label>Pengulangan soal jika soal habis (kali)<input type="number" data-c="ulang" min="0" max="10" value="'+c.ulang+'"></label></div>'
      +'<label class="check"><input type="checkbox" data-c="pakai_fighter"'+(c.pakai_fighter?' checked':'')+'> <b>Pakai karakter Fighter (5 vs 5)</b> — tiap tim mendapat satu Fighter tambahan</label>'
      +'<label class="check"><input type="checkbox" data-c="lanjut_otomatis"'+(c.lanjut_otomatis?' checked':'')+'> Lanjut otomatis ke giliran berikutnya setelah animasi hasil</label>'
      +'<label class="check"><input type="checkbox" data-c="acak_opsi"'+(c.acak_opsi?' checked':'')+'> Acak posisi pilihan jawaban</label>'
      +'<label class="check"><input type="checkbox" data-c="peringkat"'+(c.peringkat?' checked':'')+'> Tampilkan peringkat murid di akhir</label>'
      +'<h3 style="margin-top:14px">Stat tiap peran</h3><div style="overflow:auto"><table class="stat-tabel"><tr><th>Peran</th><th>HP</th><th>Attack</th><th>Defend</th></tr>';
    ORDER.forEach(function(p){
      h += '<tr><td><b>'+PERAN[p][1]+' '+PERAN[p][0]+'</b></td>';
      ['hp','atk','def'].forEach(function(k){
        h += '<td><input type="number" data-s="'+p+'.'+k+'" min="0" value="'+st[p][k]+'"></td>';
      });
      h += '</tr>';
    });
    h += '</table></div><p class="small muted">Damage = (Attack × pengali skill) − Defend lawan (minimal 1). Bank '+BANK+' soal, '+j.putaran+' soal dipakai per putaran ⇒ paling lama '+j.perkiraan+' giliran (tiap giliran satu soal per pemain, sama untuk kedua tim).</p><p class="small muted" id="cfg-ok"></p>';
    document.getElementById('cfg').innerHTML = h;
    var t = 0;
    function kirim(){
      var cfg = {stat:{}};
      document.querySelectorAll('#cfg [data-c]').forEach(function(x){ cfg[x.dataset.c] = x.type==='checkbox' ? x.checked : (x.tagName==='SELECT' ? x.value : +x.value); });
      document.querySelectorAll('#cfg [data-s]').forEach(function(x){ var a = x.dataset.s.split('.'); (cfg.stat[a[0]] = cfg.stat[a[0]] || {})[a[1]] = +x.value; });
      aksi('cfg',{cfg:cfg}).then(function(){ var o=document.getElementById('cfg-ok'); if(o){o.textContent='Pengaturan tersimpan ✓';} });
    }
    document.querySelectorAll('#cfg [data-c],#cfg [data-s]').forEach(function(el){ el.onchange = function(){ clearTimeout(t); t = setTimeout(kirim, 300); }; });
  }

  // ---------- pembaruan ----------
  function hashPer(nama, val, fn){ var h = JSON.stringify(val); if (lastHash[nama] === h) return; lastHash[nama] = h; fn(); }
  function perbarui(j){
    if (j.st === 'lobi') return lobi(j);
    if (j.st === 'selesai') return selesai(j);
    if (j.st === 'kosong') return;
    main(j);
  }

  function lobi(j){
    hashPer('lobi', [j.pemain, j.bisa_mulai, j.alasan, j.cfg.pakai_fighter, j.cfg.pilih_mandiri], function(){
      var on = !!j.cfg.pilih_mandiri;
      document.getElementById('mandiri').innerHTML = '<label class="check"><input type="checkbox" id="t-mandiri"'+(on?' checked':'')+'> <span><b>Murid memilih peran sendiri</b> '+(on?'— aktif: murid berdiskusi lalu mengambil/melepas peran di HP-nya; guru tetap bisa mengatur di bawah.':'— nonaktif: guru yang mengatur peran, murid hanya menunggu.')+'</span></label>';
      document.getElementById('t-mandiri').onchange = function(){
        var v = this.checked;
        if (!v && !confirm('Matikan pilih mandiri? Semua murid dikembalikan ke lobi dan peran yang sudah dipilih dikosongkan. Setelah itu guru mengatur peran atau memakai Acak peran.')) { this.checked = true; return; }
        aksi('mandiri',{nilai:v});
      };
      [1,2].forEach(function(t){
        var h = '';
        ORDER.forEach(function(r){
          if (r === 'fighter' && !j.cfg.pakai_fighter) return;
          var idx = UIDX(t, r), ada = j.pemain.filter(function(p){ return p.u === idx; })[0];
          h += '<div class="slot'+(ada?'':' kosong')+'"><span class="ik">'+PERAN[r][1]+'</span><span class="rl">'+PERAN[r][0]+'</span><select data-slot="'+idx+'">'
            + '<option value="">— kosong —</option>' + j.pemain.map(function(p){ return '<option value="'+p.id+'"'+(ada&&ada.id===p.id?' selected':'')+'>'+esc(p.nama)+(p.u>=0&&(!ada||ada.id!==p.id)?' (sudah di slot lain)':'')+'</option>'; }).join('')
            + '</select></div>';
        });
        document.getElementById('k'+t).innerHTML = h;
      });
      document.getElementById('pm').innerHTML = j.pemain.map(function(p){
        return '<span class="pm'+(p.u<0?' penonton':'')+'"><span class="dot'+(p.online?'':' off')+'" title="'+(p.online?'online':'terputus')+'"></span>'+esc(p.nama)+(p.u>=0?' <span class="muted small">'+PERAN[ROLEOF(p.u)][1]+' '+TIMN[TIMOF(p.u)]+'</span>':' <span class="muted small">penonton</span>')+' <button class="btn small ghost" data-out="'+p.id+'" title="Keluarkan">✕</button></span>';
      }).join('') || '<span class="muted small">Belum ada murid yang masuk.</span>';
      document.getElementById('jml').textContent = '· '+j.pemain.length+' murid masuk';
      document.getElementById('syarat').innerHTML = j.bisa_mulai ? '' : '<div class="warn">'+esc(j.alasan)+'</div>';
      var bm = document.getElementById('b-mulai'); bm.disabled = !j.bisa_mulai; bm.style.opacity = j.bisa_mulai ? 1 : .5;
      root.querySelectorAll('[data-slot]').forEach(function(s){ s.onchange = function(){
        var idx = +s.dataset.slot, tim = TIMOF(idx), peran = ROLEOF(idx);
        if (!s.value) { var cur = ST.pemain.filter(function(p){ return p.u === idx; })[0]; if (cur) aksi('atur',{p:cur.id,tim:0,peran:''}); return; }
        aksi('atur',{p:+s.value,tim:tim,peran:peran});
      }; });
      root.querySelectorAll('[data-out]').forEach(function(b){ b.onclick = function(){ if (confirm('Keluarkan murid ini dari lobi?')) aksi('keluarkan',{p:+b.dataset.out}); }; });
    });
  }

  function pct(hp, mx){ return Math.max(0, Math.min(100, mx ? hp/mx*100 : 0)); }
  function barHP(u){ var p = pct(u.hp, u.mx); return '<div class="hpbar"><i class="'+(p<25?'k':p<55?'m':'')+'" style="width:'+p+'%"></i></div><small>'+u.hp+' / '+u.mx+'</small>'; }
  function nm(j, i){ var u = j.unit[i]; return (u.nama || PERAN[ROLEOF(i)][0]) + ' (' + PERAN[ROLEOF(i)][0] + ' ' + (TIMOF(i) === 1 ? 'Sky Heaven' : 'Dark Earth') + ')'; }

  function main(j){
    var fase = j.st === 'jeda' ? j.sblm : j.st;
    document.getElementById('jdl').innerHTML = (j.st==='jeda'?'⏸ Dijeda · ':'') + 'Giliran '+j.ronde+' <span class="tag">'+esc(FASE[fase]||'')+'</span>';
    var manual = !j.cfg.lanjut_otomatis;
    hashPer('ctl', [j.st, manual, j.sblm], function(){
      var h = '';
      if (j.st === 'pilih') h += '<button class="btn primary" id="a-tp">Lewati tahap memilih (acak sisanya)</button>';
      if (j.st === 'soal') h += '<button class="btn primary" id="a-tutup">Tutup tahap menjawab sekarang</button>';
      if (j.st === 'hasil') h += '<button class="btn primary aksi-besar '+(manual?'glow':'')+'" id="a-lanjut">'+(manual?'Giliran berikutnya ▶':'Lewati animasi ▶')+'</button>';
      if (j.st !== 'jeda') h += '<button class="btn" id="a-jeda">⏸ Jeda</button>';
      if (j.st === 'jeda') h += '<button class="btn primary aksi-besar" id="a-lanjutjeda">▶ Lanjutkan</button>';
      h += '<button class="btn danger small" id="a-akhiri">Akhiri game</button>';
      document.getElementById('ctl').innerHTML = h;
      var $ = function(i){ return document.getElementById(i); };
      if ($('a-tp')) $('a-tp').onclick = function(){ aksi('tutup_pilih'); };
      if ($('a-tutup')) $('a-tutup').onclick = function(){ aksi('tutup'); };
      if ($('a-lanjut')) $('a-lanjut').onclick = function(){ aksi('lanjut'); };
      if ($('a-jeda')) $('a-jeda').onclick = function(){ aksi('jeda'); };
      if ($('a-lanjutjeda')) $('a-lanjutjeda').onclick = function(){ aksi('lanjut_jeda'); };
      $('a-akhiri').onclick = function(){ if (confirm('Akhiri game sekarang? Pemenang ditentukan dari jumlah karakter hidup (lalu total HP).')) aksi('akhiri'); };
    });
    var hid = j.unit.filter(function(u){ return u.hidup && u.ada; });
    var n = hid.length, k = hid.filter(function(u){ return u.kunci; }).length, d = hid.filter(function(u){ return u.sudah; }).length;
    document.getElementById('stat').innerHTML =
      '<div class="box"><b id="tmr">–</b>sisa waktu</div>'
      +'<div class="box"><b>'+(fase==='pilih'?k:d)+' / '+n+'</b>'+(fase==='pilih'?'sudah memilih':'sudah menjawab')+'</div>'
      +'<div class="box"><b>'+j.hidup[1]+' vs '+j.hidup[2]+'</b>karakter hidup (Sky Heaven vs Dark Earth)</div>'
      +'<div class="box"><b>'+j.soal_sisa+'</b>giliran tersisa (maks.)</div>';
    var info = '';
    if (j.soal_sisa === 0) info = '<div class="warn">Ini giliran terakhir (soal habis). Jika belum ada tim yang tumbang, pemenang ditentukan dari jumlah karakter hidup, lalu total HP.</div>';
    document.getElementById('info').innerHTML = info;
    hashPer('unit', [j.unit, j.st, j.pemain], function(){
      var spect = j.pemain.filter(function(p){ return p.u < 0; });
      document.getElementById('unit').innerHTML = j.unit.map(function(u){
        var cd = '<span class="chip s">S1: '+(u.cd.s1>0?u.cd.s1+' giliran':'siap')+'</span><span class="chip s">S2: '+(u.cd.s2>0?u.cd.s2+' giliran':'siap')+'</span>';
        var flag = (u.kutuk ? '<span class="chip k" title="Hanya terlihat oleh guru">💀 terkutuk</span>' : '') + (u.siap ? '<span class="chip k">👤 siap serang 275%</span>' : '');
        var status = !u.hidup ? '<span class="muted">pingsan</span>' : (j.st==='pilih' ? (u.kunci?'<span class="st-ok">✓ sudah memilih</span>':(u.online?'<span class="st-wait">⏳ memilih…</span>':'<span class="st-off">⚠ terputus</span>')) : (j.st==='soal' ? (u.sudah?'<span class="st-ok">✓ sudah menjawab</span>':(u.online?'<span class="st-wait">⏳ menjawab…</span>':'<span class="st-off">⚠ terputus</span>')) : '<span class="muted">–</span>'));
        var ganti = '<select data-ganti="'+u.i+'"><option value="">Ganti dengan…</option>'+spect.map(function(p){ return '<option value="'+p.id+'">'+esc(p.nama)+'</option>'; }).join('')+'</select>';
        return '<tr class="'+(u.hidup?'':'mati')+'"><td class="l"><b>'+PERAN[u.peran][1]+' '+PERAN[u.peran][0]+'</b> <span class="tag">'+TIMN[u.tim]+'</span></td>'
          + '<td class="l"><span class="dot'+(u.online?'':' off')+'"></span> '+esc(u.nama||'—')+'</td><td style="min-width:120px">'+barHP(u)+'</td><td>'+cd+flag+'</td><td>'+status+'</td><td>'+(spect.length?ganti:'<span class="muted small">tidak ada penonton</span>')+'</td></tr>';
      }).join('');
      root.querySelectorAll('[data-ganti]').forEach(function(s){ s.onchange = function(){ if (s.value && confirm('Ganti pemain pada karakter ini?')) aksi('ganti',{u:+s.dataset.ganti,p:+s.value}); else s.value = ''; }; });
    });
    riwayat();
  }

  // ---------- narasi untuk guru ----------
  function narasi(j, e){
    function n(i){ return esc(nm(j, i)); }
    var u = e.u;
    if (e.k === 'gagal') return '<span class="gagal">'+n(u)+' gagal menjalankan skill (jawaban salah / waktu habis / kutukan).</span>';
    if (e.k === 'perisai') return n(u)+(e.m==='semua'?' memasang <b>Benteng Tim</b> (damage masuk 20%).':(e.t===u?' melindungi dirinya sendiri (damage masuk 10%).':' <b>pasang badan</b> di depan '+n(e.t)+' (sekutu 0 damage, tank menerima 10%).'));
    if (e.k === 'lompat') return n(u)+(e.m==='diri'?' bersiap <b>menghindar</b> (menerima 20%).':' <b>melompat melindungi</b> '+n(e.t)+' (teman 20%, fighter 35%).');
    if (e.k === 'bayangan') return n(u)+' menghilang ke dalam <b>Bayangan</b> (tak bisa diserang giliran ini).';
    if (e.k === 'kutuk') return n(u)+' melempar <b>Kutukan</b> ke tim lawan (target dirahasiakan dari murid).';
    if (e.k === 'heal') return '<span class="heal">'+n(u)+' memakai '+(e.m==='semua'?'Hujan Cahaya':'Penyembuhan')+': '+e.h.map(function(h){ return esc(nm(j,h.u))+' +'+h.n; }).join(', ')+'</span>';
    if (e.k === 'serang') {
      var s = {basic:'Serangan Dasar', s1:'skill 1', strike:'Serangan Bayangan 275%', f2:'Rentetan Pukulan'}[e.s] || e.s;
      return n(u)+' ('+s+') → '+e.t.map(function(t){ return esc(nm(j,t.u))+(t.bl?' <b>meleset (bayangan)</b>':(t.fgd?' <span class="dmg">−'+(t.r!=null?t.r:t.d)+'</span> (fighter '+n(t.fgd.u)+' '+(t.fgd.tk?'ditangkis tank '+n(t.fgd.tk.u)+' −'+t.fgd.tk.r:'−'+t.fgd.r)+')':(t.gd?' <b>ditangkis</b> '+n(t.tk.u)+' <span class="dmg">−'+(t.tk.r!=null?t.tk.r:t.tk.d)+'</span>'+(t.tk.ko?' 💫':''):(t.sdh?' (sudah pingsan)':' <span class="dmg">−'+(t.r!=null?t.r:t.d)+'</span>'+(t.pr?' (perisai)':'')+(t.ko?' 💫':'')+(t.cr?' <b style="color:#d32f2f">CRITICAL</b>':'')+(t.pm?' <span class="muted">['+t.pm+'%]</span>':''))))); }).join(', ');
    }
    if (e.k === 'ko') return '💫 <b>'+n(u)+' pingsan!</b>';
    return '';
  }
  function riwayat(){
    hashPer('riw', [RIW.length, ST && ST.unit && ST.unit.map(function(u){ return u.nama; })], function(){
      var el = document.getElementById('riw'); if (!el) return;
      if (!RIW.length) { el.innerHTML = '<span class="muted">Belum ada giliran selesai.</span>'; return; }
      el.innerHTML = RIW.slice().reverse().map(function(r){
        return '<div class="rh">Giliran '+r.r+'</div>' + r.ev.map(function(e){ return '<div>'+narasi(ST, e)+'</div>'; }).join('');
      }).join('');
    });
  }

  function selesai(j){
    var a = j.akhir || {}, w = j.pemenang;
    var t = a.batal ? 'Sesi dibatalkan' : w===1?'🏆 TIM KIRI MENANG':w===2?'🏆 TIM KANAN MENANG':'🤝 Pertandingan SERI';
    var al = String(a.alasan||'');
    var alasan = a.batal ? '' : al==='habis' ? 'Seluruh anggota tim lawan tumbang.' : w===3 ? 'Kedua tim sama kuat.' : (al.indexOf('guru')===0?'Diakhiri guru. ':'Soal habis. ') + (al.indexOf('hp')>=0 ? 'Karakter hidup sama ('+(a.hidup?a.hidup[1]:'')+'), total HP '+(a.hp?a.hp[1]+' vs '+a.hp[2]:'')+'.' : 'Karakter hidup '+(a.hidup?a.hidup[1]+' vs '+a.hidup[2]:'')+'.');
    var h = '<h2>'+t+'</h2><p>'+esc(alasan)+' Pertandingan berlangsung '+j.ronde+' giliran.</p>';
    if (j.peringkat) h += '<h3>Peringkat murid</h3><ol>'+j.peringkat.map(function(p){ return '<li>'+esc(p.nama)+' ('+PERAN[p.peran][0]+' '+TIMN[p.tim]+') — '+p.benar+' benar</li>'; }).join('')+'</ol>';
    h += '<p class="small muted">Nilai murid sudah masuk ke menu <b>Nilai</b>.</p><div class="actions"><button class="btn primary big" id="b-baru">➕ Sesi baru</button></div>';
    document.getElementById('akhir').innerHTML = h;
    document.getElementById('b-baru').onclick = function(){ aksi('sesi_baru'); };
    hashPer('unitakhir', j.unit, function(){
      document.getElementById('unit').innerHTML = '<table class="tb"><tr><th class="l">Karakter</th><th class="l">Murid</th><th>HP akhir</th></tr>' + j.unit.map(function(u){
        return '<tr class="'+(u.hidup?'':'mati')+'"><td class="l">'+PERAN[u.peran][1]+' '+PERAN[u.peran][0]+' <span class="tag">'+TIMN[u.tim]+'</span></td><td class="l">'+esc(u.nama||'—')+'</td><td>'+barHP(u)+'</td></tr>';
      }).join('') + '</table>';
    });
    riwayat();
  }

  setInterval(function(){
    var t = document.getElementById('tmr'); if (!t || !ST) return;
    var fase = ST.st === 'jeda' ? ST.sblm : ST.st;
    if (fase === 'hasil') { t.textContent = 'aksi'; return; }
    if (sisaBase == null) { t.textContent = '∞'; return; }
    var s = ST.st === 'jeda' ? sisaBase : Math.max(0, sisaBase - (Date.now() - sisaT0));
    t.textContent = Math.ceil(s/1000) + ' dtk';
  }, 250);

  loop();
})();
</script>
<?php page_footer();
