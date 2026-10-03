<?php
// =====================================================
//  INTI GAME LIVE: RPG BATTLE 4 vs 4
//  Semua aturan, penilaian soal, kutukan (acak) dan perhitungan damage dilakukan di server.
//  Kunci jawaban tidak pernah dikirim ke HP murid.
//
//  Tim 1 = KIRI, Tim 2 = KANAN. Tiap tim punya 4 peran: tank, assassin, mage, healer.
//  Indeks karakter (unit): tim 1 = 0..3, tim 2 = 4..7 ; urutan peran = tank, assassin, mage, healer.
//
//  Satu "giliran" (ronde) berjalan serempak untuk semua karakter yang hidup:
//    pilih  -> tiap murid memilih skill + target (ada batas waktu, habis waktu = diacak)
//    soal   -> tiap murid menjawab satu soal (4 soal per giliran, sama untuk kedua tim; durasi mengikuti soal / universal)
//    hasil  -> server menghitung semua aksi, layar proyektor memainkan animasinya
// =====================================================
require_once __DIR__ . '/live.php';   // memakai live_norm, live_acak, live_tx

// Semua pengali damage/heal berupa RENTANG yang diacak (seragam) setiap kali dipakai.
const RPG_AOE = [0.65, 1.00];            // Mage skill 1: tiap lawan mendapat nilai acak sendiri (% attack)
const RPG_ASSASSIN_1 = [0.90, 1.40];     // Assassin skill 1 (Critical bila > 115%)
const RPG_STRIKE = [2.30, 2.80];         // Serangan Bayangan (Critical bila > 250%)
const RPG_CRIT = ['basic' => 0.40, 's1' => 1.15, 'strike' => 2.50];   // ambang Critical Assassin
const RPG_BENTENG = [0.60, 0.70];        // Benteng Tim: damage masuk 60-70% (dikurangi acak 30-40% per anggota)
const RPG_F_TEMAN = [0.05, 0.25];        // Fighter Lompat Pelindung: teman menerima 5-25%
const RPG_F_DIRI = [0.50, 0.70];         // ... fighter menerima 50-70%
const RPG_F_MENGHINDAR = [0.05, 0.25];   // ... melindungi diri sendiri (menghindar): 5-25%
const RPG_F_PUKUL = [0.15, 0.30];        // Fighter Rentetan Pukulan: tiap pukulan lawan pertama (4 pukulan)
const RPG_F_PUKUL_N = 4;
const RPG_F_SUSUL = [0.45, 0.65];        // pukulan ke lawan kedua
const RPG_HEAL_1 = [1.00, 1.25];         // Healer skill 1: 100-125% Attack healer
const RPG_HEAL_SEMUA = [0.40, 0.50];     // Healer skill 2: 40-50% Attack healer, acak per teman
function rpg_basic_rentang($peran)
{
    $r = ['tank' => [0.10, 0.20], 'healer' => [0.05, 0.25], 'mage' => [0.10, 0.30], 'assassin' => [0.20, 0.50], 'fighter' => [0.15, 0.35]];
    return $r[$peran] ?? [0.10, 0.20];
}
function rpg_acak(array $r) { return random_int((int)round($r[0] * 1000), (int)round($r[1] * 1000)) / 1000; }

function rpg_peran_list() { return ['tank', 'assassin', 'mage', 'healer']; }      // 4 peran dasar (indeks unit 0..7)
function rpg_peran_semua() { return ['tank', 'fighter', 'assassin', 'mage', 'healer']; }
function rpg_peran_info()
{
    return [
        'tank' => ['nama' => 'Tank', 'ikon' => '🛡️'],
        'fighter' => ['nama' => 'Fighter', 'ikon' => '🥊'],
        'assassin' => ['nama' => 'Assassin', 'ikon' => '🗡️'],
        'mage' => ['nama' => 'Mage', 'ikon' => '🔮'],
        'healer' => ['nama' => 'Healer', 'ikon' => '✨'],
    ];
}
function rpg_tim_nama($t) { return $t === 1 ? 'Sky Heaven Guardians' : ($t === 2 ? 'Dark Earth Warriors' : ''); }
// indeks unit: tim 1 = 0..3, tim 2 = 4..7 (tank, assassin, mage, healer); Fighter (opsional) = 8 (tim 1) dan 9 (tim 2)
function rpg_unit_idx($tim, $peran)
{
    if (!in_array((int)$tim, [1, 2], true)) return -1;
    if ($peran === 'fighter') return 8 + ((int)$tim - 1);
    $k = array_search($peran, rpg_peran_list(), true);
    if ($k === false) return -1;
    return ((int)$tim - 1) * 4 + $k;
}
function rpg_unit_tim($i) { return $i < 8 ? intdiv($i, 4) + 1 : $i - 7; }
function rpg_unit_peran($i) { return $i < 8 ? rpg_peran_list()[$i % 4] : 'fighter'; }

// ---------- Database (tabel dibuat sendiri, sistem lama tidak disentuh) ----------
function rpg_db()
{
    static $siap = false;
    $pdo = db();
    if ($siap) return $pdo;
    $siap = true;
    $ada = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='rpg_giliran'")->fetch();
    if ($ada) return $pdo;
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS rpg_sesi(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        game_id INTEGER NOT NULL REFERENCES games(id) ON DELETE CASCADE,
        status TEXT NOT NULL DEFAULT 'lobi',
        ronde INTEGER NOT NULL DEFAULT 0,
        cfg TEXT NOT NULL DEFAULT '{}',
        unit TEXT NOT NULL DEFAULT '[]',
        soal TEXT NOT NULL DEFAULT '[]',
        antrian TEXT NOT NULL DEFAULT '[]',
        ptr INTEGER NOT NULL DEFAULT 0,
        fase_mulai REAL NOT NULL DEFAULT 0,
        fase_dur REAL NOT NULL DEFAULT 0,
        sblm TEXT NOT NULL DEFAULT '',
        jeda_at REAL NOT NULL DEFAULT 0,
        kejadian TEXT NOT NULL DEFAULT '[]',
        riwayat TEXT NOT NULL DEFAULT '[]',
        pending TEXT NOT NULL DEFAULT '',
        pemenang INTEGER NOT NULL DEFAULT 0,
        akhir TEXT NOT NULL DEFAULT '{}',
        disimpan INTEGER NOT NULL DEFAULT 0,
        dibuat TEXT DEFAULT (datetime('now','localtime')));
    CREATE TABLE IF NOT EXISTS rpg_pemain(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sesi_id INTEGER NOT NULL REFERENCES rpg_sesi(id) ON DELETE CASCADE,
        token TEXT NOT NULL,
        nama TEXT NOT NULL,
        tim INTEGER NOT NULL DEFAULT 0,
        peran TEXT NOT NULL DEFAULT '',
        seen REAL NOT NULL DEFAULT 0);
    CREATE TABLE IF NOT EXISTS rpg_giliran(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sesi_id INTEGER NOT NULL REFERENCES rpg_sesi(id) ON DELETE CASCADE,
        ronde INTEGER NOT NULL,
        u INTEGER NOT NULL,
        pemain_id INTEGER NOT NULL,
        skill TEXT,
        target INTEGER NOT NULL DEFAULT -1,
        auto INTEGER NOT NULL DEFAULT 0,
        dikunci INTEGER NOT NULL DEFAULT 0,
        soal_idx INTEGER NOT NULL DEFAULT -1,
        perm TEXT NOT NULL DEFAULT '[]',
        durasi INTEGER NOT NULL DEFAULT 0,
        jawab TEXT,
        dijawab INTEGER NOT NULL DEFAULT 0,
        benar INTEGER NOT NULL DEFAULT 0,
        sukses INTEGER NOT NULL DEFAULT 0,
        ms INTEGER NOT NULL DEFAULT 0,
        UNIQUE(sesi_id, ronde, u));
    CREATE INDEX IF NOT EXISTS idx_rpg_sesi_game ON rpg_sesi(game_id);
    CREATE INDEX IF NOT EXISTS idx_rpg_pemain_sesi ON rpg_pemain(sesi_id);
    CREATE INDEX IF NOT EXISTS idx_rpg_giliran ON rpg_giliran(sesi_id, ronde);
    ");
    return $pdo;
}

// ---------- Pengaturan ----------
// Stat bawaan hasil simulasi keseimbangan (lihat README): peran pemukul (assassin, mage) rapuh tetapi
// sakit, healer & tank tahan lama tetapi nyaris tidak melukai. Selisih attack - defend selalu positif.
function rpg_stat_bawaan()
{
    return [
        'tank'     => ['hp' => 330, 'atk' => 24, 'def' => 12],
        'fighter'  => ['hp' => 250, 'atk' => 56, 'def' => 9],
        'assassin' => ['hp' => 225, 'atk' => 75, 'def' => 2],     // Serangan Bayangan maks (280%) menyisakan ±17-29 HP pada Assassin/Mage/Healer berHP penuh
        'mage'     => ['hp' => 229, 'atk' => 65, 'def' => 4],
        'healer'   => ['hp' => 233, 'atk' => 50, 'def' => 6],    // kekuatan heal Healer = Attack-nya
    ];
}

function rpg_cfg_bawaan()
{
    return ['waktu_pilih' => 12, 'sumber_waktu' => 'soal', 'waktu_universal' => 15, 'ulang' => 2,
        'lanjut_otomatis' => true, 'acak_opsi' => true, 'peringkat' => false, 'pakai_fighter' => false, 'pilih_mandiri' => true, 'soal_per_putaran' => 0, 'stat' => rpg_stat_bawaan()];
}

function rpg_cfg_bersih($raw)
{
    $d = rpg_cfg_bawaan();
    $raw = is_array($raw) ? $raw : [];
    $o = [];
    $o['waktu_pilih'] = max(5, min(60, (int)($raw['waktu_pilih'] ?? $d['waktu_pilih'])));
    $o['sumber_waktu'] = (($raw['sumber_waktu'] ?? $d['sumber_waktu']) === 'universal') ? 'universal' : 'soal';
    $o['waktu_universal'] = max(5, min(180, (int)($raw['waktu_universal'] ?? $d['waktu_universal'])));
    $o['ulang'] = max(0, min(10, (int)($raw['ulang'] ?? $d['ulang'])));
    $o['soal_per_putaran'] = max(0, min(500, (int)($raw['soal_per_putaran'] ?? $d['soal_per_putaran'])));   // 0 = semua soal
    foreach (['lanjut_otomatis', 'acak_opsi', 'peringkat', 'pakai_fighter', 'pilih_mandiri'] as $k) {
        $o[$k] = array_key_exists($k, $raw) ? !empty($raw[$k]) : $d[$k];
    }
    $o['stat'] = [];
    $batas = ['hp' => [20, 5000], 'atk' => [1, 500], 'def' => [0, 300]];
    foreach (rpg_peran_semua() as $p) {
        foreach ($batas as $k => $b) {
            $v = $raw['stat'][$p][$k] ?? $d['stat'][$p][$k];
            $o['stat'][$p][$k] = max($b[0], min($b[1], (int)$v));
        }
    }
    return $o;
}

// ---------- Soal ----------
function rpg_parse_soal($raw, array $jenis, &$err, $mode = 'biasa')
{
    $err = [];
    $out = [];
    $jenis = array_values(array_intersect($jenis, ['pg', 'isian']));
    foreach ((array)$raw as $row) {
        if (!is_array($row)) continue;
        $q = mb_substr(trim((string)($row['q'] ?? '')), 0, 500);
        $t = (string)($row['t'] ?? ($jenis[0] ?? 'pg'));
        if (!in_array($t, $jenis, true)) $t = $jenis[0] ?? 'pg';
        $w = isset($row['w']) && $row['w'] !== '' ? (int)$row['w'] : 15;
        $w = max(5, min(180, $w));
        $kat = (string)($row['k'] ?? '');
        $no = count($out) + 1;
        if ($t === 'pg') {
            $b = isset($row['b']) ? (int)$row['b'] : -1;
            $opsi = []; $benar = -1;
            for ($i = 0; $i < 4; $i++) {
                $x = mb_substr(trim((string)($row['o'][$i] ?? '')), 0, 200);
                if ($x === '') continue;
                if ($i === $b) $benar = count($opsi);
                $opsi[] = $x;
            }
            if ($q === '' && !$opsi) continue;
            if ($q === '') $err[] = "Soal $no: pertanyaan masih kosong.";
            if (count($opsi) < 2) $err[] = "Soal $no: isi minimal 2 pilihan jawaban.";
            elseif ($benar < 0) $err[] = "Soal $no: tandai jawaban yang benar (tidak boleh pilihan kosong).";
            $out[] = ['t' => 'pg', 'q' => $q, 'o' => $opsi, 'b' => max(0, $benar), 'w' => $w] + ($mode === 'kategori' ? ['k' => $kat] : []);
        } else {
            $j = $row['j'] ?? [];
            if (is_string($j)) $j = explode('|', $j);
            $lst = [];
            foreach ((array)$j as $x) {
                $x = mb_substr(trim((string)$x), 0, 100);
                if ($x !== '' && live_norm($x) !== '' && !in_array($x, $lst, true)) $lst[] = $x;
            }
            $lst = array_slice($lst, 0, 8);
            if ($q === '' && !$lst) continue;
            if ($q === '') $err[] = "Soal $no: pertanyaan masih kosong.";
            if (!$lst) $err[] = "Soal $no: isi jawaban singkat yang benar.";
            $out[] = ['t' => 'isian', 'q' => $q, 'j' => $lst, 'w' => $w] + ($mode === 'kategori' ? ['k' => $kat] : []);
        }
    }
    if ($mode === 'kategori') {
        foreach (rpg_peran_list() as $r) {
            $c = count(array_filter($out, function ($x) use ($r) { return ($x['k'] ?? '') === $r; }));
            if ($c < 4) $err[] = 'Kategori ' . rpg_peran_info()[$r]['nama'] . ' baru berisi ' . $c . ' soal; minimal 4 soal per kategori.';
        }
    } elseif (count($out) < 8) $err[] = 'Minimal harus ada 8 soal.';
    if (count($out) > 500) $err[] = 'Maksimal 500 soal.';
    return $out;
}

// ---------- Skill ----------
// tgt: musuh = pilih 1 lawan, sekutu = pilih 1 anggota tim sendiri (boleh diri sendiri), tidak = tanpa target
function rpg_skill_katalog($peran, $tim = 1)
{
    $basic = ['id' => 'basic', 'nama' => 'Serangan Dasar', 'ikon' => '⚔️', 'tgt' => 'musuh', 'cd' => 0,
        'desc' => 'Menyerang 1 lawan dengan damage kecil (' . (int)round(rpg_basic_rentang($peran)[0] * 100) . '–' . (int)round(rpg_basic_rentang($peran)[1] * 100) . '% attack, acak tiap giliran). Tanpa cooldown.'];
    switch ($peran) {
        case 'tank':
            return [
                ['id' => 's1', 'nama' => 'Pasang Badan', 'ikon' => '🛡️', 'tgt' => 'sekutu_lain', 'cd' => 0,
                    'desc' => 'Pindah ke depan 1 TEMAN (bukan diri sendiri) dan tangkis serangan untuknya: teman itu menerima 0 damage dan KAMU menerima 100% damage yang tertuju ke temanmu (memakai Defend-mu), selain damage yang memang tertuju padamu. Tanpa cooldown.'],
                ['id' => 's2', 'nama' => 'Benteng Tim', 'ikon' => '🏰', 'tgt' => 'tidak', 'cd' => 2,
                    'desc' => 'Lindungi SEMUA anggota tim: damage lawan dikurangi 30–40% (acak per anggota), jadi yang masuk 60–70%. Cooldown 2 giliran.'],
                $basic,
            ];
        case 'fighter':
            return [
                ['id' => 's1', 'nama' => 'Lompat Pelindung', 'ikon' => '🤸', 'tgt' => 'sekutu', 'cd' => 2,
                    'desc' => 'Melompat ke depan 1 teman dan menangkis serangan untuknya: teman menerima 5–25% damage, kamu menerima 50–70%. Bila dipilih untuk diri sendiri, kamu menghindar dan hanya menerima 5–25%. Cooldown 2 giliran.'],
                ['id' => 's2', 'nama' => 'Rentetan Pukulan', 'ikon' => '🥊', 'tgt' => 'musuh', 'cd' => 2,
                    'desc' => 'Melompat ke 1 lawan: 4 pukulan beruntun (masing-masing 15–30% attack), lalu melompat ke lawan lain (acak) dengan 1 pukulan 45–65% attack, lalu salto kembali. Cooldown 2 giliran.'],
                $basic,
            ];
        case 'healer':
            return [
                ['id' => 's1', 'nama' => 'Penyembuhan', 'ikon' => '💚', 'tgt' => 'sekutu', 'cd' => 0,
                    'desc' => 'Pulihkan HP 1 anggota tim (boleh diri sendiri) sebesar 100–125% Attack-mu. Tanpa cooldown.'],
                ['id' => 's2', 'nama' => 'Hujan Cahaya', 'ikon' => '🌟', 'tgt' => 'tidak', 'cd' => 3,
                    'desc' => 'Pulihkan HP SEMUA anggota tim sebesar 40–50% Attack-mu (acak tiap teman). Cooldown 3 giliran.'],
                $basic,
            ];
        case 'assassin':
            return [
                ['id' => 's1', 'nama' => 'Tusukan Mematikan', 'ikon' => '🗡️', 'tgt' => 'musuh', 'cd' => 2,
                    'desc' => 'Damage 90–140% attack ke 1 lawan; di atas 115% = CRITICAL. Damage terbesar di antara semua peran. Cooldown 2 giliran.'],
                ['id' => 's2', 'nama' => 'Bayangan', 'ikon' => '👤', 'tgt' => 'tidak', 'cd' => 3,
                    'desc' => 'Menghilang (tak bisa diserang giliran ini). Giliran berikutnya, jika benar lagi, serang 1 lawan dengan damage 230–280% attack (di atas 250% = CRITICAL)! Harus benar 2 kali berturut-turut. Cooldown 3 giliran.'],
                $basic,
            ];
        case 'mage':
            $s1 = $tim === 2
                ? ['nama' => 'Badai Es', 'ikon' => '❄️', 'desc' => 'Menjatuhkan batu es ke SEMUA lawan, damage 65–100% attack (acak tiap lawan). Cooldown 2 giliran.']
                : ['nama' => 'Hujan Meteor', 'ikon' => '☄️', 'desc' => 'Menjatuhkan meteor ke SEMUA lawan, damage 65–100% attack (acak tiap lawan). Cooldown 2 giliran.'];
            return [
                ['id' => 's1', 'nama' => $s1['nama'], 'ikon' => $s1['ikon'], 'tgt' => 'tidak', 'cd' => 2, 'desc' => $s1['desc']],
                ['id' => 's2', 'nama' => 'Kutukan', 'ikon' => '💀', 'tgt' => 'musuh', 'cd' => 2,
                    'desc' => 'Kutuk 1 lawan: langsung aktif giliran ini, 100% berhasil — skill lawan itu GAGAL walau ia menjawab benar. Ia tidak tahu terkena kutukan; hasilnya tampak seperti jawaban salah. Bila kamu sendiri dikutuk mage lawan, kutukanmu gagal; bila dua mage saling mengutuk, keduanya terkutuk. Cooldown 2 giliran.'],
                $basic,
            ];
    }
    return [$basic];
}

// Petunjuk permainan, stat awal dan penjelasan skill (dipakai halaman cetak guru dan lobi HP murid).
// Semua angka mengikuti $cfg, jadi ikut berubah bila guru mengutak-atik stat.
function rpg_petunjuk(array $cfg)
{
    $fighter = !empty($cfg['pakai_fighter']);
    $roles = $fighter ? rpg_peran_semua() : rpg_peran_list();
    $info = rpg_peran_info();
    $ringkas = [
        'tank' => 'Pelindung tim: HP dan Defend terbesar, damage kecil.',
        'fighter' => 'Petarung lincah: melindungi teman atau memukul beruntun.',
        'assassin' => 'Penyerang tunggal paling mematikan, tetapi paling rapuh.',
        'mage' => 'Penyerang area dan pengutuk lawan.',
        'healer' => 'Penyembuh tim; kekuatan heal diambil dari Attack-nya.',
    ];
    $out = ['peran' => [], 'umum' => []];
    foreach ($roles as $r) {
        $st = $cfg['stat'][$r];
        $sk = [];
        foreach (rpg_skill_katalog($r, 1) as $k) {
            $nm = $k['nama'];
            if ($r === 'mage' && $k['id'] === 's1') $nm = 'Hujan Meteor (Sky Heaven) / Badai Es (Dark Earth)';
            $row = ['nama' => $nm, 'ikon' => $k['ikon'], 'desc' => $k['desc'], 'cd' => (int)$k['cd'],
                'tgt' => $k['tgt'] === 'musuh' ? 'pilih 1 lawan' : ($k['tgt'] === 'sekutu' ? 'pilih 1 teman (boleh diri sendiri)' : ($k['tgt'] === 'sekutu_lain' ? 'pilih 1 teman' : 'tanpa target'))];
            if ($r === 'healer' && $k['id'] === 's1') $row['angka'] = 'Memulihkan ' . round($st['atk'] * RPG_HEAL_1[0]) . '–' . round($st['atk'] * RPG_HEAL_1[1]) . ' HP.';
            if ($r === 'healer' && $k['id'] === 's2') $row['angka'] = 'Memulihkan ' . round($st['atk'] * RPG_HEAL_SEMUA[0]) . '–' . round($st['atk'] * RPG_HEAL_SEMUA[1]) . ' HP untuk tiap teman.';
            $sk[] = $row;
        }
        if ($r === 'assassin') $sk[] = ['nama' => 'Serangan Bayangan', 'ikon' => '⚡', 'desc' => 'Giliran berikutnya setelah Bayangan (bila benar lagi): serang 1 lawan dengan damage 230–280% Attack (di atas 250% = CRITICAL).', 'cd' => 0, 'tgt' => 'pilih 1 lawan',
            'angka' => 'Damage maksimum ' . round($st['atk'] * RPG_STRIKE[1]) . ' sebelum dikurangi Defend lawan.'];
        $out['peran'][] = ['id' => $r, 'nama' => $info[$r]['nama'], 'ikon' => $info[$r]['ikon'], 'ringkas' => $ringkas[$r],
            'hp' => (int)$st['hp'], 'atk' => (int)$st['atk'], 'def' => (int)$st['def'], 'skill' => $sk];
    }
    $n = $fighter ? 5 : 4;
    $out['umum'] = [
        'Dua tim bertarung: ' . rpg_tim_nama(1) . ' melawan ' . rpg_tim_nama(2) . " ($n lawan $n). Tiap murid memegang satu karakter.",
        'Setiap giliran ada 3 tahap: (1) pilih skill dan target dalam waktu ' . (int)$cfg['waktu_pilih'] . ' detik (habis waktu = dipilihkan acak), (2) jawab satu soal, (3) hasil dihitung dan animasinya diputar di layar.',
        'Skill hanya berhasil bila soalnya dijawab BENAR. Jawaban salah atau waktu habis = skill gagal. Skill yang gagal tetap memakai cooldown.',
        'Cooldown "2" berarti skill tidak bisa dipakai 2 giliran berikutnya. Serangan Dasar tidak punya cooldown.',
        'Damage = (Attack × persen skill yang diacak) − Defend lawan, minimal 1. Heal Healer dihitung dari Attack Healer. Karakter dengan HP 0 pingsan dan tidak ikut giliran berikutnya.',
        'Urutan penghitungan dalam satu giliran: kutukan dan skill gagal → pelindung/buff (Benteng, Pasang Badan, Lompat Pelindung, Bayangan) → pemulihan (heal) → semua serangan terakhir. Karena serempak, dua karakter yang sama-sama sekarat bisa saling menjatuhkan.',
        'Kutukan Mage: langsung aktif pada giliran itu dan membuat skill targetnya gagal walau jawabannya benar (target tidak diberi tahu). Mage yang dikutuk mage lawan kutukannya gagal; dua mage saling mengutuk = keduanya terkutuk.',
        'Pemenang: tim yang menumbangkan semua karakter lawan. Bila soal habis (setelah pengulangan ' . (int)$cfg['ulang'] . ' kali): tim dengan karakter hidup lebih banyak, bila sama total HP lebih besar.',
        'Kerja sama tim: Tank dan Fighter melindungi teman yang HP-nya tipis, Healer menyembuhkan sebelum HP habis, Assassin dan Mage fokus menjatuhkan lawan yang lemah.',
    ];
    return $out;
}

// Skill yang boleh dipakai unit $u sekarang (mengikuti cooldown & kondisi bayangan)
function rpg_skill_tersedia(array $u)
{
    $tim = (int)$u['tim'];
    $out = [];
    foreach (rpg_skill_katalog($u['peran'], $tim) as $sk) {
        $sk['sisa'] = $sk['id'] === 'basic' ? 0 : (int)($u['cd'][$sk['id']] ?? 0);
        $sk['ok'] = $sk['sisa'] === 0;
        if ($u['peran'] === 'assassin' && !empty($u['siap'])) {
            if ($sk['id'] === 's2') {
                $sk = ['id' => 'strike', 'nama' => 'Serangan Bayangan', 'ikon' => '⚡', 'tgt' => 'musuh', 'cd' => 3, 'sisa' => 0, 'ok' => true,
                    'desc' => 'Muncul dari bayangan dan serang 1 lawan dengan damage 230–280% attack (di atas 250% = CRITICAL)!'];
            } else { $sk['ok'] = false; $sk['sisa'] = 0; $sk['kunci'] = true; }
        }
        $out[] = $sk;
    }
    return $out;
}

function rpg_skill_by_id(array $u, $id)
{
    foreach (rpg_skill_tersedia($u) as $sk) if ($sk['id'] === $id) return $sk;
    return null;
}

// Target sah untuk skill tertentu (daftar indeks unit)
function rpg_target_sah(array $units, array $u, array $sk)
{
    $out = [];
    if ($sk['tgt'] === 'tidak') return $out;
    foreach ($units as $x) {
        if ($x['hp'] <= 0) continue;
        $sama = (int)$x['tim'] === (int)$u['tim'];
        if ($sk['tgt'] === 'sekutu_lain' && (int)$x['i'] === (int)$u['i']) continue;   // tank tidak bisa memilih dirinya sendiri
        if ((($sk['tgt'] === 'sekutu') || ($sk['tgt'] === 'sekutu_lain')) === $sama) $out[] = (int)$x['i'];
    }
    return $out;
}

function rpg_pilihan_valid(array $units, array $u, $skillId, $target)
{
    $sk = rpg_skill_by_id($u, (string)$skillId);
    if (!$sk || !$sk['ok']) return [false, 'Skill itu belum bisa dipakai.', null];
    if ($sk['tgt'] === 'tidak') return [true, '', -1];
    if (!in_array((int)$target, rpg_target_sah($units, $u, $sk), true)) return [false, 'Target tidak sah. Pilih target yang masih hidup.', null];
    return [true, '', (int)$target];
}

// Pilihan acak (saat murid kehabisan waktu memilih)
function rpg_pilihan_acak(array $units, array $u)
{
    $ada = array_values(array_filter(rpg_skill_tersedia($u), function ($s) use ($units, $u) {
        return $s['ok'] && ($s['tgt'] === 'tidak' || rpg_target_sah($units, $u, $s));
    }));
    if (!$ada) $ada = [rpg_skill_by_id($u, 'basic')];
    $sk = $ada[random_int(0, count($ada) - 1)];
    $tg = rpg_target_sah($units, $u, $sk);
    return [$sk['id'], $tg ? $tg[random_int(0, count($tg) - 1)] : -1];
}

// ---------- Data sesi ----------
function rpg_get($sid) { rpg_db(); return db_row('SELECT * FROM rpg_sesi WHERE id=?', [(int)$sid]); }
function rpg_cfg_sesi($s) { return rpg_cfg_bersih(json_decode((string)$s['cfg'], true)); }
function rpg_units($s) { return json_decode((string)$s['unit'], true) ?: []; }
function rpg_bank($s) { return json_decode((string)$s['soal'], true) ?: []; }
function rpg_sesi_aktif($gameId)
{
    rpg_db();
    return db_row("SELECT * FROM rpg_sesi WHERE game_id=? AND status<>'selesai' ORDER BY id DESC LIMIT 1", [(int)$gameId]);
}
function rpg_sesi_terakhir($gameId)
{
    rpg_db();
    return db_row('SELECT * FROM rpg_sesi WHERE game_id=? ORDER BY id DESC LIMIT 1', [(int)$gameId]);
}
function rpg_buat_sesi($game)
{
    $data = json_decode((string)$game['data'], true) ?: [];
    $cfg = rpg_cfg_bersih($data['pengaturan'] ?? []);
    rpg_db();
    db_q('INSERT INTO rpg_sesi(game_id,cfg) VALUES(?,?)', [(int)$game['id'], json_encode($cfg)]);
    return rpg_get((int)db()->lastInsertId());
}
function rpg_pemain_semua($sid) { rpg_db(); return db_rows('SELECT * FROM rpg_pemain WHERE sesi_id=? ORDER BY id', [(int)$sid]); }

// indeks unit => baris pemain (hanya yang tim & perannya sah dan aktif; jika ganda, yang pertama)
function rpg_slot_map($sid)
{
    $s = db_row('SELECT cfg FROM rpg_sesi WHERE id=?', [(int)$sid]);
    $pakai = $s ? !empty(rpg_cfg_bersih(json_decode((string)$s['cfg'], true))['pakai_fighter']) : false;
    $m = [];
    foreach (rpg_pemain_semua($sid) as $p) {
        $i = rpg_unit_idx($p['tim'], $p['peran']);
        if ($i < 0 || ($i >= 8 && !$pakai) || isset($m[$i])) continue;
        $m[$i] = $p;
    }
    ksort($m);
    return $m;
}
function rpg_jumlah_unit($cfg) { return !empty($cfg['pakai_fighter']) ? 10 : 8; }

function rpg_bisa_mulai($sid)
{
    $m = rpg_slot_map($sid);
    $cfg = rpg_cfg_sesi(rpg_get($sid));
    $kosong = [];
    for ($i = 0; $i < rpg_jumlah_unit($cfg); $i++) {
        if (!isset($m[$i])) $kosong[] = rpg_peran_info()[rpg_unit_peran($i)]['nama'] . ' ' . rpg_tim_nama(rpg_unit_tim($i));
    }
    if ($kosong) return [false, 'Slot belum terisi: ' . implode(', ', $kosong) . '. Pilih murid untuk tiap peran atau tekan "Acak peran".'];
    return [true, ''];
}

function rpg_buat_unit(array $cfg)
{
    $u = [];
    $n = rpg_jumlah_unit($cfg);
    for ($i = 0; $i < $n; $i++) {
        $peran = rpg_unit_peran($i);
        $st = $cfg['stat'][$peran];
        $u[] = ['i' => $i, 'tim' => rpg_unit_tim($i), 'peran' => $peran, 'hp' => (int)$st['hp'], 'mx' => (int)$st['hp'],
            'atk' => (int)$st['atk'], 'def' => (int)$st['def'], 'heal' => (int)$st['atk'],
            'cd' => ['s1' => 0, 's2' => 0], 'kutuk' => 0, 'siap' => 0];
    }
    return $u;
}

function rpg_hidup(array $units, $tim)
{
    $n = 0;
    foreach ($units as $u) if ((int)$u['tim'] === (int)$tim && $u['hp'] > 0) $n++;
    return $n;
}

// Pemenang saat soal habis: lebih banyak karakter hidup, lalu total HP lebih besar, lalu seri
function rpg_pemenang_akhir(array $units)
{
    $a = [1 => rpg_hidup($units, 1), 2 => rpg_hidup($units, 2)];
    if ($a[1] !== $a[2]) return [$a[1] > $a[2] ? 1 : 2, 'hidup'];
    $h = [1 => 0, 2 => 0];
    foreach ($units as $u) $h[(int)$u['tim']] += max(0, (int)$u['hp']);
    if ($h[1] !== $h[2]) return [$h[1] > $h[2] ? 1 : 2, 'hp'];
    return [3, 'seri'];
}

// ---------- Mulai pertandingan ----------
function rpg_mulai($game, $s)
{
    live_tx(function () use ($game, $s) {
        $s = rpg_get($s['id']);
        if (!$s || $s['status'] !== 'lobi') return;
        $cfg = rpg_cfg_sesi($s);
        $data = json_decode((string)$game['data'], true) ?: [];
        $bank = array_values($data['soal'] ?? []);
        $n = count($bank);
        $kategori = ($data['mode_soal'] ?? 'biasa') === 'kategori';
        $L = rpg_panjang_putaran($data, (int)$cfg['soal_per_putaran']);
        $putaran = (int)$cfg['ulang'] + 1;
        $antrian = ['seed' => random_int(1, 2000000000), 'n' => $L, 'mode' => $kategori ? 'kategori' : 'biasa', 'fighter' => !empty($cfg['pakai_fighter'])];
        $kolam = [];
        if ($kategori) {
            foreach (rpg_peran_list() as $r) {
                $kolam[$r] = [];
                foreach ($bank as $i => $x) if (($x['k'] ?? '') === $r) $kolam[$r][] = $i;
            }
            $antrian['kat'] = $kolam;
            if (!empty($cfg['pakai_fighter'])) $antrian['fseq'] = rpg_fseq($L * $putaran);
        } else {
            $kolam['*'] = $n ? range(0, $n - 1) : [];
        }
        // Soal yang dipakai tiap putaran: utamakan soal yang paling jarang/ belum pernah muncul di putaran sebelumnya,
        // sisanya diacak; jadi seluruh bank tetap terpakai bergantian walau tiap putaran hanya memakai $L soal.
        $pakai = []; $antrian['susun'] = [];
        for ($pu = 0; $pu < $putaran; $pu++) {
            foreach ($kolam as $nm => $lst) $antrian['susun'][$pu][$nm] = rpg_susun_putaran($lst, $L, $pakai[$nm]);
        }
        db_q("UPDATE rpg_sesi SET status='pilih', ronde=0, unit=?, soal=?, antrian=?, ptr=0, kejadian='[]', riwayat='[]', pending='' WHERE id=?",
            [json_encode(rpg_buat_unit($cfg)), json_encode($bank, JSON_UNESCAPED_UNICODE), json_encode($antrian), (int)$s['id']]);
        rpg_ronde_baru_dalam((int)$s['id']);
    });
}

// Buka giliran berikutnya (di dalam transaksi). Bila soal tak cukup untuk satu giliran penuh => selesai.
function rpg_ronde_baru_dalam($sid)
{
    $s = rpg_get($sid);
    $units = rpg_units($s);
    $slot = rpg_slot_map($sid);
    $hidup = array_values(array_filter($units, function ($u) { return $u['hp'] > 0; }));
    if ((int)$s['ronde'] + 1 > rpg_giliran_maks($s)) {
        list($w, $alasan) = rpg_pemenang_akhir($units);
        rpg_selesai_dalam($s, $w, 'soal_habis_' . $alasan);
        return;
    }
    $r = (int)$s['ronde'] + 1;
    foreach ($hidup as $u) {
        $p = $slot[$u['i']] ?? null;
        db_q('INSERT OR IGNORE INTO rpg_giliran(sesi_id,ronde,u,pemain_id) VALUES(?,?,?,?)', [$sid, $r, (int)$u['i'], $p ? (int)$p['id'] : 0]);
    }
    db_q("UPDATE rpg_sesi SET status='pilih', ronde=?, fase_mulai=?, fase_dur=?, kejadian='[]', pending='' WHERE id=?",
        [$r, microtime(true), (int)rpg_cfg_sesi($s)['waktu_pilih'], $sid]);
}

// ---------- Perpindahan fase (dipanggil malas oleh setiap permintaan) ----------
function rpg_tick($sid)
{
    $s = rpg_get($sid);
    if (!$s) return null;
    $now = microtime(true);
    $cfg = rpg_cfg_sesi($s);
    if ($s['status'] === 'pilih') {
        if ($now >= (float)$s['fase_mulai'] + (int)$cfg['waktu_pilih'] + 0.8 || rpg_semua_kunci($s)) rpg_mulai_soal($sid);
    } elseif ($s['status'] === 'soal') {
        if ($now >= (float)$s['fase_mulai'] + (float)$s['fase_dur'] + 1.2 || rpg_semua_jawab($s)) rpg_tutup($sid);
    } elseif ($s['status'] === 'hasil') {
        if (!empty($cfg['lanjut_otomatis']) && $now >= (float)$s['fase_mulai'] + (float)$s['fase_dur']) rpg_lanjut($sid);
    }
    return rpg_get($sid);
}

function rpg_semua_kunci($s)
{
    $tot = (int)db_val('SELECT COUNT(*) FROM rpg_giliran WHERE sesi_id=? AND ronde=?', [$s['id'], $s['ronde']]);
    $k = (int)db_val('SELECT COUNT(*) FROM rpg_giliran WHERE sesi_id=? AND ronde=? AND dikunci=1', [$s['id'], $s['ronde']]);
    return $tot > 0 && $k >= $tot;
}
function rpg_semua_jawab($s)
{
    $tot = (int)db_val('SELECT COUNT(*) FROM rpg_giliran WHERE sesi_id=? AND ronde=?', [$s['id'], $s['ronde']]);
    $k = (int)db_val('SELECT COUNT(*) FROM rpg_giliran WHERE sesi_id=? AND ronde=? AND dijawab=1', [$s['id'], $s['ronde']]);
    return $tot > 0 && $k >= $tot;
}

// pilih -> soal : acak pilihan yang belum dikunci, lalu bagikan soal
function rpg_mulai_soal($sid)
{
    live_tx(function () use ($sid) {
        $s = rpg_get($sid);
        if (!$s || $s['status'] !== 'pilih') return;
        $cfg = rpg_cfg_sesi($s);
        $units = rpg_units($s);
        $bank = rpg_bank($s);
        $rows = db_rows('SELECT * FROM rpg_giliran WHERE sesi_id=? AND ronde=? ORDER BY id', [$sid, $s['ronde']]);
        $maks = 5;
        foreach ($rows as $g) {
            $u = $units[(int)$g['u']];
            if (!(int)$g['dikunci']) {
                list($sk, $tg) = rpg_pilihan_acak($units, $u);
                db_q('UPDATE rpg_giliran SET skill=?, target=?, auto=1, dikunci=1 WHERE id=?', [$sk, $tg, $g['id']]);
            }
            $idx = rpg_idx_soal($s, $u, (int)$s['ronde']);
            $it = $bank[$idx];
            $perm = [];
            if ($it['t'] === 'pg') {
                $perm = range(0, count($it['o']) - 1);
                if (!empty($cfg['acak_opsi'])) $perm = live_acak($perm);
            }
            $dur = $cfg['sumber_waktu'] === 'universal' ? (int)$cfg['waktu_universal'] : max(5, (int)($it['w'] ?? 15));
            $maks = max($maks, $dur);
            db_q('UPDATE rpg_giliran SET soal_idx=?, perm=?, durasi=? WHERE id=?', [$idx, json_encode($perm), $dur, $g['id']]);
        }
        db_q("UPDATE rpg_sesi SET status='soal', fase_mulai=?, fase_dur=? WHERE id=?", [microtime(true), $maks, $sid]);
    });
}

// ---------- Pembagian soal (sama seperti Tarik Tambang mode "acak anggota") ----------
// Tiap giliran hanya 4 soal, SAMA untuk kedua tim; tiap anggota tim mendapat salah satunya dengan penempatan acak.
// Dasar: persegi Latin siklik => selama N giliran (N = jumlah soal) tiap pemain mendapat N soal berbeda, jadi tidak ada
// soal yang muncul dua kali pada pemain yang sama. Setelah N giliran = satu putaran; pengulangan (reset) memulai
// putaran baru dengan acakan baru. Total giliran = N x (pengulangan + 1).
function rpg_antrian($s)
{
    $a = json_decode((string)$s['antrian'], true);
    if (!is_array($a)) $a = ['seed' => 1, 'n' => 1];
    if (!isset($a['susun'])) {   // sesi lama: pakai seluruh soal tiap putaran
        $a['susun'] = [];
        for ($pu = 0; $pu < 12; $pu++) {
            if (($a['mode'] ?? 'biasa') === 'kategori') foreach ($a['kat'] ?? [] as $r => $lst) $a['susun'][$pu][$r] = array_slice($lst, 0, max(1, (int)$a['n']));
            else $a['susun'][$pu]['*'] = $a['n'] > 0 ? range(0, (int)$a['n'] - 1) : [];
        }
    }
    return $a;
}
// Panjang satu putaran (jumlah giliran per putaran = jumlah soal yang dipakai).
// Model biasa: jumlah soal pengaturan (0 = semua). Model kategori: 0 = sebanyak kategori tersedikit; angka lain dibatasi kategori tersedikit.
function rpg_panjang_putaran(array $data, $jumlah)
{
    $bank = array_values($data['soal'] ?? []);
    $n = count($bank);
    if (($data['mode_soal'] ?? 'biasa') === 'kategori') {
        $nmin = PHP_INT_MAX;
        foreach (rpg_peran_list() as $r) $nmin = min($nmin, count(array_filter($bank, function ($x) use ($r) { return ($x['k'] ?? '') === $r; })));
        if ($nmin === PHP_INT_MAX) $nmin = 0;
        $L = $jumlah > 0 ? min((int)$jumlah, $nmin) : $nmin;
        return max(min(4, $nmin), $L);
    }
    if ($jumlah <= 0 || $jumlah >= $n) return $n;
    return max(min(8, $n), (int)$jumlah);
}
// Pilih $L soal untuk satu putaran dari kolam: yang paling jarang dipakai lebih dulu (acak di antara yang sama), lalu urutannya diacak.
function rpg_susun_putaran(array $kolam, $L, &$pakai)
{
    $pakai = is_array($pakai) ? $pakai : [];
    $kolam = live_acak($kolam);
    usort($kolam, function ($a, $b) use ($pakai) { return ($pakai[$a] ?? 0) <=> ($pakai[$b] ?? 0); });   // stabil (PHP 8)
    $pilih = array_slice($kolam, 0, min($L, count($kolam)));
    foreach ($pilih as $i) $pakai[$i] = ($pakai[$i] ?? 0) + 1;
    return array_values(live_acak($pilih));
}
// Urutan kategori soal untuk Fighter: tiap 4 giliran keempat kategori muncul sekali (acak), kategori yang sama
// tidak muncul berurutan, termasuk di batas blok.
function rpg_fseq($total)
{
    $cats = rpg_peran_list(); $seq = []; $prev = null;
    while (count($seq) < $total) {
        do { $b = live_acak($cats); } while ($prev !== null && $b[0] === $prev);
        foreach ($b as $c) $seq[] = $c;
        $prev = end($b);
    }
    return array_slice($seq, 0, $total);
}
// giliran maksimum dari data game (dipakai formulir/API untuk perkiraan)
function rpg_maks_dari_data(array $data, $ulang, $jumlah = 0)
{
    return rpg_panjang_putaran($data, (int)$jumlah) * ((int)$ulang + 1);
}
function rpg_giliran_maks($s)
{
    $a = rpg_antrian($s);
    $cfg = rpg_cfg_sesi($s);
    return max(1, (int)$a['n']) * ((int)$cfg['ulang'] + 1);
}
function rpg_idx_soal($s, array $u, $r)
{
    $a = rpg_antrian($s);
    $L = max(1, (int)$a['n']);
    $pass = intdiv($r - 1, $L);
    $q = ($r - 1) % $L + 1;
    $S = $a['susun'][$pass] ?? end($a['susun']);
    if (($a['mode'] ?? 'biasa') === 'kategori') {
        if ($u['peran'] === 'fighter') {
            // Fighter: kategori bergilir (rpg_fseq); soal dari kategori itu dalam putaran ini, urutan dibalik agar berbeda dari pemilik kategori
            $seq = $a['fseq'] ?? [];
            $cat = $seq[$r - 1] ?? 'tank';
            $kc = count(array_filter(array_slice($seq, $pass * $L, ($r - 1) - $pass * $L), function ($c) use ($cat) { return $c === $cat; }));
            $list = array_reverse($S[$cat] ?? []);
            return $list[$kc % max(1, count($list))];
        }
        return ($S[$u['peran']] ?? [0])[$q - 1] ?? 0;   // tiap peran memakai soal kategorinya, sama di kedua tim
    }
    $D = $S['*'];
    $pos = $u['peran'] === 'fighter' ? 4 : (int)array_search($u['peran'], rpg_peran_list(), true);
    return $D[live_anggota_idx($a['seed'] . '.' . $pass, $L, !empty($a['fighter']) ? 5 : 4, (int)$u['tim'], $pos, $q)];
}

// Murid memilih
function rpg_simpan_pilihan($s, $pemain, $skill, $target)
{
    return live_tx(function () use ($s, $pemain, $skill, $target) {
        $s = rpg_get($s['id']);
        if (!$s || $s['status'] !== 'pilih') return [false, 'Waktu memilih sudah habis.', 'fase'];
        $g = db_row('SELECT * FROM rpg_giliran WHERE sesi_id=? AND ronde=? AND pemain_id=?', [$s['id'], $s['ronde'], $pemain['id']]);
        if (!$g) return [false, 'Karaktermu tidak ikut giliran ini.', 'fase'];
        if ((int)$g['dikunci']) return [true, '', ''];
        $units = rpg_units($s);
        list($ok, $pesan, $tg) = rpg_pilihan_valid($units, $units[(int)$g['u']], $skill, $target);
        if (!$ok) return [false, $pesan, 'pilihan'];
        db_q('UPDATE rpg_giliran SET skill=?, target=?, auto=0, dikunci=1 WHERE id=?', [(string)$skill, $tg, $g['id']]);
        return [true, '', ''];
    });
}

// ---------- Soal untuk seorang pemain & penilaian ----------
function rpg_soal_untuk($s, array $g)
{
    if ($g['soal_idx'] < 0) return null;
    $bank = rpg_bank($s);
    $it = $bank[(int)$g['soal_idx']] ?? null;
    if (!$it) return null;
    $kat = !empty($it['k']) ? ['kat' => $it['k']] : [];
    if ($it['t'] === 'isian') return ['t' => 'isian', 'q' => $it['q']] + $kat;
    $perm = json_decode((string)$g['perm'], true) ?: [];
    $o = [];
    foreach ($perm as $k) $o[] = $it['o'][$k];
    return ['t' => 'pg', 'q' => $it['q'], 'o' => $o] + $kat;
}

function rpg_nilai($s, array $g, $jawab)
{
    $it = rpg_bank($s)[(int)$g['soal_idx']] ?? null;
    if (!$it) return false;
    if ($it['t'] === 'isian') {
        $u = live_norm($jawab);
        if ($u === '') return false;
        foreach ($it['j'] as $j) if (live_norm($j) === $u) return true;
        return false;
    }
    $perm = json_decode((string)$g['perm'], true) ?: [];
    $k = (int)$jawab;
    return isset($perm[$k]) && $perm[$k] === (int)$it['b'];
}

// ---------- Penghitungan satu giliran ----------
function rpg_tutup($sid)
{
    live_tx(function () use ($sid) {
        $s = rpg_get($sid);
        if (!$s || $s['status'] !== 'soal') return;
        rpg_hitung_dalam($s);
    });
}

function rpg_acak_urut(array $a) { return live_acak($a); }

function rpg_hitung_dalam($s)
{
    $sid = (int)$s['id'];
    $r = (int)$s['ronde'];
    $cfg = rpg_cfg_sesi($s);
    $units = rpg_units($s);
    $rows = [];
    foreach (db_rows('SELECT * FROM rpg_giliran WHERE sesi_id=? AND ronde=?', [$sid, $r]) as $g) $rows[(int)$g['u']] = $g;

    // 1. aksi tiap karakter yang hidup di awal giliran
    $A = [];
    foreach ($units as $u) {
        if ($u['hp'] <= 0 || !isset($rows[$u['i']])) continue;
        $g = $rows[$u['i']];
        $A[$u['i']] = ['skill' => $g['skill'] ?: 'basic', 'target' => (int)$g['target'], 'ok' => (bool)$g['benar']];
    }
    // 2. Kutukan Mage (debuff) dihitung serempak sebelum skill lain berjalan, berdasarkan jawaban awal:
    //    - target kutukan yang berlaku langsung gagal (jawaban benar pun dianggap salah);
    //    - mage yang sendirinya dikutuk mage lain: kutukannya digagalkan, kecuali kedua mage saling mengutuk (keduanya terkutuk).
    $kutuk = [];
    foreach ($A as $i => $a) if ($a['ok'] && $units[$i]['peran'] === 'mage' && $a['skill'] === 's2' && isset($A[$a['target']])) $kutuk[$i] = (int)$a['target'];
    $berlaku = [];
    foreach ($kutuk as $c => $t) {
        $dikutuk = in_array($c, $kutuk, true);
        $saling = isset($kutuk[$t]) && $kutuk[$t] === $c;
        if ($dikutuk && !$saling) continue;
        $berlaku[$c] = $t;
    }
    foreach ($berlaku as $t) $A[$t]['ok'] = false;
    foreach ($units as &$u) $u['kutuk'] = 0;
    unset($u);

    $dipakai = [];            // skill yang dipakai (cooldown) : i => [id, ...]
    $bayang = [];             // assassin yang sedang bayangan giliran ini
    $lindung = [];            // i => pengali damage (Benteng Tim)
    $tguard = [];             // sekutu => indeks tank yang pasang badan untuk dia
    $fguard = [];             // sekutu => indeks fighter yang melompat melindunginya
    $fself = [];              // fighter yang menghindar (melindungi diri sendiri)
    $st1 = []; $st2 = []; $st3 = [];
    $hidupIdx = function ($t) use (&$units) { return isset($units[$t]) && $units[$t]['hp'] > 0; };

    // 3. tahap buff (perisai, lompat pelindung, bayangan, kutukan) dan kegagalan
    foreach ($A as $i => $a) {
        $u = $units[$i]; $sk = $a['skill']; $ok = $a['ok'];
        if ($sk === 's1' || $sk === 's2') {
            if (!($u['peran'] === 'assassin' && $sk === 's2')) $dipakai[$i][$sk] = true;
        }
        if ($sk === 'strike') $dipakai[$i]['s2'] = true;
        if (!$ok) { $st2[] = ['k' => 'gagal', 'u' => $i, 's' => $sk]; continue; }
        if ($u['peran'] === 'tank' && $sk === 's1') {
            $t = $a['target'];
            if ($t !== $i && $hidupIdx($t)) { $tguard[$t] = $i; $st1[] = ['k' => 'perisai', 'u' => $i, 'm' => 'satu', 't' => $t]; }
        } elseif ($u['peran'] === 'tank' && $sk === 's2') {
            foreach ($units as $x) if ($x['tim'] === $u['tim'] && $x['hp'] > 0) $lindung[$x['i']] = rpg_acak(RPG_BENTENG);   // acak untuk tiap target
            $st1[] = ['k' => 'perisai', 'u' => $i, 'm' => 'semua', 't' => -1];
        } elseif ($u['peran'] === 'fighter' && $sk === 's1') {
            $t = $a['target'];
            if ($t === $i) { $fself[$i] = true; $st1[] = ['k' => 'lompat', 'u' => $i, 'm' => 'diri', 't' => $i]; }
            elseif ($hidupIdx($t)) { $fguard[$t] = $i; $st1[] = ['k' => 'lompat', 'u' => $i, 'm' => 'teman', 't' => $t]; }
        } elseif ($u['peran'] === 'assassin' && $sk === 's2') {
            $bayang[$i] = true;
            $st1[] = ['k' => 'bayangan', 'u' => $i];
        } elseif ($u['peran'] === 'mage' && $sk === 's2') {
            $st1[] = ['k' => 'kutuk', 'u' => $i];      // target sengaja tidak dicatat (rahasia)
        }
    }
    // 4. heal
    foreach ($A as $i => $a) {
        $u = $units[$i];
        if (!$a['ok'] || $u['peran'] !== 'healer' || !in_array($a['skill'], ['s1', 's2'], true)) continue;
        $tl = $a['skill'] === 's1' ? [$a['target']] : array_values(array_map(function ($x) { return $x['i']; },
            array_filter($units, function ($x) use ($u) { return $x['tim'] === $u['tim'] && $x['hp'] > 0; })));
        $h = [];
        foreach ($tl as $t) {
            if (!isset($units[$t]) || $units[$t]['hp'] <= 0) continue;
            $amt = max(1, (int)round($u['atk'] * rpg_acak($a['skill'] === 's1' ? RPG_HEAL_1 : RPG_HEAL_SEMUA)));   // semua: acak tiap teman
            $baru = min($units[$t]['mx'], $units[$t]['hp'] + $amt);
            $h[] = ['u' => $t, 'n' => $baru - $units[$t]['hp'], 'hp' => $baru];
            $units[$t]['hp'] = $baru;
        }
        $st2[] = ['k' => 'heal', 'u' => $i, 'm' => $a['skill'] === 's1' ? 'satu' : 'semua', 'h' => $h];
    }

    // Damage yang tertuju ke $ti. $kot = serangan kotor (Attack x persen), $k = bagian Defend yang dipotong (1 = normal, kecil untuk
    // tiap pukulan Fighter). Tiap penerima memakai Defend-nya sendiri. Urutan: Benteng Tim -> Tank menerima 100% ->
    // Fighter melompat (teman 5-25%, fighter 50-70%; bila fighter dijaga tank, tank menerima bagian fighter itu 100%) -> Fighter menghindar (5-25%).
    $kurangi = function ($idx, $dmg) use (&$units) { $real = min($dmg, $units[$idx]['hp']); $units[$idx]['hp'] -= $real; return $real; };
    $hit = function ($ti, $kot, $k) use (&$units, &$tguard, &$fguard, &$fself, &$lindung, $kurangi, $hidupIdx) {
        $pr = $lindung[$ti] ?? 1;
        $net = function ($y) use (&$units, $kot, $k, $pr) { return max(1, (int)round(max(1, (int)round($kot - $units[$y]['def'] * $k)) * $pr)); };
        $gt = $tguard[$ti] ?? null;
        if ($gt !== null && $gt !== $ti && $hidupIdx($gt)) {
            $dt = $net($gt); $real = $kurangi($gt, $dt);
            $tk = ['u' => $gt, 'd' => $real, 'r' => $dt, 'hp' => $units[$gt]['hp']];
            if ($units[$gt]['hp'] <= 0) $tk['ko'] = 1;
            return ['u' => $ti, 'd' => 0, 'hp' => $units[$ti]['hp'], 'gd' => 1, 'tk' => $tk];
        }
        $fg = $fguard[$ti] ?? null;
        if ($fg !== null && $fg !== $ti && $hidupIdx($fg)) {
            $da = max(1, (int)round($net($ti) * rpg_acak(RPG_F_TEMAN)));
            $ra = $kurangi($ti, $da);
            $row = ['u' => $ti, 'd' => $ra, 'r' => $da, 'hp' => $units[$ti]['hp']];
            if ($units[$ti]['hp'] <= 0) $row['ko'] = 1;
            $pc = rpg_acak(RPG_F_DIRI);
            $gt2 = $tguard[$fg] ?? null;
            if ($gt2 !== null && $gt2 !== $fg && $hidupIdx($gt2)) {
                $bag = max(1, (int)round($net($gt2) * $pc)); $rt = $kurangi($gt2, $bag);
                $tk = ['u' => $gt2, 'd' => $rt, 'r' => $bag, 'hp' => $units[$gt2]['hp']];
                if ($units[$gt2]['hp'] <= 0) $tk['ko'] = 1;
                $row['fgd'] = ['u' => $fg, 'd' => 0, 'r' => $bag, 'hp' => $units[$fg]['hp'], 'tk' => $tk];
            } else {
                $bag = max(1, (int)round($net($fg) * $pc)); $rf = $kurangi($fg, $bag);
                $row['fgd'] = ['u' => $fg, 'd' => $rf, 'r' => $bag, 'hp' => $units[$fg]['hp']];
                if ($units[$fg]['hp'] <= 0) $row['fgd']['ko'] = 1;
            }
            return $row;
        }
        $raw = $net($ti);
        if (!empty($fself[$ti])) $raw = max(1, (int)round($raw * rpg_acak(RPG_F_MENGHINDAR)));
        $real = $kurangi($ti, $raw);
        $row = ['u' => $ti, 'd' => $real, 'r' => $raw, 'hp' => $units[$ti]['hp']];
        if ($pr < 1) $row['pr'] = $pr;
        if (!empty($fself[$ti])) $row['mh'] = 1;
        if ($units[$ti]['hp'] <= 0) $row['ko'] = 1;
        return $row;
    };
    $tembak = function ($ti, $kot, $k = 1, $n = 1) use (&$units, &$bayang, $hit) {
        $tu = $units[$ti];
        if (!empty($bayang[$ti])) return ['u' => $ti, 'd' => 0, 'hp' => $tu['hp'], 'bl' => 1, 'n' => $n];
        if ($tu['hp'] <= 0) return ['u' => $ti, 'd' => 0, 'hp' => 0, 'ko' => 1, 'sdh' => 1, 'n' => $n];
        $row = $hit($ti, $kot, $k);
        $row['n'] = $n;
        return $row;
    };

    // 5. serangan (urutan diacak, damage dihitung berurutan). Tiap serangan dilewatkan satu per satu melalui penjagaan
    //    (tank/fighter), jadi bila lawan menyerang lebih dari sekali, tank menerima semua damage itu satu per satu.
    $atk = [];
    foreach ($A as $i => $a) {
        $u = $units[$i];
        if (!$a['ok']) continue;
        $sk = $a['skill'];
        if ($sk === 'basic') $atk[] = [$i, 'basic', 'satu'];
        elseif ($u['peran'] === 'assassin' && $sk === 's1') $atk[] = [$i, 's1', 'satu'];
        elseif ($sk === 'strike') $atk[] = [$i, 'strike', 'satu'];
        elseif ($u['peran'] === 'mage' && $sk === 's1') $atk[] = [$i, 's1', 'semua'];
        elseif ($u['peran'] === 'fighter' && $sk === 's2') $atk[] = [$i, 'f2', 'kombo'];
    }
    $atk = rpg_acak_urut($atk);
    $beri = function (array $row, $pm, $peran, $sk) {      // catat persen damage; Critical khusus Assassin
        $row['pm'] = (int)round($pm * 100);
        if ($peran === 'assassin' && isset(RPG_CRIT[$sk]) && $pm > RPG_CRIT[$sk] && empty($row['bl']) && empty($row['sdh'])) $row['cr'] = 1;
        return $row;
    };
    foreach ($atk as $x) {
        list($i, $sk, $mode) = $x;
        $u = $units[$i];
        $t = [];
        if ($mode === 'semua') {            // Mage: tiap lawan mendapat persen acak sendiri
            foreach ($units as $y) {
                if ($y['tim'] === $u['tim'] || $y['hp'] <= 0) continue;
                $m = rpg_acak(RPG_AOE);
                $t[] = $beri($tembak($y['i'], $u['atk'] * $m), $m, $u['peran'], $sk);
            }
        } elseif ($mode === 'kombo') {      // Fighter: 4 pukulan (tiap 15-30%) ke lawan pertama, lalu 1 pukulan 45-65% ke lawan lain
            $t1 = $A[$i]['target'];
            $ms = []; for ($q = 0; $q < RPG_F_PUKUL_N; $q++) $ms[] = rpg_acak(RPG_F_PUKUL);
            $sum = array_sum($ms);
            foreach ($ms as $q => $m) {
                $row = $tembak($t1, $u['atk'] * $m, $m / $sum);          // tiap pukulan lewat penjagaan sendiri; Defend total dipotong sekali
                $row['hn'] = $q + 1; $row['pm'] = (int)round($m * 100);
                $t[] = $row;
            }
            $lain = array_values(array_map(function ($y) { return $y['i']; }, array_filter($units, function ($y) use ($u, $t1) { return $y['tim'] !== $u['tim'] && $y['hp'] > 0 && $y['i'] !== $t1; })));
            $t2 = $lain ? $lain[random_int(0, count($lain) - 1)] : $t1;
            $m2 = rpg_acak(RPG_F_SUSUL);
            $row = $tembak($t2, $u['atk'] * $m2); $row['hn'] = 5; $row['pm'] = (int)round($m2 * 100);
            $t[] = $row;
        } else {
            $ti = $A[$i]['target'];
            $m = $sk === 'basic' ? rpg_acak(rpg_basic_rentang($u['peran'])) : ($sk === 's1' ? rpg_acak(RPG_ASSASSIN_1) : rpg_acak(RPG_STRIKE));
            $t[] = $beri($tembak($ti, $u['atk'] * $m), $m, $u['peran'], $sk);
        }
        $st3[] = ['k' => 'serang', 'u' => $i, 's' => $sk, 'm' => $mode, 't' => $t];
    }
    $ev = array_merge(rpg_acak_urut($st1), rpg_acak_urut($st2), $st3);

    // 6. pingsan, cooldown, status bayangan
    $ko = [];
    foreach ($A as $i => $a) if ($units[$i]['hp'] <= 0) { $ko[] = $i; $ev[] = ['k' => 'ko', 'u' => $i]; }
    foreach ($units as &$u) {
        $i = $u['i'];
        foreach (['s1', 's2'] as $k) {
            if (!empty($dipakai[$i][$k])) {
                $kat = null;
                foreach (rpg_skill_katalog($u['peran'], $u['tim']) as $s0) if ($s0['id'] === $k) $kat = $s0;
                $u['cd'][$k] = (int)$kat['cd'];
            } elseif ($u['cd'][$k] > 0) $u['cd'][$k]--;
        }
        if (isset($A[$i])) {
            if ($A[$i]['skill'] === 'strike') $u['siap'] = 0;
            elseif ($A[$i]['ok'] && $u['peran'] === 'assassin' && $A[$i]['skill'] === 's2') $u['siap'] = 1;
        }
        if ($u['hp'] <= 0) { $u['siap'] = 0; $u['kutuk'] = 0; }
    }
    unset($u);

    // 7. simpan hasil tiap pemain
    foreach ($A as $i => $a) {
        db_q('UPDATE rpg_giliran SET sukses=? WHERE id=?', [$a['ok'] ? 1 : 0, $rows[$i]['id']]);
    }

    // 8. pemenang? durasi animasi
    $a1 = rpg_hidup($units, 1); $a2 = rpg_hidup($units, 2);
    $pending = '';
    if ($a1 === 0 || $a2 === 0) {
        $w = $a1 === $a2 ? 3 : ($a1 > 0 ? 1 : 2);
        $pending = json_encode(['pemenang' => $w, 'alasan' => $w === 3 ? 'seri' : 'habis']);
    }
    $dur = 1.5 + (count($st1) ? 2.4 : 0) + (count($st2) ? 2.4 : 0) + count($st3) * 2.7 + ($ko ? 1.8 : 0) + 2.0;
    foreach ($st3 as $e3) if ($e3['s'] === 'f2') $dur += 2.2;
    $dur = max(5.0, min(60.0, $dur));
    $riw = json_decode((string)$s['riwayat'], true) ?: [];
    $riw[] = ['r' => $r, 'ev' => $ev];
    $riw = array_slice($riw, -60);
    db_q("UPDATE rpg_sesi SET status='hasil', unit=?, kejadian=?, riwayat=?, pending=?, fase_mulai=?, fase_dur=? WHERE id=?",
        [json_encode($units), json_encode($ev), json_encode($riw), $pending, microtime(true), $dur, $sid]);
}

// hasil -> giliran berikutnya / selesai
function rpg_lanjut($sid)
{
    live_tx(function () use ($sid) {
        $s = rpg_get($sid);
        if (!$s || $s['status'] !== 'hasil') return;
        if ($s['pending'] !== '') {
            $p = json_decode($s['pending'], true) ?: [];
            rpg_selesai_dalam($s, (int)($p['pemenang'] ?? 3), (string)($p['alasan'] ?? 'seri'));
            return;
        }
        rpg_ronde_baru_dalam((int)$sid);
    });
}

function rpg_selesai_dalam($s, $pemenang, $alasan)
{
    $sid = (int)$s['id'];
    $units = rpg_units($s);
    $slot = rpg_slot_map($sid);
    if (!(int)$s['disimpan']) {
        foreach ($slot as $i => $p) {
            $st = db_row('SELECT COALESCE(SUM(benar),0) benar, COUNT(CASE WHEN soal_idx>=0 THEN 1 END) n, COALESCE(SUM(CASE WHEN benar=1 THEN ms ELSE 0 END),0) ms
                          FROM rpg_giliran WHERE sesi_id=? AND u=?', [$sid, $i]);
            $n = max(1, (int)$st['n']);
            $tag = rpg_peran_info()[$units[$i]['peran']]['nama'] . ' ' . rpg_tim_nama((int)$units[$i]['tim']);
            db_q('INSERT INTO skor(game_id,nama,skor,maks,durasi) VALUES(?,?,?,?,?)',
                [(int)$s['game_id'], mb_substr($p['nama'] . ' (' . $tag . ')', 0, 50), min((int)$st['benar'], $n), $n, (int)round($st['ms'] / 1000)]);
        }
    }
    $akhir = ['pemenang' => $pemenang, 'alasan' => $alasan, 'ronde' => (int)$s['ronde'],
        'hidup' => [1 => rpg_hidup($units, 1), 2 => rpg_hidup($units, 2)],
        'hp' => [1 => array_sum(array_map(function ($u) { return $u['tim'] === 1 ? max(0, $u['hp']) : 0; }, $units)),
                 2 => array_sum(array_map(function ($u) { return $u['tim'] === 2 ? max(0, $u['hp']) : 0; }, $units))]];
    db_q("UPDATE rpg_sesi SET status='selesai', pemenang=?, akhir=?, disimpan=1, pending='' WHERE id=?", [$pemenang, json_encode($akhir), $sid]);
}

function rpg_sisa_ms($s, $cfg)
{
    $st = $s['status'] === 'jeda' ? $s['sblm'] : $s['status'];
    if (!in_array($st, ['pilih', 'soal', 'hasil'], true)) return null;
    $ref = $s['status'] === 'jeda' ? (float)$s['jeda_at'] : microtime(true);
    $tot = $st === 'pilih' ? (int)$cfg['waktu_pilih'] : (float)$s['fase_dur'];
    return max(0, (int)round(($tot - ($ref - (float)$s['fase_mulai'])) * 1000));
}
function rpg_total_ms($s, $cfg)
{
    $st = $s['status'] === 'jeda' ? $s['sblm'] : $s['status'];
    if ($st === 'pilih') return (int)$cfg['waktu_pilih'] * 1000;
    if ($st === 'soal' || $st === 'hasil') return (int)round((float)$s['fase_dur'] * 1000);
    return 0;
}

// Perkiraan giliran yang masih bisa dimainkan dari sisa soal (jika semua masih hidup)
function rpg_perkiraan_ronde($bankN, $ulang)
{
    return $bankN * ($ulang + 1);
}

function rpg_peringkat($sid, $maks = 5)
{
    $out = [];
    $units = rpg_units(rpg_get($sid));
    foreach (rpg_slot_map($sid) as $i => $p) {
        $st = db_row('SELECT COALESCE(SUM(benar),0) benar, COALESCE(SUM(CASE WHEN benar=1 THEN ms ELSE 0 END),0) ms FROM rpg_giliran WHERE sesi_id=? AND u=?', [$sid, $i]);
        $out[] = ['nama' => $p['nama'], 'tim' => (int)$units[$i]['tim'], 'peran' => $units[$i]['peran'], 'benar' => (int)$st['benar'], 'ms' => (int)$st['ms']];
    }
    usort($out, function ($a, $b) { return [$b['benar'], $a['ms']] <=> [$a['benar'], $b['ms']]; });
    return array_map(function ($p) { unset($p['ms']); return $p; }, array_slice($out, 0, $maks));
}
