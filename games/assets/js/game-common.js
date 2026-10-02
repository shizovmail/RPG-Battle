/* Pustaka bersama untuk semua game hasil generate.
   Dipanggil dari games/{guru}/{game}/index.html dengan jalur ../../../assets/js/game-common.js */
(function () {
  var D = window.GAME_DATA || {};
  var BASE = '../../../';

  var css = ''
    + '.gk-header{display:flex;align-items:center;gap:10px;padding:10px 16px;background:rgba(0,0,0,.18);color:#fff;font-family:system-ui,"Segoe UI",sans-serif}'
    + '.gk-header img{height:36px;width:36px;object-fit:contain;background:#fff;border-radius:8px;padding:2px}'
    + '.gk-sek{font-size:.78rem;opacity:.85}.gk-jud{font-weight:800;font-size:1.05rem}'
    + '.gk-ov{position:fixed;inset:0;background:rgba(15,20,40,.72);display:grid;place-items:center;z-index:999;padding:16px;font-family:system-ui,"Segoe UI",sans-serif}'
    + '.gk-box{background:#fff;color:#1c2436;border-radius:22px;padding:28px 24px;max-width:420px;width:100%;text-align:center;box-shadow:0 20px 50px rgba(0,0,0,.35);animation:gkpop .25s ease-out}'
    + '@keyframes gkpop{from{transform:scale(.9);opacity:0}to{transform:none;opacity:1}}'
    + '.gk-box h2{margin:.2em 0;font-size:1.5rem}.gk-box p{margin:.4em 0;color:#555}'
    + '.gk-emoji{font-size:3.2rem;line-height:1}'
    + '.gk-in{width:100%;box-sizing:border-box;font-size:1.15rem;padding:12px 14px;border:2px solid #d0d7e5;border-radius:12px;margin:14px 0 10px;text-align:center}'
    + '.gk-in.gk-err{border-color:#d64545;animation:gkgeleng .3s}'
    + '.gk-select{text-align:left;background:#fff;cursor:pointer}'
    + '@keyframes gkgeleng{25%{transform:translateX(-6px)}75%{transform:translateX(6px)}}'
    + '.gk-btn{background:#2c56c9;color:#fff;border:0;border-radius:12px;padding:13px 22px;font-size:1.08rem;font-weight:800;cursor:pointer;width:100%;box-shadow:0 4px 0 #1e3f99}'
    + '.gk-btn:active{transform:translateY(3px);box-shadow:0 1px 0 #1e3f99}'
    + '.gk-score{font-size:4rem;font-weight:900;color:#2c56c9;line-height:1.1}'
    + '.gk-small{font-size:.85rem}'
    + '.gk-toast{position:fixed;left:50%;top:18px;transform:translateX(-50%);padding:12px 22px;border-radius:999px;color:#fff;font-weight:800;z-index:998;font-family:system-ui,sans-serif;box-shadow:0 8px 20px rgba(0,0,0,.25)}';
  var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function overlay(html) {
    var o = document.createElement('div');
    o.className = 'gk-ov';
    o.innerHTML = '<div class="gk-box">' + html + '</div>';
    document.body.appendChild(o);
    return o;
  }
  function toast(teks, warna, ms) {
    var t = document.createElement('div');
    t.className = 'gk-toast'; t.style.background = warna || '#1f9d6b'; t.textContent = teks;
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, ms || 1300);
  }
  function header() {
    var h = document.getElementById('gk-header');
    if (!h) return;
    h.className = 'gk-header';
    h.innerHTML = (D.logo ? '<img src="' + esc(BASE + D.logo) + '" alt="">' : '')
      + '<div><div class="gk-sek">' + esc(D.sekolah || '') + (D.kelas ? ' · Kelas ' + esc(D.kelas) : '') + '</div>'
      + '<div class="gk-jud">' + esc(D.judul || '') + '</div></div>';
  }
  function checkStatus() {
    if (!D.id || location.protocol === 'file:') return Promise.resolve(true);
    return fetch(BASE + 'api.php?a=status&id=' + encodeURIComponent(D.id), { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (j) { return j.aktif !== false; })
      .catch(function () { return true; });
  }
  function askName(cb, tombol) {
    tombol = tombol || 'Mulai bermain';
    var pakaiKelas = D.sumber_nama === 'kelas' && Array.isArray(D.daftar_murid) && D.daftar_murid.length > 0;
    var saved = '';
    try { saved = sessionStorage.getItem('gk_nama') || ''; } catch (e) {}
    if (pakaiKelas) { askNameKelas(cb, saved, tombol); return; }
    var o = overlay('<div class="gk-emoji">🎮</div><h2>' + esc(D.judul) + '</h2>'
      + '<p>' + esc(D.sekolah || '') + (D.guru ? '<br>Guru: ' + esc(D.guru) : '') + '</p>'
      + '<input class="gk-in" id="gk-nama" maxlength="50" autocomplete="name" placeholder="Tulis nama lengkapmu" value="' + esc(saved) + '">'
      + '<button class="gk-btn" id="gk-go">' + esc(tombol) + '</button>');
    var inp = o.querySelector('#gk-nama');
    function mulai() {
      var n = inp.value.trim().replace(/\s+/g, ' ');
      if (n.length < 2) { inp.classList.remove('gk-err'); void inp.offsetWidth; inp.classList.add('gk-err'); inp.focus(); return; }
      try { sessionStorage.setItem('gk_nama', n); } catch (e) {}
      o.remove(); cb(n);
    }
    o.querySelector('#gk-go').onclick = mulai;
    inp.onkeydown = function (e) { if (e.key === 'Enter') mulai(); };
    setTimeout(function () { inp.focus(); }, 60);
  }
  function askNameKelas(cb, saved, tombol) {
    tombol = tombol || 'Mulai bermain';
    var LAIN = '__lain__';
    var opsi = D.daftar_murid.map(function (n) {
      return '<option value="' + esc(n) + '"' + (n === saved ? ' selected' : '') + '>' + esc(n) + '</option>';
    }).join('');
    var o = overlay('<div class="gk-emoji">🎮</div><h2>' + esc(D.judul) + '</h2>'
      + '<p>' + esc(D.sekolah || '') + (D.kelas_murid ? '<br>Kelas ' + esc(D.kelas_murid) : '') + '</p>'
      + '<select class="gk-in gk-select" id="gk-nama"><option value="">— Pilih namamu —</option>' + opsi
      + '<option value="' + LAIN + '">Nama saya tidak ada di daftar</option></select>'
      + '<input class="gk-in" id="gk-nama-lain" maxlength="50" placeholder="Tulis namamu" style="display:none">'
      + '<button class="gk-btn" id="gk-go">' + esc(tombol) + '</button>');
    var sel = o.querySelector('#gk-nama'), lain = o.querySelector('#gk-nama-lain');
    sel.onchange = function () {
      var pilihLain = sel.value === LAIN;
      lain.style.display = pilihLain ? 'block' : 'none';
      if (pilihLain) lain.focus();
    };
    function mulai() {
      var n = sel.value === LAIN ? lain.value.trim().replace(/\s+/g, ' ') : sel.value;
      if (!n || (sel.value === LAIN && n.length < 2)) {
        var target = sel.value === LAIN ? lain : sel;
        target.classList.remove('gk-err'); void target.offsetWidth; target.classList.add('gk-err'); target.focus();
        return;
      }
      try { sessionStorage.setItem('gk_nama', n); } catch (e) {}
      o.remove(); cb(n);
    }
    o.querySelector('#gk-go').onclick = mulai;
    lain.onkeydown = function (e) { if (e.key === 'Enter') mulai(); };
    setTimeout(function () { sel.focus(); }, 60);
  }
  function start(opt) {
    header();
    checkStatus().then(function (ok) {
      if (!ok) {
        overlay('<div class="gk-emoji">🔒</div><h2>Game sedang ditutup</h2><p>Guru belum membuka game ini. Coba lagi nanti atau tanyakan ke gurumu.</p>');
        return;
      }
      if (opt.needName) askName(opt.onStart); else opt.onStart('');
    });
  }
  function submit(nama, skor, maks, durasi) {
    if (!D.id || !D.skor || location.protocol === 'file:') return Promise.resolve(null);
    return fetch(BASE + 'api.php?a=skor', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: D.id, token: D.token, nama: nama, skor: skor, maks: maks, durasi: durasi })
    }).then(function (r) { return r.json(); }).catch(function () { return { ok: false }; });
  }
  function fmt(s) { s = Math.max(0, s | 0); var m = Math.floor(s / 60), d = s % 60; return m + ':' + (d < 10 ? '0' : '') + d; }
  function result(o) {
    var nilai = o.maks ? Math.round(o.skor / o.maks * 100) : 0;
    var emoji = nilai >= 90 ? '🏆' : nilai >= 70 ? '🎉' : nilai >= 50 ? '👍' : '💪';
    var pesan = nilai >= 90 ? 'Luar biasa!' : nilai >= 70 ? 'Hebat!' : nilai >= 50 ? 'Bagus, terus berlatih!' : 'Jangan menyerah, coba lagi!';
    var ov = overlay('<div class="gk-emoji">' + emoji + '</div><h2>' + pesan + '</h2><p>' + esc(o.nama) + '</p>'
      + '<div class="gk-score">' + nilai + '</div>'
      + '<p>Benar ' + o.skor + ' dari ' + o.maks + ' soal' + (o.durasi ? ' · waktu ' + fmt(o.durasi) : '') + '</p>'
      + '<p class="gk-small" id="gk-save">' + (D.skor && D.id ? 'Mengirim nilai ke guru…' : '') + '</p>'
      + '<button class="gk-btn" id="gk-rep">Main lagi</button>');
    submit(o.nama, o.skor, o.maks, o.durasi).then(function (r) {
      if (!r) return;
      ov.querySelector('#gk-save').textContent = r.ok ? '✅ Nilai sudah terkirim ke guru.' : '⚠️ Nilai belum terkirim' + (r.pesan ? ': ' + r.pesan : '. Periksa koneksi internet.');
    });
    ov.querySelector('#gk-rep').onclick = function () { ov.remove(); if (o.onReplay) o.onReplay(); };
  }
  function shuffle(a) {
    a = a.slice();
    for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)); var t = a[i]; a[i] = a[j]; a[j] = t; }
    return a;
  }
  var actx = null;
  function beep(freq, dur, type) {
    try {
      actx = actx || new (window.AudioContext || window.webkitAudioContext)();
      var o = actx.createOscillator(), g = actx.createGain();
      o.type = type || 'sine'; o.frequency.value = freq;
      g.gain.setValueAtTime(0.15, actx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.001, actx.currentTime + (dur || 0.15));
      o.connect(g); g.connect(actx.destination); o.start(); o.stop(actx.currentTime + (dur || 0.15));
    } catch (e) {}
  }
  function sfxBenar() { beep(660, .12); setTimeout(function () { beep(880, .18); }, 110); }
  function sfxSalah() { beep(200, .3, 'square'); }

  window.GK = { data: D, base: BASE, esc: esc, overlay: overlay, toast: toast, header: header, start: start, askName: askName,
    submit: submit, result: result, shuffle: shuffle, fmt: fmt, beep: beep, sfxBenar: sfxBenar, sfxSalah: sfxSalah };
})();
