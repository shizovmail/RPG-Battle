// JavaScript untuk halaman admin & guru
(function () {
  // Tombol salin
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var inp = document.querySelector(b.dataset.copy);
      inp.select();
      (navigator.clipboard ? navigator.clipboard.writeText(inp.value) : Promise.reject()).catch(function () { document.execCommand('copy'); });
      var t = b.textContent; b.textContent = 'Tersalin ✓'; setTimeout(function () { b.textContent = t; }, 1500);
    });
  });

  // Tutup menu dropdown saat klik di luar
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.menu[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
  });

  // ===== Pembuat soal =====
  var daftar = document.getElementById('daftar-soal');
  if (daftar) {
    var tpl = document.getElementById('tpl-soal').innerHTML;
    var nOpsi = +daftar.dataset.nopsi;
    var idx = daftar.querySelectorAll('[data-soal]').length;

    function nomori() {
      var s = daftar.querySelectorAll('[data-soal]');
      s.forEach(function (el, i) { el.querySelector('.soal-no').textContent = 'Soal ' + (i + 1); });
      var j = document.getElementById('jml-soal'); if (j) j.textContent = '(' + s.length + ')';
    }
    var bentuk = daftar.dataset.bentuk || 'pilihan';
    var tetap = JSON.parse(daftar.dataset.tetap || '[]');
    function tambah(data) {
      var wrap = document.createElement('div');
      wrap.innerHTML = tpl.replace(/__I__/g, idx++);
      var el = wrap.firstElementChild;
      if (data) {
        el.querySelector('textarea').value = data.q;
        var ins = el.querySelectorAll('input:not([type=radio]):not([type=hidden])');
        (data.o || []).forEach(function (t, i) { if (ins[i]) ins[i].value = t; });
        var r = el.querySelectorAll('input[type=radio]')[data.b || 0]; if (r) r.checked = true;
      }
      daftar.appendChild(el);
      nomori();
      return el;
    }
    document.getElementById('tambah-soal').addEventListener('click', function () {
      tambah().querySelector('textarea').focus();
    });
    daftar.addEventListener('click', function (e) {
      if (e.target.matches('[data-hapus-soal]')) {
        if (daftar.querySelectorAll('[data-soal]').length <= 1) { alert('Minimal harus ada satu soal.'); return; }
        e.target.closest('[data-soal]').remove(); nomori();
      }
    });
    nomori();

    // Dipakai jendela "Ambil dari Bank Soal": item sudah disesuaikan dengan jenis game ini oleh server
    window.RGBankAdd = function (items) {
      var pertama = daftar.querySelector('[data-soal]');
      var kosong = pertama && daftar.querySelectorAll('[data-soal]').length === 1 && !pertama.querySelector('textarea').value.trim();
      items.forEach(function (it) { tambah({ q: it.q, o: it.o || [], b: it.b || 0 }); });
      if (kosong) { pertama.remove(); nomori(); }
      return items.length;
    };

    // Impor teks
    var go = document.getElementById('impor-go');
    if (go) go.addEventListener('click', function () {
      var teks = document.getElementById('impor-teks').value.trim();
      var n = 0;
      // buang soal pertama jika masih kosong
      var pertama = daftar.querySelector('[data-soal]');
      var kosong = pertama && daftar.querySelectorAll('[data-soal]').length === 1 && !pertama.querySelector('textarea').value.trim();
      function belah(baris) {
        var i;
        if (baris.indexOf('\t') >= 0) i = baris.lastIndexOf('\t');
        else if (baris.indexOf('|') >= 0) i = baris.lastIndexOf('|');
        else if (baris.indexOf('=') >= 0) i = baris.lastIndexOf('=');
        else return null;
        var a = baris.slice(0, i).trim(), b = baris.slice(i + 1).trim();
        return a && b ? [a, b] : null;
      }
      if (bentuk === 'pilihan') {
        teks.split(/\n\s*\n/).forEach(function (b) {
          var baris = b.split('\n').map(function (x) { return x.trim(); }).filter(Boolean);
          if (baris.length < 2) return;
          var q = baris.shift(), o = [], benar = 0;
          baris.slice(0, nOpsi).forEach(function (t, i) {
            if (t.charAt(0) === '*') { benar = i; t = t.slice(1).trim(); }
            o.push(t.replace(/^[A-Ea-e][.)]\s+/, ''));
          });
          tambah({ q: q, o: o, b: benar }); n++;
        });
      } else {
        teks.split('\n').forEach(function (baris) {
          var p = belah(baris.trim()); if (!p) return;
          if (bentuk === 'tunggal') { tambah({ q: p[0], o: [p[1]], b: 0 }); n++; return; }
          var h = p[1].replace(/^\*/, '').trim().charAt(0).toLowerCase(), b = -1;
          tetap.forEach(function (t, i) { if (b < 0 && t.charAt(0).toLowerCase() === h) b = i; });
          if (b < 0) return;
          tambah({ q: p[0], o: [], b: b }); n++;
        });
      }
      if (n && kosong) { pertama.remove(); nomori(); }
      alert(n ? n + ' soal ditambahkan.' : 'Tidak ada soal yang terbaca. Periksa formatnya.');
      if (n) { document.getElementById('impor-teks').value = ''; go.closest('details').removeAttribute('open'); }
    });
  }

  // ===== Daftar nama =====
  var isi = document.getElementById('isi-nama');
  if (isi) {
    var hitung = function () {
      var n = isi.value.split('\n').filter(function (x) { return x.trim(); }).length;
      document.getElementById('jml-nama').textContent = '(' + n + ' nama)';
    };
    isi.addEventListener('input', hitung); hitung();
    var sel = document.getElementById('ambil-kelas');
    if (sel) sel.addEventListener('change', function () {
      var k = (window.KELAS_SAYA || [])[sel.value]; if (!k) return;
      if (isi.value.trim() && !confirm('Ganti daftar nama dengan murid kelas ' + k.nama + '?')) return;
      isi.value = k.murid.join('\n'); hitung();
      var kl = document.querySelector('input[name=kelas]'); if (kl && !kl.value) kl.value = k.nama;
    });
  }
})();
