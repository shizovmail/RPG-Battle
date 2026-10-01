# RPG Battle 4 vs 4 — tambahan template untuk "Ruang Game Kelas"

Template game **live** baru untuk sistem `ruang-game-kelas` (zip `05262697-ruang-game-kelas.zip`), konsepnya seperti
*Tarik Tambang* (guru = wasit, murid main dari HP, pertandingan beranimasi di layar proyektor), tetapi berupa
**pertarungan RPG 4 vs 4**: Tank, Assassin, Mage, dan Healer di setiap tim.

Folder **`games/`** di repo ini berisi *hanya* file yang ditambahkan/diubah, dengan struktur yang sama dengan folder
sistem (folder tempat `config.php` berada). Cukup salin/timpa isinya ke folder sistem di XAMPP-mu.

## Cara memasang

1. Salin **isi** folder `games/` ke folder sistem (yang berisi `config.php`, `guru/`, `inc/`, `templates/`, …).
   Contoh: `C:\xampp\htdocs\app\games\` bila sistem dipasang di `htdocs/app/games`.
2. Selesai. Tidak perlu migrasi manual: tabel baru dibuat otomatis (`rpg_*`) pada permintaan pertama, dan template
   `rpg_battle` otomatis terdaftar. Cek di **Admin → Template game** bahwa "RPG Battle 4 vs 4" aktif.
3. Pastikan **Admin → Pengaturan → URL publik** menunjuk ke folder sistem yang benar
   (mis. `https://smp-kartini-dua.my.id/app/games`) supaya link dan QR murid benar. File `config.php` tidak diubah.
4. Guru: **Buat game → RPG Battle 4 vs 4** → isi soal → **Panel wasit**.

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
  di Tim Kiri & Tim Kanan (manual lewat dropdown, atau tombol **Acak peran**). Murid lain menjadi **penonton** dan bisa
  menggantikan pemain yang HP-nya bermasalah (**Ganti pemain**, kapan saja).
* **Satu giliran** (serempak untuk semua karakter yang hidup):
  1. **Pilih** — murid memilih skill (3 pilihan) dan target di HP. Waktu habis ⇒ skill & target **diacak**.
  2. **Soal** — setelah semua memilih, tiap murid mendapat *satu soal* (durasi mengikuti soal, atau durasi universal).
     Benar ⇒ skill berhasil; salah / waktu habis ⇒ karakter **tertunduk kecewa**, skill gagal (cooldown tetap terpakai).
  3. **Aksi** — server menghitung semuanya, layar proyektor memainkan animasi, lalu giliran berikutnya.
* **Tank & Healer** memilih anggota tim sendiri (termasuk diri sendiri) untuk skill utamanya; **Assassin & Mage**
  memilih lawan. Serangan dasar (damage kecil, tanpa cooldown) selalu menarget lawan.
* **Pemenang**: tim yang menumbangkan semua lawan. Jika soal habis (setelah diulang sesuai pengaturan): tim dengan
  **karakter hidup lebih banyak**; bila sama, **total HP lebih besar**; bila sama lagi, seri.

### Skill

| Peran | Skill 1 | Skill 2 | Serangan dasar |
|---|---|---|---|
| 🛡️ Tank | **Perisai Pelindung**: 1 anggota, damage lawan yang masuk 10% · tanpa cooldown | **Benteng Tim**: semua anggota, damage masuk 30% · cooldown 2 | 50% Attack |
| 🗡️ Assassin | **Tusukan Mematikan**: 1 lawan, 100% Attack · cooldown 2 | **Bayangan**: tak bisa diserang giliran itu; giliran berikutnya, bila benar lagi, **Serangan Bayangan 275% Attack** (harus benar 2× berturut-turut) · cooldown 3 | 50% Attack |
| 🔮 Mage | **Hujan Meteor** (Kiri, merah) / **Badai Es** (Kanan, biru muda): semua lawan, 100% Attack · cooldown 3 | **Kutukan**: 1 lawan, giliran berikutnya 75% gagal walau benar; **lawan tidak tahu siapa** · cooldown 2 | 50% Attack |
| ✨ Healer | **Penyembuhan**: 1 anggota, pulih sebesar stat Heal · cooldown 2 | **Hujan Cahaya**: semua anggota, 55% Heal per orang · cooldown 3 | 50% Attack |

* Damage = `(Attack × pengali) − Defend lawan`, minimal 1. Perisai tank mengalikan hasilnya (10% / 30%).
* Cooldown "2" = tidak bisa dipakai 2 giliran berikutnya, giliran ke-3 bisa lagi.
* Semua aksi dalam satu giliran dihitung serempak: perisai/bayangan/kutukan → pemulihan → serangan (urutan acak).
* Karakter yang HP-nya 0 **pingsan** (terbaring) dan tidak ikut giliran berikutnya.

### Stat bawaan (bisa diubah guru per game, dan per sesi di lobi)

| Peran | HP | Attack | Defend | Heal |
|---|---|---|---|---|
| Tank | 300 | 24 | 12 | – |
| Assassin | 140 | 75 | 5 | – |
| Mage | 160 | 55 | 6 | – |
| Healer | 170 | 30 | 8 | 40 |

Dipilih lewat simulasi ribuan pertandingan (`docs/simulasi_keseimbangan.py`): kedua sisi menang ±50% (cermin),
urutan damage Assassin > Mage > Healer > Tank sesuai rancangan, Tank sangat kokoh tetapi hampir tidak melukai, dan
selisih Attack − Defend selalu positif (serangan dasar Tank terhadap Tank pun tetap melukai minimal 1).

### Kebutuhan soal

Tiap giliran memakai **satu soal per karakter hidup** (sampai 8). Bank minimal 8 soal, tetapi disarankan **≥ 40–60 soal**
dan pengulangan 1–2 kali; formulir menampilkan perkiraan jumlah giliran. Bila soal tidak cukup untuk satu giliran
penuh, pertandingan berakhir dengan aturan "soal habis" di atas.

## Pengujian yang sudah dilakukan

* Simulasi game penuh via API (8–10 murid virtual) sampai ada pemenang.
* 43 pemeriksaan aturan (cooldown, perisai 10%/30%, damage minimal 1, bayangan & serangan 275%, kutukan 75% & tersembunyi,
  heal tunggal/semua, tumbang semua/seri, soal habis → hidup/HP/seri, auto-acak saat waktu habis, durasi soal vs universal,
  pergantian pemain).
* Tangkapan layar proyektor, panel wasit, formulir, dan HP murid pada setiap tahap (Chromium).
* Kunci jawaban tidak ada di `index.html` game maupun di respons API murid; aksi guru butuh login + CSRF.

Dibuat oleh Subrata Pratama, S.Pd. - SMP Kartini 2 Batam
