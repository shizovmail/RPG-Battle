# Bank Soal, Privat/Publik, dan Konversi Game — tambahan untuk "Ruang Game Kelas"

## Isi fitur

1. **Privat / Publik per game.** Setiap formulir game berbasis soal punya pilihan visibilitas.
   * 🔒 **Privat** — hanya pembuatnya yang melihat dan memakai ulang soal & jawabannya.
   * 🌐 **Publik** — guru lain yang punya akun bisa melihat soal **beserta jawabannya** dan memakainya (sebagian/seluruhnya).
   * Game lama dan game baru tanpa pilihan otomatis **privat**. Hasil "Duplikat" selalu mulai privat.
   * Bisa diganti kapan saja: **Game saya → Lainnya → Jadikan publik/privat**, atau di halaman Link & QR.
2. **Ambil dari Bank Soal** (tombol 📚 di formulir pembuat game, semua jenis game berbasis soal).
   * Menampilkan semua judul game beserta nama pemiliknya (milikmu + publik guru lain).
   * Kotak cari: pertanyaan, pilihan, jawaban, judul game, dan nama guru. Hasilnya soal beserta jawabannya.
   * Centang soal satuan atau **Pilih semua** per game. Soal otomatis disesuaikan dengan jenis game tujuan; yang tidak cocok ditandai beserta alasannya.
   * Soal yang sama persis di beberapa game digabung ("juga ada di …").
   * Soal yang diambil adalah **salinan**; mengubah game asli tidak memengaruhi game kamu.
3. **Menu Bank soal** — telusuri semua judul, cari, dan lihat soal & jawaban tanpa membuat game.
4. **Konversi game** (**Game saya → Lainnya → Konversi**, atau tombol di Bank Soal).
   * Soal & jawaban dipindah **persis sama** ke game baru berjenis lain; pengaturan kembali ke bawaan jenis tujuan (langsung diarahkan ke halaman Edit).
   * Untuk game publik guru lain, tombolnya **Pakai semua soal ini**. Kelas milik guru lain tidak ikut terbawa.
   * Pratinjau per jenis tujuan: berapa soal pindah utuh, berapa dengan catatan, berapa dilewati beserta alasannya.

## Cara memasang

1. **Cadangkan** folder sistem dan folder database (`app_data`, di luar `htdocs`).
2. Salin **isi** folder `games/` di zip ini ke folder sistem (yang berisi `config.php`), timpa file yang sama.
3. Selesai. Migrasi database (versi 3: kolom `visibilitas`) berjalan **otomatis** pada permintaan pertama. Aman diulang, data lama tidak berubah.
4. Semua game lama menjadi **privat**. Guru yang ingin berbagi soal menjadikannya publik sendiri.

`config.php`, template, dan file game live/RPG tidak diubah. Tidak ada tabel baru; hanya satu kolom di tabel `games`.

### File baru
| File | Fungsi |
|---|---|
| `inc/bank.php` | Logika: visibilitas, bentuk soal perantara, konversi, pencarian |
| `guru/bank.php` | Halaman Bank Soal (telusuri, cari, lihat soal & jawaban) |
| `guru/bank_api.php` | API JSON (hanya baca) untuk jendela pilih soal; hanya mengembalikan game sendiri + game publik |
| `guru/konversi.php` | Halaman konversi game |
| `assets/js/bank.js` | Jendela "Ambil dari Bank Soal" |

### File yang diubah
`inc/db.php` (migrasi v3), `inc/boot.php`, `inc/layout.php` (menu Bank soal), `guru/buat.php`, `guru/buat_live.php`, `guru/buat_rpg.php` (pilihan visibilitas + tombol bank),
`guru/game.php` (lencana, filter, menu), `guru/aksi.php` (ubah visibilitas), `guru/hasil.php`, `admin/index.php` (statistik game publik), `assets/js/app.js`, `assets/css/style.css`.

## Aturan konversi (soal tidak pernah diubah isinya)

| Dari ↓ / Ke → | Kuis, Labirin | Benar/Salah | Pasangan, TTS | Tarik Tambang, RPG |
|---|---|---|---|---|
| **Pilihan ganda** (Kuis, Labirin, Live) | ✓ utuh (jika pilihan ≤ batas) | hanya soal 2 pilihan Benar/Salah | ✓ **jawaban benar saja** (catatan) | ✓ utuh |
| **Benar/Salah** | ✓ utuh | — | ✓ jawaban benar saja | ✓ utuh |
| **Pasangan, TTS** (soal–jawaban) | ✗ tidak punya pilihan pengecoh | ✗ | ✓ utuh (TTS: jawaban 2–15 huruf) | ✓ sebagai isian singkat |
| **Isian singkat** (Live) | ✗ | ✗ | ✓ jawaban pertama (catatan) | ✓ utuh |

Soal yang tidak bisa dipindah **dilewati dan dilaporkan**, tidak diubah diam-diam. Batas jumlah soal jenis tujuan tetap berlaku
(RPG minimal 8, TTS maksimal 20, dst.); bila tidak terpenuhi, tombol konversi nonaktif disertai alasannya.
Konversi dari/ke RPG: durasi soal memakai bawaan 15 detik; model kategori peran dipertahankan hanya jika sumbernya RPG kategori.

## Keamanan & privasi
* Game privat milik orang lain tidak pernah dikirim: dicek di server pada halaman Bank, API, pencarian, dan konversi.
* Game publik milik akun yang dinonaktifkan admin tidak tampil di bank.
* Hanya pemilik yang bisa mengubah visibilitas game (diperiksa di server, dengan token CSRF).
* Menjadikan publik meminta konfirmasi, karena **jawaban ikut terlihat** oleh guru lain.
* Murid tidak punya akses ke Bank Soal (hanya akun guru).

## Pengujian yang sudah dijalankan
PHP 8.3 + SQLite: migrasi pada database lama dan instalasi baru; 19 uji mesin konversi; 53 uji alur dua akun guru (hak akses, pencarian,
konversi, ubah visibilitas, XSS); 23 uji browser (Chromium) jendela Bank Soal pada formulir umum, live, dan RPG.
