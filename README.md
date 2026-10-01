# RPG Battle (4 vs 4 atau 5 vs 5) — tambahan template untuk "Ruang Game Kelas"

Template game **live** baru untuk sistem `ruang-game-kelas` (zip `05262697-ruang-game-kelas.zip`), konsepnya seperti
*Tarik Tambang* (guru = wasit, murid main dari HP, pertandingan beranimasi di layar proyektor), tetapi berupa
**pertarungan RPG**: Tank, Assassin, Mage, Healer (dan Fighter opsional) di setiap tim — Sky Heaven Guardians vs Dark Earth Warriors.

Folder **`games/`** di repo ini berisi *hanya* file yang ditambahkan/diubah, dengan struktur yang sama dengan folder
sistem (folder tempat `config.php` berada). Cukup salin/timpa isinya ke folder sistem di XAMPP-mu.

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

### Skill

| Peran | Skill 1 | Skill 2 |
|---|---|---|
| 🛡️ Tank | **Pasang Badan**: pindah ke depan 1 **teman** (tidak bisa diri sendiri); teman itu **0 damage**, tank menerima **10%** damage tersebut · tanpa cooldown | **Benteng Tim**: semua anggota, damage masuk **20%** · cooldown 2 |
| 🥊 Fighter | **Lompat Pelindung**: melompat ke depan 1 teman dan menangkis untuknya: teman menerima **20%**, fighter **35%**. Dipilih untuk diri sendiri ⇒ fighter *menghindar* dan hanya menerima 20%. Bila tank menjaga fighter/teman itu, **tank paling depan yang menerima**. Cooldown 2 | **Rentetan Pukulan**: melompat ke lawan pertama, 4 pukulan × 20% Attack (total 80%), lompat ke lawan kedua (acak), 1 pukulan 55% Attack, lalu salto kembali. Cooldown 2 |
| 🗡️ Assassin | **Tusukan Mematikan**: 1 lawan, 100% Attack · cooldown 2 | **Bayangan**: tak bisa diserang giliran itu; giliran berikutnya, bila benar lagi, **Serangan Bayangan 275% Attack** (harus benar 2× berturut-turut) · cooldown 3 |
| 🔮 Mage | **Hujan Meteor** (Sky Heaven, merah) / **Badai Es** (Dark Earth, biru muda): semua lawan, 100% Attack · cooldown 3 | **Kutukan**: 1 lawan, giliran berikutnya 75% gagal walau benar; **lawan tidak tahu siapa** · cooldown 2 |
| ✨ Healer | **Penyembuhan**: 1 anggota, pulih sebesar stat Heal · **tanpa cooldown** | **Hujan Cahaya**: semua anggota, 55% Heal per orang · cooldown 3 |

* **Serangan dasar** semua peran: **10%–20% Attack** (acak tiap giliran), tanpa cooldown, selalu menarget lawan.
* Damage = `(Attack × pengali) − Defend lawan`, minimal 1. Rentetan Pukulan menghitung Defend sekali untuk 4 pukulan pertama.
* Urutan perlindungan pada damage ke satu karakter: Benteng Tim (20%) → tank menangkis (tank 10%) → fighter melompat (teman 20%, fighter 35%; bila fighter dijaga tank, tank menerima 10% bagian fighter) → fighter menghindar (20%).
* Aksi dalam satu giliran dihitung serempak: perisai/lompat/bayangan/kutukan → pemulihan → serangan (urutan acak).
* Cooldown "2" = tidak bisa dipakai 2 giliran berikutnya; skill yang gagal tetap memakai cooldown.
* Karakter yang HP-nya 0 **pingsan** (terbaring) dan tidak ikut giliran berikutnya.

### Stat bawaan (bisa diubah guru per game, dan per sesi di lobi)

| Peran | HP | Attack | Defend | Heal |
|---|---|---|---|---|
| Tank | 240 | 24 | 12 | – |
| Fighter | 175 | 48 | 9 | – |
| Assassin | 110 | 75 | 5 | – |
| Mage | 125 | 55 | 6 | – |
| Healer | 135 | 30 | 8 | 30 |

### Simulasi keseimbangan (`docs/simulasi_keseimbangan.py`)

Hasil 2000 pertandingan per baris, soal habis di giliran 30, peluang menjawab benar p = 0.5 / 0.7 / 0.9.
"Acak" = skill & target acak (batas bawah). "Terarah" = menyerang 2 lawan terlemah dan melindungi 2 sekutu terlemah.

| Mode | Pemain | Menang Sky / Dark | Menang karena **semua musuh habis** | karena **selisih jumlah hidup** | karena **selisih total HP** | Seri |
|---|---|---|---|---|---|---|
| 4 vs 4 | acak (p=0.7) | 50% / 50% | **66%** | 26% | 8% | 0% |
| 4 vs 4 | terarah (p=0.7) | 50% / 50% | **47%** | 44% | 9% | 0% |
| 5 vs 5 | acak (p=0.7) | 50% / 50% | **67%** | 28% | 5% | 0% |
| 5 vs 5 | terarah (p=0.7) | 50% / 50% | **45%** | 48% | 7% | 0% |

Rentang p = 0.5 sampai 0.9: menang karena semua musuh habis 25%–79%, karena selisih jumlah hidup 15%–64%, karena selisih total HP 4%–10%.
Kedua sisi selalu menang ±50% (cermin), tak ada seri. Tiap peran tumbang dengan frekuensi sebanding (Tank paling jarang bila pemain
terarah, Mage/Assassin paling sering), sehingga tidak ada peran yang dominan.

### Kebutuhan soal

Tiap giliran satu soal per pemain, dan **soal yang muncul sama untuk kedua tim** (4 soal pada 4 vs 4, 5 soal pada 5 vs 5), diacak ke tiap
anggota tim. Selama satu putaran, tiap pemain mendapat soal yang selalu berbeda (persegi Latin, seperti mode "acak anggota" Tarik Tambang).
Giliran maksimum = jumlah soal × (pengulangan + 1); bank 20–40 soal sudah cukup. Bila belum ada tim yang tumbang setelah soal habis,
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
