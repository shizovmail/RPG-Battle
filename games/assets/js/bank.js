// Jendela "Ambil dari Bank Soal" di formulir pembuat game.
// Server sudah menyesuaikan setiap soal dengan jenis game tujuan (data-t); di sini hanya memilih lalu memanggil
// window.RGBankAdd(items) yang didefinisikan oleh masing-masing formulir.
(function () {
  var root = document.getElementById('bank-root');
  if (!root) return;
  var API = root.dataset.api, TUJUAN = root.dataset.t;
  var sel = {};            // "idGame:indeks" -> item siap tambah
  var cacheSoal = {};      // idGame -> respons soal
  var buka = {};           // idGame -> sedang diperluas
  var scope = 'semua', kueri = '', nomor = 0, timer = null, ui = null;

  function h(tag, attr, kids) {
    var el = document.createElement(tag);
    Object.keys(attr || {}).forEach(function (k) {
      if (k === 'class') el.className = attr[k];
      else if (k === 'text') el.textContent = attr[k];
      else if (k.slice(0, 2) === 'on') el[k] = attr[k];
      else el.setAttribute(k, attr[k]);
    });
    (kids || []).forEach(function (c) { if (c) el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return el;
  }
  function api(params) {
    var q = Object.keys(params).map(function (k) { return k + '=' + encodeURIComponent(params[k]); }).join('&');
    return fetch(API + '?t=' + encodeURIComponent(TUJUAN) + '&scope=' + scope + '&' + q, { credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.error || 'Gagal memuat Bank Soal.'); return j; }); });
  }
  function jumlahPilih() { return Object.keys(sel).length; }

  // ---------- kerangka ----------
  function bangun() {
    var cari = h('input', { type: 'search', placeholder: 'Cari soal, jawaban, judul game, atau nama guru…', 'aria-label': 'Cari di Bank Soal' });
    var lingkup = h('select', { 'aria-label': 'Sumber soal' }, [
      h('option', { value: 'semua', text: 'Semua (milikku + publik)' }),
      h('option', { value: 'saya', text: 'Hanya milikku' }),
      h('option', { value: 'publik', text: 'Publik guru lain' })
    ]);
    var body = h('div', { class: 'bk-body' });
    var info = h('span', { class: 'bk-info' });
    var catatan = h('span', { class: 'bk-note small muted' });
    var tambah = h('button', { type: 'button', class: 'btn primary', disabled: 'disabled', text: 'Tambahkan' });
    var tutup = function () { ui.overlay.hidden = true; document.body.classList.remove('bk-lock'); };
    var overlay = h('div', { class: 'bk-overlay', hidden: 'hidden' }, [
      h('div', { class: 'bk-modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Bank Soal' }, [
        h('div', { class: 'bk-head' }, [h('h2', { text: '📚 Bank Soal' }), h('button', { type: 'button', class: 'btn small ghost', text: '✕ Tutup', onclick: tutup })]),
        h('div', { class: 'bk-bar' }, [cari, lingkup]),
        body,
        h('div', { class: 'bk-foot' }, [h('div', {}, [info, catatan]), h('div', { class: 'actions' }, [
          h('button', { type: 'button', class: 'btn', text: 'Batal', onclick: tutup }), tambah])])
      ])
    ]);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) tutup(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !overlay.hidden) tutup(); });
    document.body.appendChild(overlay);
    cari.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(function () { kueri = cari.value.trim(); muat(); }, 320);
    });
    lingkup.addEventListener('change', function () { scope = lingkup.value; cacheSoal = {}; muat(); });
    tambah.addEventListener('click', function () {
      var items = Object.keys(sel).map(function (k) { return sel[k]; });
      if (!items.length || typeof window.RGBankAdd !== 'function') return;
      var n = window.RGBankAdd(items);
      sel = {}; segarkanFoot(); tutup();
      toast('✓ ' + n + ' soal dari Bank Soal ditambahkan. Periksa dulu, lalu simpan game.');
      var daftar = document.querySelector('#daftar-soal, #live-soal, #rpg-soal');
      if (daftar) daftar.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    ui = { overlay: overlay, cari: cari, body: body, info: info, catatan: catatan, tambah: tambah };
  }
  function toast(teks) {
    var t = h('div', { class: 'bk-toast', role: 'status', text: teks });
    document.body.appendChild(t);
    setTimeout(function () { t.classList.add('off'); }, 4500);
    setTimeout(function () { t.remove(); }, 5200);
  }
  function segarkanFoot() {
    var n = jumlahPilih();
    ui.info.textContent = n ? n + ' soal dipilih' : 'Centang soal yang ingin dipakai';
    ui.catatan.textContent = typeof window.RGBankTujuan === 'function' ? ' ' + window.RGBankTujuan() : '';
    ui.tambah.disabled = !n;
    ui.tambah.textContent = n ? 'Tambahkan ' + n + ' soal ke game ini' : 'Tambahkan';
  }

  // ---------- komponen ----------
  function badge(vis) { return h('span', { class: 'tag ' + (vis === 'public' ? 'vis-pub' : 'vis-priv'), text: vis === 'public' ? '🌐 Publik' : '🔒 Privat' }); }
  function pemilik(g) { return g.milik ? 'milikmu' : 'oleh ' + g.guru; }

  function tampilJawaban(s) {
    if (s.t === 'isian') return h('div', { class: 'bk-jawab' }, ['✍️ Jawaban: ', h('b', { text: (s.j || []).join(' / ') })]);
    var ol = h('ol', { class: 'bk-opsi', type: 'A' });
    (s.o || []).forEach(function (o, i) { ol.appendChild(h('li', { class: i === s.b ? 'benar' : '' }, [o + (i === s.b ? ' ✓' : '')])); });
    return ol;
  }
  function barisSoal(gid, e, game) {
    var key = gid + ':' + e.i, ok = e.st !== 'skip';
    var cb = h('input', { type: 'checkbox', 'aria-label': 'Pilih soal' });
    if (!ok) cb.disabled = true;
    cb.checked = !!sel[key];
    cb.addEventListener('change', function () {
      if (cb.checked) sel[key] = e.item; else delete sel[key];
      row.classList.toggle('dipilih', cb.checked); segarkanFoot();
    });
    var kepala = game ? h('div', { class: 'bk-asal small muted' }, [(game.ikon || '') + ' ' + game.judul + ' · ' + pemilik(game) + ' · soal ' + (e.i + 1)]) : null;
    var row = h('label', { class: 'bk-soal' + (ok ? '' : ' mati') + (sel[key] ? ' dipilih' : '') }, [
      cb,
      h('div', { class: 'bk-isi' }, [kepala, h('div', { class: 'bk-q', text: e.s.q }), tampilJawaban(e.s),
        (e.serupa && e.serupa.length) ? h('div', { class: 'bk-serupa small muted' }, ['Soal yang sama juga ada di: ' + e.serupa.slice(0, 3).map(function (s) { return s.judul + ' (' + (s.milik ? 'milikmu' : s.guru) + ')'; }).join(', ') + (e.serupa.length > 3 ? ' dan ' + (e.serupa.length - 3) + ' lainnya' : '')]) : null,
        e.st === 'catatan' ? h('div', { class: 'bk-cat small' }, ['ℹ️ ' + e.msg]) : null,
        e.st === 'skip' ? h('div', { class: 'bk-skip small' }, ['⛔ Tidak bisa dipakai di game ini: ' + e.msg]) : null])
    ]);
    return row;
  }
  function barisGame(g) {
    var kotak = h('div', { class: 'bk-soal-list' });
    var wrap = h('div', { class: 'bk-game' });
    var tombolLihat = h('button', { type: 'button', class: 'btn small', text: buka[g.id] ? 'Sembunyikan' : 'Lihat soal' });
    var tombolSemua = h('button', { type: 'button', class: 'btn small primary', text: 'Pilih semua' });
    var cocok = typeof g.cocok === 'number' ? g.cocok : g.n;
    function isi() {
      kotak.innerHTML = '';
      if (!buka[g.id]) return;
      kotak.appendChild(h('p', { class: 'muted small', text: 'Memuat soal…' }));
      ambilSoal(g.id).then(function (r) {
        kotak.innerHTML = '';
        r.soal.forEach(function (e) { kotak.appendChild(barisSoal(g.id, e, null)); });
      }).catch(function (er) { kotak.innerHTML = ''; kotak.appendChild(h('p', { class: 'bk-skip', text: er.message })); });
    }
    tombolLihat.onclick = function () { buka[g.id] = !buka[g.id]; tombolLihat.textContent = buka[g.id] ? 'Sembunyikan' : 'Lihat soal'; isi(); };
    tombolSemua.onclick = function () {
      ambilSoal(g.id).then(function (r) {
        var n = 0;
        r.soal.forEach(function (e) { if (e.st !== 'skip') { sel[g.id + ':' + e.i] = e.item; n++; } });
        buka[g.id] = true; tombolLihat.textContent = 'Sembunyikan'; isi(); segarkanFoot();
      }).catch(function (er) { alert(er.message); });
    };
    wrap.appendChild(h('div', { class: 'bk-game-kepala' }, [
      h('div', { class: 'bk-game-info' }, [
        h('b', { text: (g.ikon || '🎮') + ' ' + g.judul }), ' ', badge(g.vis),
        h('div', { class: 'small muted' }, [pemilik(g) + ' · ' + g.jenis + ' · ' + g.n + ' soal' + (cocok < g.n ? ' (' + cocok + ' cocok dengan game ini)' : '')])]),
      h('div', { class: 'actions' }, [tombolLihat, cocok ? tombolSemua : null])
    ]));
    wrap.appendChild(kotak);
    if (buka[g.id]) isi();
    return wrap;
  }
  function ambilSoal(id) {
    if (cacheSoal[id]) return Promise.resolve(cacheSoal[id]);
    return api({ aksi: 'soal', game: id }).then(function (r) { cacheSoal[id] = r; return r; });
  }

  // ---------- muat ----------
  function muat() {
    var no = ++nomor;
    ui.body.innerHTML = '';
    ui.body.appendChild(h('p', { class: 'muted', text: 'Memuat…' }));
    var p = kueri ? api({ aksi: 'cari', q: kueri }) : api({ aksi: 'daftar' });
    p.then(function (r) {
      if (no !== nomor) return;
      ui.body.innerHTML = '';
      if (!kueri) {
        if (!r.games.length) { ui.body.appendChild(h('p', { class: 'muted', text: 'Belum ada game dengan soal di sumber ini.' })); return; }
        ui.body.appendChild(h('h3', { text: 'Semua judul (' + r.games.length + ')' }));
        r.games.forEach(function (g) { ui.body.appendChild(barisGame(g)); });
        return;
      }
      if (!r.games.length && !r.soal.length) { ui.body.appendChild(h('p', { class: 'muted', text: 'Tidak ada yang cocok dengan “' + kueri + '”.' })); return; }
      if (r.games.length) {
        ui.body.appendChild(h('h3', { text: 'Judul game / guru yang cocok (' + r.games.length + ')' }));
        r.games.forEach(function (g) { ui.body.appendChild(barisGame(g)); });
      }
      if (r.soal.length) {
        ui.body.appendChild(h('h3', { text: 'Soal yang cocok (' + r.soal.length + (r.terpotong ? '+' : '') + ')' }));
        if (r.terpotong) ui.body.appendChild(h('p', { class: 'muted small', text: 'Hasil dibatasi; persempit kata kunci untuk hasil yang lebih tepat.' }));
        var daftar = h('div', { class: 'bk-soal-list' });
        r.soal.forEach(function (e) { daftar.appendChild(barisSoal(e.game.id, e, e.game)); });
        ui.body.appendChild(daftar);
      }
    }).catch(function (er) {
      if (no !== nomor) return;
      ui.body.innerHTML = '';
      ui.body.appendChild(h('p', { class: 'bk-skip', text: er.message }));
    });
  }

  root.querySelector('[data-bank-buka]').addEventListener('click', function () {
    if (!ui) bangun();
    // setiap dibuka: mulai dari daftar SEMUA judul (pilihan yang sudah dicentang tetap diingat)
    ui.cari.value = ''; kueri = ''; buka = {};
    ui.overlay.hidden = false; document.body.classList.add('bk-lock');
    segarkanFoot(); muat();
    setTimeout(function () { ui.cari.focus(); }, 50);
  });
})();
