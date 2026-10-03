# RPG Battle (4 vs 4 atau 5 vs 5) — tambahan template untuk "Ruang Game Kelas"

Template game **live** baru untuk sistem `ruang-game-kelas` (zip `05262697-ruang-game-kelas.zip`), konsepnya seperti
*Tarik Tambang* (guru = wasit, murid main dari HP, pertandingan beranimasi di layar proyektor), tetapi berupa
**pertarungan RPG**: Tank, Assassin, Mage, Healer (dan Fighter opsional) di setiap tim — Sky Heaven Guardians vs Dark Earth Warriors.

Folder **`games/`** di repo ini adalah **seluruh sistem terbaru** (sudah termasuk fitur Bank Soal) dengan template RPG Battle di dalamnya.
Cukup salin/timpa isinya ke folder sistem di XAMPP-mu; data lama (database, game, upload) tidak ikut tertimpa karena tidak ada di dalamnya.
Rincian perubahan RPG Battle terbaru hanya pada file `inc/rpg.php`, `guru/rpg_layar.php`, `guru/rpg_live.php`, `guru/buat_rpg.php`, `api_rpg.php`, dan `templates/rpg_battle/`.

## Cara memasang

1. Salin **isi** folder `games/` ke folder sistem (yang berisi `config.php`, `guru/`, `inc/`, `templates/`, …).
   Contoh: `C:\xampp\htdocs\app\games\` bila sistem dipasang di `htdocs/app/games`.
2. Selesai. Tidak perlu migrasi manual: tabel baru dibuat otomatis (`rpg_*`) pada permintaan pertama, dan template
   `rpg_battle` otomatis terdaftar. Cek di **Admin → Template game** bahwa "RPG Battle" aktif.
3. Pastikan **Admin → Pengaturan → URL publik** menunjuk ke folder sistem yang benar
   (mis. `https://smp-kartini-dua.my.id/app/games`) supaya link dan QR murid benar. File `config.php` tidak diubah.
4. Guru: **Buat game → RPG Battle** → isi soal → **Panel wasit**.

### File baru (tidak menyentuh file lain)

| File | Fungsi |
|---|---|
| `templates/rpg_battle/manifest.json`, `template.html` | Template & tampilan HP murid (pilih skill/target, jawab soal, hasil) |
| `inc/rpg.php` | Mesin game di server: aturan, skill, cooldown, soal, kutukan, pemenang |
| `api_rpg.php` | API murid & guru (semua penilaian di server; kunci jawaban tidak pernah dikirim ke HP) |
| `guru/buat_rpg.php` | Formulir soal (durasi per soal), waktu, dan stat tiap peran |
| `guru/rpg_live.php` | Panel wasit (lobi, atur peran, kontrol pertandingan, ganti pemain, riwayat) |
| `guru/rpg_layar.php` | Layar proyektor beranimasi |

### Dua file lama yang diberi "pengait" satu baris

Tidak ada logika lama yang diubah; hanya ditambahkan satu baris agar template bisa memakai formulir & panelnya sendiri.
Jika file-mu sudah berbeda dari versi di zip, cukup tambahkan baris ini secara manual:

* `guru/buat.php` — tepat **sebelum** baris `if (!empty($m['live'])) { require __DIR__ . '/buat_live.php'; exit; }`:
  ```php
  if (!empty($m['form']) && preg_match('/^[a-z0-9_]+\.php$/', (string)$m['form'])) { require __DIR__ . '/' . $m['form']; exit; }
  ```
* `guru/live.php` — tepat **sesudah** baris `if (empty($tpl[$g['template']]['live'])) { redirect('guru/hasil.php?id=' . $g['id']); }`:
  ```php
  if (!empty($tpl[$g['template']]['panel']) && preg_match('/^[a-z0-9_]+\.php$/', (string)$tpl[$g['template']]['panel'])) { redirect('guru/' . $tpl[$g['template']]['panel'] . '?id=' . $g['id']); }
  ```

Game Tarik Tambang dan template lain tidak terpengaruh. Menghapus game akan ikut menghapus sesi RPG-nya (FK cascade).

## Cara bermain

* **Lobi**: murid membuka link/QR dan memasukkan nama. Guru menempatkan **8 murid** ke slot Tank/Assassin/Mage/Healer
  di Sky Heaven Guardians & Dark Earth Warriors (manual lewat dropdown, atau tombol **Acak peran**). Murid lain menjadi **penonton** dan bisa
  menggantikan pemain yang HP-nya bermasalah (**Ganti pemain**, kapan saja).
* **Satu giliran** (serempak untuk semua karakter yang hidup):
  1. **Pilih** — murid memilih skill (3 pilihan) dan target di HP. Waktu habis ⇒ skill & target **diacak**.
  2. **Soal** — setelah semua memilih, tiap murid mendapat *satu soal*. Seperti Tarik Tambang (mode "acak anggota"): tiap giliran hanya **4 soal yang sama untuk kedua tim**, diacak ke tiap anggota tim. Pemain **tidak akan mendapat soal yang sama lagi** sampai soal habis dan diulang (reset). Durasi mengikuti soal, atau durasi universal.
     Benar ⇒ skill berhasil; salah / waktu habis ⇒ karakter **tertunduk kecewa**, skill gagal (cooldown tetap terpakai).
  3. **Aksi** — server menghitung semuanya, layar proyektor memainkan animasi, lalu giliran berikutnya.
* **Tank & Healer** memilih anggota tim sendiri (termasuk diri sendiri) untuk skill utamanya; **Assassin & Mage**
  memilih lawan. Serangan dasar (damage kecil, tanpa cooldown) selalu menarget lawan.
* **Pemenang**: tim yang menumbangkan semua lawan. Jika soal habis (setelah diulang sesuai pengaturan): tim dengan
  **karakter hidup lebih banyak**; bila sama, **total HP lebih besar**; bila sama lagi, seri.

### Tim dan peran

* **Sky Heaven Guardians** (kiri) vs **Dark Earth Warriors** (kanan).
* 4 vs 4: 🛡️ Tank, 🗡️ Assassin, 🔮 Mage, ✨ Healer. **Fighter 🥊 bersifat opsional**: guru mencentang **Pakai karakter Fighter** di formulir atau di pengaturan lobi ⇒ **5 vs 5**.
* Formasi 5 peran (zig-zag): Tank depan agak bawah → Fighter agak atas → Assassin agak bawah → Mage agak atas → Healer paling belakang.
* Warna Fighter: kiri hijau limau + aksen oranye, kanan oranye bata + aksen kuning.

### Skill dan rentang damage

Semua angka berikut diacak (seragam) setiap kali dipakai. Damage = `(Attack × persen) − Defend lawan`, minimal 1.

| Peran | Serangan dasar (tanpa cooldown) | Skill 1 | Skill 2 |
|---|---|---|---|
| 🛡️ Tank | 10–20% Attack | **Pasang Badan**: pindah ke depan 1 **teman** (bukan diri sendiri); teman **0 damage**, tank menerima **100%** damage yang tertuju ke temannya (dihitung dengan Defend tank), ditambah serangan yang memang tertuju padanya · tanpa cooldown | **Benteng Tim**: semua anggota, damage masuk **15–25%** (acak per anggota) · cooldown 2 |
| 🥊 Fighter | 15–35% Attack | **Lompat Pelindung**: melompat ke depan 1 teman: teman menerima **5–25%**, fighter **50–70%**. Dipilih untuk diri sendiri ⇒ menghindar, menerima **5–25%**. Bila tank menjaga, tank yang menerima · cooldown 2 | **Rentetan Pukulan**: lawan pertama 4 pukulan × **15–30%** Attack, lalu lawan kedua (acak) 1 pukulan **45–65%** Attack, salto kembali · cooldown 2 |
| 🗡️ Assassin | 20–50% Attack (**CRITICAL** bila > 40%) | **Tusukan Mematikan**: 90–140% Attack (**CRITICAL** bila > 115%) · cooldown 2 | **Bayangan**: tak bisa diserang giliran itu; giliran berikutnya, bila benar lagi, **Serangan Bayangan 230–280%** (**CRITICAL** bila > 250%) · cooldown 3 |
| 🔮 Mage | 10–30% Attack | **Hujan Meteor** (Sky Heaven) / **Badai Es** (Dark Earth): semua lawan, 65–100% Attack, nilai acak tiap lawan · **cooldown 2** | **Kutukan**: 1 lawan, **langsung aktif giliran itu, 100% berhasil**: skill lawan itu gagal walau jawabannya benar, sebelum ia sempat beraksi; ia tidak tahu terkena kutukan (hasilnya sama seperti jawaban salah) · cooldown 2 |
| ✨ Healer | 5–25% Attack | **Penyembuhan**: 1 anggota, **200–250%** Attack healer · tanpa cooldown | **Hujan Cahaya**: semua anggota, **80–100%** Attack healer (acak tiap teman) · cooldown 3 |

* **CRITICAL**: hanya Assassin. Bila persen damage melebihi ambang (40% / 115% / 250%), tulisan **CRITICAL!** merah muncul sebentar di dekat target.
* **Penjagaan satu per satu**: tiap serangan lawan dilewatkan sendiri-sendiri ke tank/fighter penjaga. Jadi bila lawan menyerang lebih dari sekali
  (beberapa penyerang, serangan area, atau 4 pukulan Fighter), penjaga menerima setiap damage itu satu per satu. Tank menangkis lebih dulu daripada Fighter.
* Urutan perlindungan pada satu damage: Benteng Tim → tank menangkis → fighter melompat (bila fighter dijaga tank, tank menerima bagian fighter) → fighter menghindar.
* Aksi dalam satu giliran dihitung serempak: **kutukan** (langsung menggagalkan skill targetnya) → perisai/lompat/bayangan → pemulihan → serangan.
* **Siapa menyerang duluan?** Semua serangan dalam satu giliran bersifat **serempak**: tiap karakter yang hidup di awal giliran dan menjawab benar tetap menyerang
  walau ia dijatuhkan HP-nya oleh serangan lain pada giliran yang sama. Jadi bila dua Assassin sama-sama tinggal 1 hit KO dan keduanya menyerang, **keduanya pingsan**
  (tidak ada yang "duluan"). Urutan acak hanya memengaruhi kejadian yang saling bergantung, mis. siapa yang menghabiskan HP target lebih dulu (kredit kill) atau
  serangan yang menimpa target yang sudah pingsan di giliran yang sama (tidak ada damage tambahan). Pemulihan Healer berjalan sebelum serangan, jadi heal bisa menyelamatkan target yang diserang.
  Satu-satunya cara serangan musuh tidak jalan adalah: jawaban salah, kena Kutukan, atau pelaku sudah pingsan sejak giliran sebelumnya.
* Cooldown "2" = tidak bisa dipakai 2 giliran berikutnya; skill yang gagal tetap memakai cooldown. Karakter dengan HP 0 **pingsan** dan tidak ikut giliran berikutnya.

### Stat bawaan (bisa diubah guru per game, dan per sesi di lobi)

| Peran | HP | Attack | Defend |
|---|---|---|---|
| Tank | 360 | 24 | 25 |
| Fighter | 285 | 56 | 18 |
| Assassin | 158 | 80 | 8 |
| Mage | 176 | 65 | 10 |
| Healer | 190 | 60 | 12 |

Kekuatan pemulihan Healer **diambil dari Attack-nya** (tidak ada kolom Heal): Penyembuhan 200–250% × 60 = **120–150 HP**, Hujan Cahaya 80–100% × 60 = **48–60 HP** untuk tiap teman.
**Tabel lengkap damage tiap skill ke tiap peran beserta sisa HP, dan jumlah heal: [`docs/TABEL-DAMAGE.md`](docs/TABEL-DAMAGE.md)** (dibuat ulang dengan `python3 docs/tabel_damage.py > docs/TABEL-DAMAGE.md`).
Catatan: game yang sudah tersimpan membawa stat lama; klik **Kembalikan stat bawaan** di formulir (atau ubah di Panel wasit) untuk memakai stat baru.

### Laporan simulasi (`docs/simulasi_keseimbangan.py`)

Jalankan `python3 docs/simulasi_keseimbangan.py [giliran_maks] [jumlah_simulasi]` untuk mengulang semua angka di bawah. "Acak" = skill & target acak (batas bawah),
"terarah" = menyerang 2 lawan terlemah dan melindungi 2 sekutu terlemah; *p* = peluang menjawab benar. Kedua sisi menang ±50% (cermin).

**Pertandingan penuh (soal habis di giliran 30, p = 0.7):**

| Mode | Pemain | Semua musuh habis | Selisih jumlah hidup | Selisih total HP | Rata-rata giliran |
|---|---|---|---|---|---|
| 4 vs 4 | acak | **32%** | 55% | 13% | 27,9 |
| 4 vs 4 | terarah | **18%** | 68% | 14% | 29,1 |
| 5 vs 5 | acak | **27%** | 63% | 11% | 28,6 |
| 5 vs 5 | terarah | **15%** | 72% | 13% | 29,5 |

Peran (4v4 terarah): porsi damage Assassin 57% / Mage 42%; **kill akhir Assassin 92%** (Mage 8%); tumbang: Mage 53%, Assassin 46%, Healer 25%, Tank 12%. Tank dikeroyok seluruh lawan tanpa heal tidak pernah tumbang dalam 2 giliran.
Karakter lemah dikeroyok 3–4 lawan: bertahan ±2,5–2,8 giliran; + Healer ±2,7–3,4; + Healer + Tank ±8,3–9,5; + Tank + Fighter ±9,8–10,7 (5 vs 5, semua jawaban benar).

**Permainan dengan soal sedikit** (4 vs 4, p = 0,7; kolom = % pertandingan berakhir: *semua musuh habis / selisih jumlah hidup / selisih total HP*; giliran maksimum = soal × (pengulangan + 1)):

| Soal · ulang | Stat | Pemain terarah | Pemain acak |
|---|---|---|---|
| 5 · 1 (maks 10) | bawaan | 0 / 64 / 36 | 0 / 62 / 37 |
| | HP ×0,5 | 6 / 75 / 19 | 14 / 69 / 17 |
| | HP ×0,4 + Defend ×0,5 | 27 / 58 / 14 | 42 / 46 / 11 |
| 8 · 1 (maks 16) | bawaan | 0 / 74 / 25 | 2 / 74 / 24 |
| | HP ×0,5 | 32 / 56 / 12 | 49 / 41 / 10 |
| 8 · 2 (maks 24) | bawaan | 8 / 75 / 17 | 18 / 66 / 16 |
| | HP ×0,5 | 64 / 28 / 8 | 78 / 16 / 5 |
| 10 · 1 (maks 20) | bawaan | 3 / 76 / 21 | 8 / 72 / 19 |
| | HP ×0,5 | 50 / 40 / 9 | 67 / 26 / 7 |
| 10 · 2 (maks 30) | bawaan | 18 / 68 / 14 | 32 / 55 / 13 |

Dengan stat ini soal sedikit hampir tidak pernah berakhir dengan semua musuh habis; hasilnya ditentukan selisih jumlah hidup. Agar lebih seru: HP semua peran ×0,5, atau tambah pengulangan soal 2–3 kali, atau naikkan Attack semua karakter.
Model biasa minimal 8 soal per putaran, model kategori minimal 4 soal per kategori.

### Pilih peran mandiri & koneksi otomatis

* **Murid memilih peran sendiri (bawaan: aktif).** Setelah masuk lobi, murid melihat 2 tim dengan semua peran; tekan **Pilih** pada peran yang kosong. Bila sudah dipegang
  temannya, tombol tampil "Terisi" dan nama pemegangnya terlihat — minta ia menekan **Keluar** (kembali ke lobi) agar kamu bisa masuk. Murid boleh pindah peran/tim kapan saja selama lobi.
* **Guru memegang kendali.** Di Panel wasit (lobi) ada kotak centang **Murid memilih peran sendiri**. Mematikannya mengembalikan semua murid ke lobi (peran dikosongkan), lalu guru
  memilih peran tiap murid sendiri atau menekan **Acak peran**. Saat aktif pun guru tetap bisa mengatur/menukar peran. Pengaturan awalnya juga ada di formulir game.
* **Tidak terlempar dari game** (RPG Battle dan Tarik Tambang): tombol Back di HP tidak lagi menutup halaman; ada peringatan sebelum menutup/memuat ulang; saat HP kembali dibuka
  atau internet kembali, game langsung menyambung; bila terputus muncul pita merah "Menyambungkan kembali…". Bila token hilang (mis. sesi baru dibuat guru), halaman **otomatis
  masuk lagi dengan namamu** tanpa mengetik; bila dikeluarkan guru muncul tombol *Masuk lagi*. Di Tarik Tambang yang sedang berjalan ada tombol **"Aku sudah ikut, masuk kembali"**
  (tulis nama yang sama). Nama yang tertinggal online dilepas otomatis setelah ±8 detik.
  Catatan: halaman murid dibuat saat game disimpan, jadi **simpan ulang** game RPG/Tarik Tambang lama agar mendapat perbaikan ini.

### Musik latar (layar proyektor)

Musik RPG (`assets/js/rg-musik.js`, ±5 KB, disintesis browser, tanpa file audio, durasi ±76 detik lalu **mengulang terus**) hanya diputar di **layar proyektor**, bukan di HP murid.
Tekan **🔇 Aktifkan suara** (sekali, karena browser mewajibkan klik) — musik ikut menyala; atau tombol **🎵 Musik** di pojok kanan bawah. Tombol 🎵 berikutnya **membisukan/menyalakan musik saja**
(efek suara animasi tidak ikut). Pilihan bisu diingat. Musik berhenti sementara bila tab layar proyektor disembunyikan.
Keseimbangan sudah diukur (`SFX_VOL` di `guru/rpg_layar.php`, `RGMusic.vol` di `assets/js/rg-musik.js`): musik ±−21 dBFS, efek animasi puncaknya 6–13 dB di atas musik (kemenangan 17 dB), campuran 3 efek + musik tidak clipping (puncak −5 dBFS).
Suara keluar lewat perangkat audio yang dipakai browser (speaker laptop, atau speaker/HDMI proyektor bila itu output yang dipilih di laptop); volumenya mengikuti volume laptop.

### Kebutuhan soal

Tiap giliran satu soal per pemain, dan **soal yang muncul sama untuk kedua tim** (4 soal pada 4 vs 4, 5 soal pada 5 vs 5), diacak ke tiap
anggota tim. Selama satu putaran, tiap pemain mendapat soal yang selalu berbeda (persegi Latin, seperti mode "acak anggota" Tarik Tambang).
**Jumlah soal tiap putaran** (pengaturan, 0 = semua): membatasi berapa soal yang dipakai per putaran. Model kategori: 0 atau angka melebihi batas
= sebanyak kategori dengan soal paling sedikit (mis. tank 10, healer 12, assassin 11, mage 18 ⇒ 10 soal per putaran). Pengacakan tetap mengambil dari
seluruh soal: soal yang belum pernah muncul didahulukan pada putaran berikutnya (mage bisa mendapat soal 11–18 di putaran kedua).
Giliran maksimum = soal per putaran × (pengulangan + 1); bank 20–40 soal sudah cukup. Bila belum ada tim yang tumbang setelah soal habis,
berlaku aturan "soal habis" di atas.

**Model kategori peran** (pilihan di formulir): bank soal terpisah untuk Tank, Assassin, Mage, dan Healer (minimal 4 soal tiap kategori).
Tiap peran hanya mendapat soal kategorinya, sama di kedua tim, tanpa pengulangan. **Fighter** mendapat soal dari kategori yang bergilir:
dalam tiap 4 giliran keempat kategori muncul sekali (acak), kategori yang sama tidak muncul berturut-turut, dan ia tak menerima soal yang sama dua kali.
Giliran maksimum mengikuti kategori yang soalnya paling sedikit.

> **Penting saat memperbarui:** file game murid (`index.html`) dibuat saat game disimpan. Untuk game RPG Battle yang sudah ada, buka **Edit** lalu **Simpan** sekali agar file murid memakai template terbaru.

## Pengujian yang sudah dilakukan

* Simulasi game penuh via API (8–10 murid virtual) sampai ada pemenang.
* 39+ pemeriksaan aturan otomatis (cooldown, Benteng 15–25%, Pasang Badan 0%/100% dan tak bisa diri sendiri, Fighter: lompat pelindung 5–25%/50–70%,
  menghindar 20%, rantai tank-fighter, rentetan pukulan 4+1, basic 10–20%, kategori soal & rotasi Fighter, 5 vs 5, soal sama untuk kedua tim,
  bayangan 275%, kutukan langsung 100%, heal, tumbang semua/seri, soal habis, auto-acak, ganti pemain).
* Tangkapan layar proyektor, panel wasit, formulir, dan HP murid pada setiap tahap (Chromium).
* Kunci jawaban tidak ada di `index.html` game maupun di respons API murid; aksi guru butuh login + CSRF.

Dibuat oleh Subrata Pratama, S.Pd. - SMP Kartini 2 Batam
