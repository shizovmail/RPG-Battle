<?php
require __DIR__ . '/../inc/boot.php';
require_once __DIR__ . '/../inc/live.php';
$u = require_login('guru');
$g = db_row('SELECT * FROM games WHERE id=? AND user_id=?', [(int)($_GET['id'] ?? 0), $u['id']]);
if (!$g) { flash('Game tidak ditemukan.', 'err'); redirect('guru/game.php'); }
$tpl = list_templates();
if (empty($tpl[$g['template']]['live'])) { redirect('guru/hasil.php?id=' . $g['id']); }
if (!empty($tpl[$g['template']]['panel']) && preg_match('/^[a-z0-9_]+\.php$/', (string)$tpl[$g['template']]['panel'])) { redirect('guru/' . $tpl[$g['template']]['panel'] . '?id=' . $g['id']); } // template dengan panel wasit sendiri
$link = game_link($u['username'], $g['slug']);
$bank = count((json_decode((string)$g['data'], true) ?: [])['soal'] ?? []);
page_header('Panel wasit', 'guru/game.php');
?>
<style>
  .lv-top{display:flex;gap:12px;align-items:center;flex-wrap:wrap;justify-content:space-between;margin-bottom:14px}
  .lv-top h1{margin:0}
  .cols{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
  @media(max-width:900px){.cols{grid-template-columns:1fr}}
  .kol{border-radius:16px;border:2px solid var(--line);background:#fff;padding:12px;min-height:120px}
  .kol h3{margin:0 0 8px;display:flex;justify-content:space-between;align-items:center}
  .kol.t1{border-color:#d7192a;background:#fff5f5}.kol.t1 h3{color:#b3121f}
  .kol.t2{border-color:#9aa7c2;background:#fff}
  .kol.t2 h3{color:#1c2436}
  .pm{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:7px 8px;border-radius:10px;background:#fff;border:1px solid var(--line);margin-bottom:6px}
  .pm .nm{font-weight:800;flex:1;min-width:100px}
  .pm select{width:auto;margin:0;padding:4px 6px;font-size:.82rem}
  .dot{width:10px;height:10px;border-radius:50%;background:#1f9d6b;display:inline-block}
  .dot.off{background:#d64545}
  .st-ok{color:#1f9d6b;font-weight:900}.st-wait{color:#b98410}.st-off{color:#d64545;font-weight:800}
  .lstat{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0}
  .lstat .box{background:#fff;border:1.5px solid var(--line);border-radius:14px;padding:10px 16px;text-align:center;min-width:120px}
  .lstat .box b{display:block;font:800 1.7rem var(--head)}
  .aksi-besar{font-size:1.15rem;padding:16px 28px}
  .glow{animation:glow 1s ease-in-out infinite;background:#1f9d6b!important;border-color:#1f9d6b!important;color:#fff!important}
  @keyframes glow{0%,100%{box-shadow:0 0 0 0 rgba(31,157,107,.6)}50%{box-shadow:0 0 0 12px rgba(31,157,107,0)}}
  .warn{background:#fff8e1;border:1.5px solid #f0d78a;border-radius:12px;padding:10px 14px;margin:10px 0}
  table.riw{border-collapse:collapse;width:100%}
  table.riw td,table.riw th{border-bottom:1px solid var(--line);padding:6px 10px;text-align:center}
  .rope{position:relative;height:34px;background:linear-gradient(90deg,#ffe0e0 0 50%,#f1f4fb 50% 100%);border-radius:99px;border:1.5px solid var(--line);margin:10px 0}
  .rope i{position:absolute;top:3px;width:26px;height:26px;border-radius:50%;background:#f4b52c;border:3px solid #7a4b00;transform:translateX(-50%);transition:left .6s}
  .rope span{position:absolute;top:6px;font-size:.8rem;font-weight:800}
  .cfg-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 18px}
  @media(max-width:700px){.cfg-grid{grid-template-columns:1fr}}
  .qbox{background:#f7f9ff;border:1.5px dashed #9db3e8;border-radius:12px;padding:10px 14px;margin:10px 0;font-weight:700}
</style>

<a class="back" href="<?= e(url('guru/game.php')) ?>">Game saya</a>
<div class="lv-top">
  <h1>🪢 <?= e($g['judul']) ?></h1>
  <div class="actions">
    <button class="btn primary" id="btn-layar">🖥️ Buka layar proyektor</button>
    <a class="btn" href="<?= e(url('guru/buat.php?id=' . $g['id'])) ?>">Edit soal</a>
    <a class="btn" href="<?= e(url('guru/skor.php?id=' . $g['id'])) ?>">Nilai</a>
  </div>
</div>
<div class="card" style="padding:12px 16px">
  <div class="copy-row"><input id="link" value="<?= e($link) ?>" readonly><button class="btn primary" data-copy="#link">Salin link murid</button></div>
  <p class="small muted" style="margin:6px 0 0">Bank soal: <b><?= $bank ?></b> soal. Murid membuka link/QR ini di HP. Jendela proyektor menampilkan QR untuk dipindai.</p>
</div>
<div id="panel"><div class="card">Memuat…</div></div>

<p class="small muted" style="text-align:center;margin-top:18px">Dibuat oleh Subrata Pratama, S.Pd. - SMP Kartini 2 Batam</p>
<script>
(function(){
  var GAME = <?= (int)$g['id'] ?>, CSRF = <?= json_encode(csrf_token()) ?>, BANK = <?= (int)$bank ?>;
  var API = <?= json_encode(url('api_live.php')) ?>;
  var LAYAR = <?= json_encode(url('guru/layar.php?id=' . $g['id'])) ?>;
  var root = document.getElementById('panel');
  var ST = null, keyTampil = '', sisaBase = null, sisaT0 = 0, sibuk = false, lastHash = {};

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
  document.getElementById('btn-layar').onclick = function(){ window.open(LAYAR,'layar_tt','width=1280,height=720'); };

  function loop(){
    get().then(function(j){ if (j.ok === false) { root.innerHTML = '<div class="warn">'+esc(j.pesan||'Terputus')+'</div>'; return; } terima(j); })
      .catch(function(){}).then(function(){ setTimeout(loop, 1000); });
  }
  function terima(j){
    ST = j; sisaBase = j.sisa; sisaT0 = Date.now();
    var k = j.st === 'lobi' ? 'lobi' : (j.st === 'kosong' || j.st === 'selesai') ? j.st + j.sesi : 'main';
    if (k !== keyTampil) { keyTampil = k; lastHash = {}; bangun(j); }
    perbarui(j);
  }

  // ---------- kerangka ----------
  function bangun(j){
    if (j.st === 'kosong') {
      root.innerHTML = '<div class="card"><h2>Belum ada sesi</h2><p>Buat sesi untuk membuka lobi. Setelah itu murid bisa masuk lewat link/QR.</p>'
        +'<button class="btn primary big" id="b-baru">➕ Buat sesi baru</button></div>';
      document.getElementById('b-baru').onclick = function(){ aksi('sesi_baru'); };
    } else if (j.st === 'lobi') {
      root.innerHTML = '<div class="card"><div class="sec-head"><h2>Lobi <span class="muted" id="jml"></span></h2><div class="actions"><button class="btn" id="b-acak">🎲 Acak tim</button><button class="btn danger small" id="b-batal">Batalkan sesi</button></div></div>'
        +'<div class="cols"><div class="kol t1"><h3>🔴 Tim Merah <span id="n1"></span></h3><div id="k1"></div></div><div class="kol t2"><h3>⚪ Tim Putih <span id="n2"></span></h3><div id="k2"></div></div><div class="kol"><h3>Belum bertim <span id="n0"></span></h3><div id="k0"></div></div></div>'
        +'<p class="small muted" style="margin-top:10px">Jumlah <b>HP</b> kedua tim harus sama. Jika murid ganjil, gunakan <b>“Satu HP dengan…”</b> supaya dua murid berbagi satu HP, atau keluarkan satu murid.</p>'
        +'<div id="syarat"></div><div class="actions" style="margin-top:8px"><button class="btn primary big" id="b-mulai">▶ Mulai pertandingan</button></div></div>'
        +'<div class="card"><h2>Pengaturan pertandingan</h2><div id="cfg"></div></div>';
      document.getElementById('b-acak').onclick = function(){ aksi('acak'); };
      document.getElementById('b-batal').onclick = function(){ if (confirm('Batalkan sesi ini? Murid yang sudah masuk harus masuk ulang.')) aksi('sesi_baru'); };
      document.getElementById('b-mulai').onclick = function(){ aksi('mulai'); };
      bangunCfg(j);
    } else if (j.st === 'selesai') {
      root.innerHTML = '<div class="card" id="akhir"></div><div class="card"><h2>Riwayat tarikan</h2><div id="riw"></div></div>';
    } else {
      root.innerHTML = '<div class="card"><div class="sec-head"><h2 id="jdl"></h2><div class="actions" id="ctl"></div></div><div class="lstat" id="stat"></div><div id="qb"></div><div class="rope" id="rope"></div><div class="cols" style="grid-template-columns:1fr 1fr"><div class="kol t1"><h3>🔴 Tim Merah <span id="n1"></span></h3><div id="k1"></div></div><div class="kol t2"><h3>⚪ Tim Putih <span id="n2"></span></h3><div id="k2"></div></div></div></div>'
        +'<div class="card"><h2>Riwayat tarikan</h2><div id="riw"></div></div>';
    }
  }

  function bangunCfg(j){
    var c = j.cfg, mode = <?= json_encode(live_mode_opsi(), JSON_UNESCAPED_UNICODE) ?>;
    var opts = Object.keys(mode).map(function(k){ return '<option value="'+k+'"'+(c.mode===k?' selected':'')+'>'+esc(mode[k])+'</option>'; }).join('');
    document.getElementById('cfg').innerHTML = '<div class="cfg-grid">'
      +'<label>Waktu menjawab per soal (detik, 0 = manual)<input type="number" data-c="waktu" min="0" max="300" value="'+c.waktu+'"></label>'
      +'<label>Selisih jawaban benar untuk menang<input type="number" data-c="selisih" min="1" max="30" value="'+c.selisih+'"></label>'
      +'<label>Jumlah soal pertandingan (0 = semua '+BANK+' soal)<input type="number" data-c="jumlah" min="0" max="'+BANK+'" value="'+c.jumlah+'"></label>'
      +'<label>Durasi layar hasil (detik)<input type="number" data-c="durasi_hasil" min="2" max="20" value="'+c.durasi_hasil+'"></label>'
      +'<label>Pengulangan soal jika selisih belum tercapai (kali)<input type="number" data-c="ulang" min="0" max="10" value="'+c.ulang+'"></label></div>'
      +'<label>Cara soal muncul di HP murid<select data-c="mode">'+opts+'</select></label>'
      +'<label class="check"><input type="checkbox" data-c="acak_opsi"'+(c.acak_opsi?' checked':'')+'> Acak posisi pilihan jawaban</label>'
      +'<label class="check"><input type="checkbox" data-c="hindari_ulang"'+(c.hindari_ulang?' checked':'')+'> Utamakan soal yang belum dipakai di sesi sebelumnya</label>'
      +'<label class="check"><input type="checkbox" data-c="peringkat"'+(c.peringkat?' checked':'')+'> Tampilkan peringkat murid di akhir</label>'
      +'<p class="small muted" id="cfg-ok"></p>';
    document.querySelectorAll('#cfg [data-c]').forEach(function(el){
      el.onchange = function(){
        var cfg = {};
        document.querySelectorAll('#cfg [data-c]').forEach(function(x){ cfg[x.dataset.c] = x.type==='checkbox' ? x.checked : (x.tagName==='SELECT' ? x.value : +x.value); });
        aksi('cfg',{cfg:cfg}).then(function(){ var o=document.getElementById('cfg-ok'); if(o){o.textContent='Pengaturan tersimpan ✓';} });
      };
    });
  }

  // ---------- pembaruan ----------
  function hashPer(nama, val, fn){ var h = JSON.stringify(val); if (lastHash[nama] === h) return; lastHash[nama] = h; fn(); }
  function perbarui(j){
    if (j.st === 'lobi') return lobi(j);
    if (j.st === 'selesai') return selesai(j);
    if (j.st === 'kosong') return;
    main(j);
  }
  function hosts(j){ return j.pemain.filter(function(p){ return p.gabung_ke === null; }); }
  function mitra(j, id){ return j.pemain.filter(function(p){ return p.gabung_ke === id; })[0]; }

  function lobi(j){
    hashPer('lobi', [j.pemain, j.bisa_mulai, j.alasan], function(){
      var H = hosts(j), n = {0:[],1:[],2:[]};
      H.forEach(function(p){ n[p.tim].push(p); });
      [0,1,2].forEach(function(t){
        document.getElementById('n'+t).textContent = '('+n[t].length+')';
        document.getElementById('k'+t).innerHTML = n[t].map(function(p){ return baris(j,p,t); }).join('') || '<span class="muted small">—</span>';
      });
      document.getElementById('jml').textContent = '· '+j.pemain.length+' murid masuk';
      document.getElementById('syarat').innerHTML = j.bisa_mulai ? '' : '<div class="warn">'+esc(j.alasan)+'</div>';
      var bm = document.getElementById('b-mulai'); bm.disabled = !j.bisa_mulai; bm.style.opacity = j.bisa_mulai ? 1 : .5;
      wire();
    });
  }
  function baris(j, p, t){
    var m = mitra(j, p.id), h = '<div class="pm"><span class="dot'+(p.online?'':' off')+'" title="'+(p.online?'online':'terputus')+'"></span><span class="nm">'+esc(p.nama)+(m?' <span class="muted small">& '+esc(m.nama)+' 🤝</span>':'')+'</span>';
    if (t !== 1) h += '<button class="btn small" data-tim="1" data-p="'+p.id+'">→ Merah</button>';
    if (t !== 2) h += '<button class="btn small" data-tim="2" data-p="'+p.id+'">→ Putih</button>';
    if (m) h += '<button class="btn small ghost" data-pisah="'+m.id+'">Pisahkan</button>';
    else {
      var kand = hosts(ST).filter(function(x){ return x.id !== p.id && !mitra(ST, x.id) && !mitra(ST, p.id); });
      if (kand.length && !mitra(ST, p.id)) h += '<select data-gabung="'+p.id+'"><option value="">Satu HP dengan…</option>'+kand.map(function(x){ return '<option value="'+x.id+'">'+esc(x.nama)+'</option>'; }).join('')+'</select>';
    }
    h += '<button class="btn small ghost" data-out="'+p.id+'" title="Keluarkan">✕</button></div>';
    return h;
  }
  function wire(){
    root.querySelectorAll('[data-tim]').forEach(function(b){ b.onclick = function(){ aksi('tim',{p:+b.dataset.p,tim:+b.dataset.tim}); }; });
    root.querySelectorAll('[data-pisah]').forEach(function(b){ b.onclick = function(){ aksi('pisah',{p:+b.dataset.pisah}); }; });
    root.querySelectorAll('[data-out]').forEach(function(b){ b.onclick = function(){ if (confirm('Keluarkan murid ini dari lobi?')) aksi('keluarkan',{p:+b.dataset.out}); }; });
    root.querySelectorAll('[data-gabung]').forEach(function(s){ s.onchange = function(){ if (s.value) aksi('gabung',{p:+s.dataset.gabung, ke:+s.value}); }; });
  }

  function statusIkon(p){
    if (p.sudah) return '<span class="st-ok">✓ sudah</span>';
    return p.online ? '<span class="st-wait">⏳ belum</span>' : '<span class="st-off">⚠ terputus</span>';
  }
  function main(j){
    document.getElementById('jdl').innerHTML = (j.st==='jeda'?'⏸ Dijeda · ':'') + esc(j.label) + (j.sub ? ' <span class="tag">'+esc(j.sub)+'</span>' : '');
    var mand = j.cfg.waktu === 0;
    hashPer('ctl', [j.st, j.semua_sudah, mand, j.sblm], function(){
      var h = '';
      if (j.st === 'soal') h += '<button class="btn primary aksi-besar '+(j.semua_sudah&&mand?'glow':'')+'" id="a-tutup">'+(mand?'Tutup tarikan & tampilkan hasil':'Tutup tarikan sekarang')+'</button>';
      if (j.st === 'hasil') h += '<button class="btn primary aksi-besar '+(mand?'glow':'')+'" id="a-lanjut">'+(mand?'Soal berikutnya ▶':'Lewati layar hasil ▶')+'</button>';
      if (j.st === 'soal' || j.st === 'hasil') h += '<button class="btn" id="a-jeda">⏸ Jeda</button>';
      if (j.st === 'jeda') h += '<button class="btn primary aksi-besar" id="a-lanjutjeda">▶ Lanjutkan</button>';
      h += '<button class="btn danger small" id="a-akhiri">Akhiri game</button>';
      document.getElementById('ctl').innerHTML = h;
      var $ = function(i){ return document.getElementById(i); };
      if ($('a-tutup')) $('a-tutup').onclick = function(){ aksi('tutup'); };
      if ($('a-lanjut')) $('a-lanjut').onclick = function(){ aksi('lanjut'); };
      if ($('a-jeda')) $('a-jeda').onclick = function(){ aksi('jeda'); };
      if ($('a-lanjutjeda')) $('a-lanjutjeda').onclick = function(){ aksi('lanjut_jeda'); };
      $('a-akhiri').onclick = function(){ if (confirm('Akhiri game sekarang? Pemenang ditentukan dari posisi tali saat ini.')) aksi('akhiri'); };
    });
    var sm = j.sudah[1]+j.sudah[2], tot = j.jml[1]+j.jml[2];
    document.getElementById('stat').innerHTML =
      '<div class="box"><b id="tmr">–</b>sisa waktu</div>'
      +'<div class="box"><b>'+sm+' / '+tot+'</b>sudah menjawab</div>'
      +'<div class="box"><b>'+Math.abs(j.pos)+' / '+j.selisih+'</b>selisih tali'+(j.pos>0?' (Merah)':j.pos<0?' (Putih)':'')+'</div>';
    var qb = '';
    if (j.soal_tampil) qb = '<div class="qbox">Soal '+(j.soal_tampil.t==='pg'?'pilihan ganda':'isian singkat')+': '+esc(j.soal_tampil.q)+'</div>';
    else if (j.soal_set) qb = '<div class="qbox">Soal tarikan ini (sama untuk kedua tim): no. '+j.soal_set.join(', ')+' <span class="muted small" style="font-weight:400">· tiap anggota mendapat satu soal, diacak</span></div>';
    else if (j.st !== 'hasil') qb = '<p class="small muted">Mode acak per murid: setiap murid mendapat soal berbeda, jadi soal tidak ditampilkan di sini.</p>';
    document.getElementById('qb').innerHTML = qb;
    var pos = 50 - (j.pos / j.selisih) * 50; // Merah di kiri
    pos = Math.max(0, Math.min(100, pos));
    document.getElementById('rope').innerHTML = '<span style="left:12px;color:#b3121f">MERAH</span><span style="right:12px">PUTIH</span><i style="left:'+pos+'%"></i>';
    hashPer('tim', j.pemain, function(){
      var H = hosts(j);
      [1,2].forEach(function(t){
        var L = H.filter(function(p){ return p.tim === t; });
        document.getElementById('n'+t).textContent = '('+L.length+' HP)';
        document.getElementById('k'+t).innerHTML = L.map(function(p){
          var m = mitra(j,p.id);
          return '<div class="pm"><span class="nm">'+esc(p.nama)+(m?' & '+esc(m.nama):'')+'</span>'+(j.st==='soal'||j.st==='jeda'?statusIkon(p):'')+'</div>';
        }).join('');
      });
    });
    riwayat(j);
  }
  function riwayat(j){
    hashPer('riw', j.riwayat, function(){
      var el = document.getElementById('riw'); if (!el) return;
      if (!j.riwayat.length) { el.innerHTML = '<p class="muted small">Belum ada tarikan selesai.</p>'; return; }
      el.innerHTML = '<table class="riw"><tr><th>Tarikan</th><th>Merah benar</th><th>Putih benar</th><th>Hasil</th><th>Selisih tali</th></tr>'
        + j.riwayat.map(function(r){
          var h = r.win===0 ? 'Seri, tali diam' : (r.win===1?'Merah':'Putih')+' menarik '+r.lang+' langkah'+(r.cepat?' (lebih cepat)':'');
          return '<tr><td>'+r.r+'</td><td>'+r.b1+'</td><td>'+r.b2+'</td><td>'+h+'</td><td>'+(r.pos>0?'Merah +'+r.pos:r.pos<0?'Putih +'+(-r.pos):'0')+'</td></tr>';
        }).join('') + '</table>';
    });
  }
  function selesai(j){
    var w = j.pemenang, t = w===1?'🏆 TIM MERAH MENANG':w===2?'🏆 TIM PUTIH MENANG':'🤝 Pertandingan SERI';
    var h = '<h2>'+t+'</h2><p>Pertandingan selesai setelah '+j.riwayat.length+' tarikan.</p>';
    if (j.peringkat) h += '<h3>Peringkat murid</h3><ol>'+j.peringkat.map(function(p){ return '<li>'+esc(p.nama)+' ('+(p.tim===1?'Merah':'Putih')+') — '+p.benar+' benar</li>'; }).join('')+'</ol>';
    h += '<p class="small muted">Nilai murid sudah masuk ke menu <b>Nilai</b>.</p><div class="actions"><button class="btn primary big" id="b-baru">➕ Sesi baru</button></div>';
    document.getElementById('akhir').innerHTML = h;
    document.getElementById('b-baru').onclick = function(){ aksi('sesi_baru'); };
    riwayat(j);
  }

  setInterval(function(){
    var t = document.getElementById('tmr'); if (!t || !ST) return;
    if (ST.st === 'hasil') { t.textContent = 'hasil'; return; }
    if (sisaBase == null) { t.textContent = '∞'; return; }
    var s = ST.st === 'jeda' ? sisaBase : Math.max(0, sisaBase - (Date.now() - sisaT0));
    t.textContent = Math.ceil(s/1000) + ' dtk';
  }, 250);

  loop();
})();
</script>
<?php page_footer();
