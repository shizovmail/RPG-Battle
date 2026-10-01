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
//    soal   -> tiap murid menjawab satu soal (batas waktu mengikuti soal / waktu universal)
//    hasil  -> server menghitung semua aksi, layar proyektor memainkan animasinya
// =====================================================
require_once __DIR__ . '/live.php';   // memakai live_norm, live_acak, live_tx

const RPG_BASIC = 0.5;        // serangan dasar = 50% attack
const RPG_AOE = 1.0;          // skill 1 mage (area) = 100% attack
const RPG_ASSASSIN_1 = 1.0;   // skill 1 assassin = 100% attack, 1 target
const RPG_STRIKE = 2.75;      // serangan bayangan assassin = 275% attack
const RPG_LINDUNG_1 = 0.10;   // tank melindungi 1 anggota: damage masuk 10%
const RPG_LINDUNG_SEMUA = 0.30; // tank melindungi semua: damage masuk 30%
const RPG_HEAL_SEMUA = 0.55;  // heal semua = 55% heal satu anggota (per anggota)
const RPG_KUTUK_GAGAL = 75;   // peluang (%) jawaban benar dianggap gagal saat dikutuk

function rpg_peran_list() { return ['tank', 'assassin', 'mage', 'healer']; }
function rpg_peran_info()
{
    return [
        'tank' => ['nama' => 'Tank', 'ikon' => '🛡️'],
        'assassin' => ['nama' => 'Assassin', 'ikon' => '🗡️'],
        'mage' => ['nama' => 'Mage', 'ikon' => '🔮'],
        'healer' => ['nama' => 'Healer', 'ikon' => '✨'],
    ];
}
function rpg_tim_nama($t) { return $t === 1 ? 'Kiri' : ($t === 2 ? 'Kanan' : ''); }
function rpg_unit_idx($tim, $peran)
{
    $k = array_search($peran, rpg_peran_list(), true);
    if ($k === false || !in_array((int)$tim, [1, 2], true)) return -1;
    return ((int)$tim - 1) * 4 + $k;
}

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
        'tank'     => ['hp' => 300, 'atk' => 24, 'def' => 12, 'heal' => 0],
        'assassin' => ['hp' => 140, 'atk' => 75, 'def' => 5,  'heal' => 0],
        'mage'     => ['hp' => 160, 'atk' => 55, 'def' => 6,  'heal' => 0],
        'healer'   => ['hp' => 170, 'atk' => 30, 'def' => 8,  'heal' => 40],
    ];
}

function rpg_cfg_bawaan()
{
    return ['waktu_pilih' => 12, 'sumber_waktu' => 'soal', 'waktu_universal' => 15, 'ulang' => 2,
        'lanjut_otomatis' => true, 'acak_opsi' => true, 'peringkat' => false, 'stat' => rpg_stat_bawaan()];
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
    foreach (['lanjut_otomatis', 'acak_opsi', 'peringkat'] as $k) {
        $o[$k] = array_key_exists($k, $raw) ? !empty($raw[$k]) : $d[$k];
    }
    $o['stat'] = [];
    $batas = ['hp' => [20, 5000], 'atk' => [1, 500], 'def' => [0, 300], 'heal' => [0, 1000]];
    foreach (rpg_peran_list() as $p) {
        foreach ($batas as $k => $b) {
            $v = $raw['stat'][$p][$k] ?? $d['stat'][$p][$k];
            $o['stat'][$p][$k] = max($b[0], min($b[1], (int)$v));
        }
    }
    return $o;
}

// ---------- Soal ----------
function rpg_parse_soal($raw, array $jenis, &$err)
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
            $out[] = ['t' => 'pg', 'q' => $q, 'o' => $opsi, 'b' => max(0, $benar), 'w' => $w];
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
            $out[] = ['t' => 'isian', 'q' => $q, 'j' => $lst, 'w' => $w];
        }
    }
    if (count($out) < 8) $err[] = 'Minimal harus ada 8 soal (satu giliran memakai satu soal untuk tiap pemain).';
    if (count($out) > 500) $err[] = 'Maksimal 500 soal.';
    return $out;
}

// ---------- Skill ----------
// tgt: musuh = pilih 1 lawan, sekutu = pilih 1 anggota tim sendiri (boleh diri sendiri), tidak = tanpa target
function rpg_skill_katalog($peran, $tim = 1)
{
    $basic = ['id' => 'basic', 'nama' => 'Serangan Dasar', 'ikon' => '⚔️', 'tgt' => 'musuh', 'cd' => 0,
        'desc' => 'Menyerang 1 lawan, damage kecil. Tanpa cooldown.'];
    switch ($peran) {
        case 'tank':
            return [
                ['id' => 's1', 'nama' => 'Perisai Pelindung', 'ikon' => '🛡️', 'tgt' => 'sekutu', 'cd' => 0,
                    'desc' => 'Lindungi 1 anggota tim (boleh diri sendiri): damage lawan yang masuk hanya 10%. Tanpa cooldown.'],
                ['id' => 's2', 'nama' => 'Benteng Tim', 'ikon' => '🏰', 'tgt' => 'tidak', 'cd' => 2,
                    'desc' => 'Lindungi SEMUA anggota tim: damage lawan yang masuk hanya 30%. Cooldown 2 giliran.'],
                $basic,
            ];
        case 'healer':
            return [
                ['id' => 's1', 'nama' => 'Penyembuhan', 'ikon' => '💚', 'tgt' => 'sekutu', 'cd' => 2,
                    'desc' => 'Pulihkan HP 1 anggota tim (boleh diri sendiri). Cooldown 2 giliran.'],
                ['id' => 's2', 'nama' => 'Hujan Cahaya', 'ikon' => '🌟', 'tgt' => 'tidak', 'cd' => 3,
                    'desc' => 'Pulihkan HP SEMUA anggota tim, lebih sedikit dari penyembuhan tunggal. Cooldown 3 giliran.'],
                $basic,
            ];
        case 'assassin':
            return [
                ['id' => 's1', 'nama' => 'Tusukan Mematikan', 'ikon' => '🗡️', 'tgt' => 'musuh', 'cd' => 2,
                    'desc' => 'Damage 100% ke 1 lawan (damage terbesar di antara semua peran). Cooldown 2 giliran.'],
                ['id' => 's2', 'nama' => 'Bayangan', 'ikon' => '👤', 'tgt' => 'tidak', 'cd' => 3,
                    'desc' => 'Menghilang (tak bisa diserang giliran ini). Giliran berikutnya, jika benar lagi, serang 1 lawan dengan damage 275%! Harus benar 2 kali berturut-turut. Cooldown 3 giliran.'],
                $basic,
            ];
        case 'mage':
            $s1 = $tim === 2
                ? ['nama' => 'Badai Es', 'ikon' => '❄️', 'desc' => 'Menjatuhkan batu es ke SEMUA lawan, damage 100% attack. Cooldown 3 giliran.']
                : ['nama' => 'Hujan Meteor', 'ikon' => '☄️', 'desc' => 'Menjatuhkan meteor ke SEMUA lawan, damage 100% attack. Cooldown 3 giliran.'];
            return [
                ['id' => 's1', 'nama' => $s1['nama'], 'ikon' => $s1['ikon'], 'tgt' => 'tidak', 'cd' => 3, 'desc' => $s1['desc']],
                ['id' => 's2', 'nama' => 'Kutukan', 'ikon' => '💀', 'tgt' => 'musuh', 'cd' => 2,
                    'desc' => 'Kutuk 1 lawan: giliran berikutnya 75% peluang ia GAGAL walau menjawab benar. Lawan tidak tahu siapa yang dikutuk. Cooldown 2 giliran.'],
                $basic,
            ];
    }
    return [$basic];
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
                    'desc' => 'Muncul dari bayangan dan serang 1 lawan dengan damage 275% attack!'];
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
        if (($sk['tgt'] === 'sekutu') === $sama) $out[] = (int)$x['i'];
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
    $ada = array_values(array_filter(rpg_skill_tersedia($u), function ($s) { return $s['ok']; }));
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

// indeks unit => baris pemain (hanya yang tim & perannya sah; jika ganda, yang pertama)
function rpg_slot_map($sid)
{
    $m = [];
    foreach (rpg_pemain_semua($sid) as $p) {
        $i = rpg_unit_idx($p['tim'], $p['peran']);
        if ($i >= 0 && !isset($m[$i])) $m[$i] = $p;
    }
    ksort($m);
    return $m;
}

function rpg_bisa_mulai($sid)
{
    $m = rpg_slot_map($sid);
    $kosong = [];
    for ($i = 0; $i < 8; $i++) {
        if (!isset($m[$i])) {
            $pi = rpg_peran_info()[rpg_peran_list()[$i % 4]];
            $kosong[] = $pi['nama'] . ' ' . rpg_tim_nama(intdiv($i, 4) + 1);
        }
    }
    if ($kosong) return [false, 'Slot belum terisi: ' . implode(', ', $kosong) . '. Pilih murid untuk tiap peran atau tekan "Acak peran".'];
    return [true, ''];
}

function rpg_buat_unit(array $cfg)
{
    $u = [];
    foreach ([1, 2] as $t) {
        foreach (rpg_peran_list() as $k => $peran) {
            $st = $cfg['stat'][$peran];
            $u[] = ['i' => ($t - 1) * 4 + $k, 'tim' => $t, 'peran' => $peran, 'hp' => (int)$st['hp'], 'mx' => (int)$st['hp'],
                'atk' => (int)$st['atk'], 'def' => (int)$st['def'], 'heal' => (int)$st['heal'],
                'cd' => ['s1' => 0, 's2' => 0], 'kutuk' => 0, 'siap' => 0];
        }
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
        $antrian = [];
        for ($p = 0; $p <= (int)$cfg['ulang']; $p++) $antrian = array_merge($antrian, live_acak($n ? range(0, $n - 1) : []));
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
    $ant = json_decode((string)$s['antrian'], true) ?: [];
    if ((int)$s['ptr'] + count($hidup) > count($ant)) {
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
        $ant = json_decode((string)$s['antrian'], true) ?: [];
        $ptr = (int)$s['ptr'];
        $rows = db_rows('SELECT * FROM rpg_giliran WHERE sesi_id=? AND ronde=? ORDER BY id', [$sid, $s['ronde']]);
        shuffle($rows);   // pembagian soal acak antar pemain
        $maks = 5;
        foreach ($rows as $g) {
            $u = $units[(int)$g['u']];
            if (!(int)$g['dikunci']) {
                list($sk, $tg) = rpg_pilihan_acak($units, $u);
                db_q('UPDATE rpg_giliran SET skill=?, target=?, auto=1, dikunci=1 WHERE id=?', [$sk, $tg, $g['id']]);
            }
            $idx = $ant[$ptr++];
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
        db_q("UPDATE rpg_sesi SET status='soal', ptr=?, fase_mulai=?, fase_dur=? WHERE id=?", [$ptr, microtime(true), $maks, $sid]);
    });
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
    if ($it['t'] === 'isian') return ['t' => 'isian', 'q' => $it['q']];
    $perm = json_decode((string)$g['perm'], true) ?: [];
    $o = [];
    foreach ($perm as $k) $o[] = $it['o'][$k];
    return ['t' => 'pg', 'q' => $it['q'], 'o' => $o];
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
    // 2. kutukan giliran lalu: jawaban benar bisa dianggap gagal
    foreach ($A as $i => &$a) {
        if (!empty($units[$i]['kutuk']) && $a['ok'] && random_int(1, 100) <= RPG_KUTUK_GAGAL) $a['ok'] = false;
    }
    unset($a);
    foreach ($units as &$u) $u['kutuk'] = 0;
    unset($u);

    $dipakai = [];            // skill yang dipakai (cooldown) : i => [id, ...]
    $bayang = [];             // assassin yang sedang bayangan giliran ini
    $lindung = [];            // i => pengali damage terkecil
    $kutukBaru = [];
    $st1 = []; $st2 = []; $st3 = [];

    // 3. tahap buff (perisai, bayangan, kutukan) dan kegagalan
    foreach ($A as $i => $a) {
        $u = $units[$i]; $sk = $a['skill']; $ok = $a['ok'];
        if ($sk === 's1' || $sk === 's2') {
            if (!($u['peran'] === 'assassin' && $sk === 's2')) $dipakai[$i][$sk] = true;
        }
        if ($sk === 'strike') $dipakai[$i]['s2'] = true;
        if (!$ok) { $st2[] = ['k' => 'gagal', 'u' => $i, 's' => $sk]; continue; }
        if ($u['peran'] === 'tank' && $sk === 's1') {
            $t = $a['target'];
            $lindung[$t] = min($lindung[$t] ?? 1, RPG_LINDUNG_1);
            $st1[] = ['k' => 'perisai', 'u' => $i, 'm' => 'satu', 't' => $t];
        } elseif ($u['peran'] === 'tank' && $sk === 's2') {
            foreach ($units as $x) if ($x['tim'] === $u['tim'] && $x['hp'] > 0) $lindung[$x['i']] = min($lindung[$x['i']] ?? 1, RPG_LINDUNG_SEMUA);
            $st1[] = ['k' => 'perisai', 'u' => $i, 'm' => 'semua', 't' => -1];
        } elseif ($u['peran'] === 'assassin' && $sk === 's2') {
            $bayang[$i] = true;
            $st1[] = ['k' => 'bayangan', 'u' => $i];
        } elseif ($u['peran'] === 'mage' && $sk === 's2') {
            $kutukBaru[] = $a['target'];
            $st1[] = ['k' => 'kutuk', 'u' => $i];      // target sengaja tidak dicatat (rahasia)
        }
    }
    // 4. heal
    foreach ($A as $i => $a) {
        $u = $units[$i];
        if (!$a['ok'] || $u['peran'] !== 'healer' || !in_array($a['skill'], ['s1', 's2'], true)) continue;
        $tl = $a['skill'] === 's1' ? [$a['target']] : array_values(array_map(function ($x) { return $x['i']; },
            array_filter($units, function ($x) use ($u) { return $x['tim'] === $u['tim'] && $x['hp'] > 0; })));
        $amt = $a['skill'] === 's1' ? (int)$u['heal'] : (int)round($u['heal'] * RPG_HEAL_SEMUA);
        $h = [];
        foreach ($tl as $t) {
            if ($units[$t]['hp'] <= 0) continue;
            $baru = min($units[$t]['mx'], $units[$t]['hp'] + $amt);
            $h[] = ['u' => $t, 'n' => $baru - $units[$t]['hp'], 'hp' => $baru];
            $units[$t]['hp'] = $baru;
        }
        $st2[] = ['k' => 'heal', 'u' => $i, 'm' => $a['skill'] === 's1' ? 'satu' : 'semua', 'h' => $h];
    }
    // 5. serangan (urutan diacak, damage dihitung berurutan)
    $atk = [];
    foreach ($A as $i => $a) {
        $u = $units[$i];
        if (!$a['ok']) continue;
        $sk = $a['skill'];
        if ($sk === 'basic') $atk[] = [$i, 'basic', RPG_BASIC, 'satu'];
        elseif ($u['peran'] === 'assassin' && $sk === 's1') $atk[] = [$i, 's1', RPG_ASSASSIN_1, 'satu'];
        elseif ($sk === 'strike') $atk[] = [$i, 'strike', RPG_STRIKE, 'satu'];
        elseif ($u['peran'] === 'mage' && $sk === 's1') $atk[] = [$i, 's1', RPG_AOE, 'semua'];
    }
    $atk = rpg_acak_urut($atk);
    foreach ($atk as $x) {
        list($i, $sk, $mult, $mode) = $x;
        $u = $units[$i];
        $tl = $mode === 'semua'
            ? array_values(array_map(function ($y) { return $y['i']; }, array_filter($units, function ($y) use ($u) { return $y['tim'] !== $u['tim'] && $y['hp'] > 0; })))
            : [$A[$i]['target']];
        $t = [];
        foreach ($tl as $ti) {
            $tu = $units[$ti];
            if (!empty($bayang[$ti])) { $t[] = ['u' => $ti, 'd' => 0, 'hp' => $tu['hp'], 'bl' => 1]; continue; }
            if ($tu['hp'] <= 0) { $t[] = ['u' => $ti, 'd' => 0, 'hp' => 0, 'ko' => 1, 'sdh' => 1]; continue; }
            $d = max(1, (int)round($u['atk'] * $mult - $tu['def']));
            $pr = $lindung[$ti] ?? 1;
            if ($pr < 1) $d = max(1, (int)round($d * $pr));
            $units[$ti]['hp'] = max(0, $tu['hp'] - $d);
            $row = ['u' => $ti, 'd' => min($d, $tu['hp']), 'r' => $d, 'hp' => $units[$ti]['hp']];
            if ($pr < 1) $row['pr'] = $pr;
            if ($units[$ti]['hp'] <= 0) $row['ko'] = 1;
            $t[] = $row;
        }
        $st3[] = ['k' => 'serang', 'u' => $i, 's' => $sk, 'm' => $mode, 't' => $t];
    }
    $ev = array_merge(rpg_acak_urut($st1), rpg_acak_urut($st2), $st3);

    // 6. pingsan, kutukan baru, cooldown, status bayangan
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
    foreach ($kutukBaru as $t) if (isset($units[$t]) && $units[$t]['hp'] > 0) $units[$t]['kutuk'] = 1;

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
function rpg_perkiraan_ronde($bankN, $ulang, $hidup = 8)
{
    return $hidup > 0 ? intdiv($bankN * ($ulang + 1), $hidup) : 0;
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
