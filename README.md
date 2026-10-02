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
| 🛡️ Tank | 10–20% Attack | **Pasang Badan**: pindah ke depan 1 **teman** (bukan diri sendiri); teman **0 damage**, tank menerima **5–15%** damage itu · tanpa cooldown | **Benteng Tim**: semua anggota, damage masuk **15–25%** (acak per anggota) · cooldown 2 |
| 🥊 Fighter | 15–35% Attack | **Lompat Pelindung**: melompat ke depan 1 teman: teman menerima **5–25%**, fighter **10–40%**. Dipilih untuk diri sendiri ⇒ menghindar, menerima **5–25%**. Bila tank menjaga, tank yang menerima · cooldown 2 | **Rentetan Pukulan**: lawan pertama 4 pukulan × **15–30%** Attack, lalu lawan kedua (acak) 1 pukulan **45–65%** Attack, salto kembali · cooldown 2 |
| 🗡️ Assassin | 20–50% Attack (**CRITICAL** bila > 40%) | **Tusukan Mematikan**: 90–140% Attack (**CRITICAL** bila > 115%) · cooldown 2 | **Bayangan**: tak bisa diserang giliran itu; giliran berikutnya, bila benar lagi, **Serangan Bayangan 230–280%** (**CRITICAL** bila > 250%) · cooldown 3 |
| 🔮 Mage | 10–30% Attack | **Hujan Meteor** (Sky Heaven) / **Badai Es** (Dark Earth): semua lawan, 65–100% Attack, nilai acak tiap lawan · **cooldown 2** | **Kutukan**: 1 lawan, giliran berikutnya 75% gagal walau benar; lawan tidak tahu siapa · cooldown 2 |
| ✨ Healer | 5–25% Attack | **Penyembuhan**: 1 anggota, **85–115%** stat Heal · tanpa cooldown | **Hujan Cahaya**: semua anggota, **45–70%** stat Heal (acak tiap teman) · cooldown 3 |

* **CRITICAL**: hanya Assassin. Bila persen damage melebihi ambang (40% / 115% / 250%), tulisan **CRITICAL!** merah muncul sebentar di dekat target.
* **Penjagaan satu per satu**: tiap serangan lawan dilewatkan sendiri-sendiri ke tank/fighter penjaga. Jadi bila lawan menyerang lebih dari sekali
  (beberapa penyerang, serangan area, atau 4 pukulan Fighter), penjaga menerima setiap damage itu satu per satu. Tank menangkis lebih dulu daripada Fighter.
* Urutan perlindungan pada satu damage: Benteng Tim → tank menangkis → fighter melompat (bila fighter dijaga tank, tank menerima bagian fighter) → fighter menghindar.
* Aksi dalam satu giliran dihitung serempak: perisai/lompat/bayangan/kutukan → pemulihan → serangan (urutan acak).
* Cooldown "2" = tidak bisa dipakai 2 giliran berikutnya; skill yang gagal tetap memakai cooldown. Karakter dengan HP 0 **pingsan** dan tidak ikut giliran berikutnya.

### Stat bawaan (bisa diubah guru per game, dan per sesi di lobi)

| Peran | HP | Attack | Defend | Heal |
|---|---|---|---|---|
| Tank | 240 | 24 | 12 | – |
| Fighter | 190 | 48 | 9 | – |
| Assassin | 135 | 75 | 5 | – |
| Mage | 150 | 55 | 6 | – |
| Healer | 150 | 30 | 8 | 30 |

### Laporan simulasi (`docs/simulasi_keseimbangan.py`)

2500 pertandingan per baris, soal habis di giliran 30, peluang menjawab benar *p*. "Acak" = skill & target acak (batas bawah).
"Terarah" = menyerang 2 lawan terlemah dan melindungi 2 sekutu terlemah. Kedua sisi selalu menang ±50% (cermin), tidak ada seri.

**Cara menang (p = 0.7):**

| Mode | Pemain | Menang Sky / Dark | Semua musuh habis | Selisih jumlah hidup | Selisih total HP |
|---|---|---|---|---|---|
| 4 vs 4 | acak | 49% / 51% | **74%** | 19% | 7% |
| 4 vs 4 | terarah | 49% / 51% | **55%** | 36% | 9% |
| 5 vs 5 | acak | 49% / 51% | **74%** | 21% | 5% |
| 5 vs 5 | terarah | 51% / 49% | **54%** | 40% | 7% |

Rentang p = 0.5 → 0.9: semua musuh habis 30–85%, selisih jumlah hidup 11–60%, selisih total HP 3–11%.

**Per peran (p = 0.7).** *Kill akhir* = serangan yang menjatuhkan HP target ke 0 (kill lewat tangkisan dikreditkan ke penyerang asli).
*Pingsan pertama* = peran yang paling dulu tumbang di timnya. *Bertahan* = rata-rata giliran hidup (maks 30).

| Mode | Peran | Damage | Kill akhir | Pingsan | Pingsan pertama | Bertahan | Selamat sampai akhir |
|---|---|---|---|---|---|---|---|
| 4v4 acak | Tank | <1% | 0% | 51% | 15% | 20,5 | 49% |
| | Assassin | 56% | **80%** | 53% | 26% | 17,7 | 47% |
| | Mage | 44% | 20% | **58%** | **30%** | **17,1** | 42% |
| | Healer | <1% | 0% | 55% | 29% | 18,4 | 45% |
| 4v4 terarah | Tank | <1% | 0% | 31% | 0% | **25,5** | **69%** |
| | Assassin | 53% | **85%** | 52% | 41% | 18,4 | 48% |
| | Mage | 46% | 14% | **58%** | **42%** | 17,8 | 42% |
| | Healer | 1% | 0% | 45% | 16% | 21,5 | 55% |
| 5v5 acak | Tank | <1% | 0% | 49% | 11% | 20,7 | 51% |
| | Fighter | 17% | 13% | 55% | 18% | 19,0 | 45% |
| | Assassin | 41% | **65%** | 56% | 23% | 17,5 | 44% |
| | Mage | 41% | 22% | **61%** | **26%** | **16,9** | 39% |
| | Healer | <1% | 0% | 56% | 23% | 18,4 | 44% |
| 5v5 terarah | Tank | <1% | 0% | 30% | 0% | 25,9 | 70% |
| | Fighter | 23% | 15% | 36% | 1% | 24,8 | 64% |
| | Assassin | 36% | **70%** | 57% | 40% | 17,1 | 43% |
| | Mage | 40% | 15% | **63%** | **44%** | **16,2** | 37% |
| | Healer | <1% | 0% | 49% | 15% | 20,6 | 51% |

Kesimpulan:
* **Kill akhir terbanyak**: Assassin (65–85%), karena serangan besarnya sering menjadi pukulan penutup; Mage menyusul (14–22%), Fighter 13–15%.
* **Pemberi damage terbesar**: Assassin dan Mage (±80–100% bila 4v4); pada 5v5 Fighter menyumbang 17–23%. Tank dan Healer praktis tidak melukai (sesuai rancangan: penopang tim).
* **Paling cepat pingsan**: **Mage** (pingsan paling sering, 58–63%) dan **Assassin** (peran pertama yang tumbang bila lawan fokus: 40–44%) — keduanya "meriam kaca".
* **Paling lama bertahan**: **Tank** (hampir 26 dari 30 giliran bila pemain terarah), disusul **Fighter** (24,8) lalu Healer.
* Pada permainan acak semua peran tumbang dengan frekuensi sebanding (49–61%), jadi tidak ada peran yang tidak adil; peran bertahan baru menonjol bila pemain bermain terarah.

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
* 39+ pemeriksaan aturan otomatis (cooldown, Benteng 20%, Pasang Badan 0%/10% dan tak bisa diri sendiri, Fighter: lompat pelindung 20%/35%,
  menghindar 20%, rantai tank-fighter, rentetan pukulan 4+1, basic 10–20%, kategori soal & rotasi Fighter, 5 vs 5, soal sama untuk kedua tim,
  bayangan 275%, kutukan 75%, heal, tumbang semua/seri, soal habis, auto-acak, ganti pemain).
* Tangkapan layar proyektor, panel wasit, formulir, dan HP murid pada setiap tahap (Chromium).
* Kunci jawaban tidak ada di `index.html` game maupun di respons API murid; aksi guru butuh login + CSRF.

Dibuat oleh Subrata Pratama, S.Pd. - SMP Kartini 2 Batam
