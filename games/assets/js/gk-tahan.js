/* Penahan koneksi untuk game live di HP murid (Tarik Tambang, RPG Battle).
   - Tombol Back (Android) tidak lagi menutup halaman secara tidak sengaja.
   - Peringatan sebelum menutup/memuat ulang selama murid ada di dalam game.
   - Saat HP kembali dibuka / internet kembali, game langsung menyambung ulang.
   - Pita "Menyambungkan kembali…" bila koneksi terputus. */
(function () {
  var cfg = { aktif: function () { return false; }, lanjut: function () {} };
  var gagal = 0, pita = null, tunggu = 0;

  function tampilPita(on, teks) {
    if (!pita) {
      pita = document.createElement('div');
      pita.style.cssText = 'position:fixed;left:0;right:0;top:0;z-index:9999;padding:9px 12px;background:#d64545;color:#fff;text-align:center;font:700 .9rem system-ui,sans-serif;display:none';
      document.body.appendChild(pita);
    }
    pita.textContent = teks || '📶 Koneksi terputus — menyambungkan kembali…';
    pita.style.display = on ? 'block' : 'none';
  }
  function lanjutkan() { try { cfg.lanjut(); } catch (e) {} }

  window.GKT = {
    init: function (o) {
      cfg.aktif = o.aktif || cfg.aktif; cfg.lanjut = o.lanjut || cfg.lanjut;
      // jebakan tombol Back: satu entri riwayat cadangan yang selalu dipasang ulang
      try {
        history.pushState({ gkt: 1 }, '', location.href);
        window.addEventListener('popstate', function () {
          if (cfg.aktif()) { history.pushState({ gkt: 1 }, '', location.href); if (window.GK && GK.toast) GK.toast('Tetap di game. Tutup halaman dengan tombol Home HP.', '#2c56c9', 2200); }
          else { try { history.back(); } catch (e) {} }
        });
      } catch (e) {}
      window.addEventListener('beforeunload', function (ev) {
        if (!cfg.aktif()) return;
        ev.preventDefault(); ev.returnValue = 'Kamu sedang bermain. Yakin ingin keluar?'; return ev.returnValue;
      });
      document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') lanjutkan(); });
      window.addEventListener('pageshow', lanjutkan);
      window.addEventListener('focus', lanjutkan);
      window.addEventListener('online', function () { tampilPita(false); lanjutkan(); });
      window.addEventListener('offline', function () { tampilPita(true); });
    },
    // dipanggil dari polling: ok = true bila permintaan berhasil
    hasil: function (ok) {
      if (ok) { gagal = 0; tampilPita(false); return; }
      gagal++;
      if (gagal >= 2) tampilPita(true);
    },
    pita: tampilPita
  };
})();
