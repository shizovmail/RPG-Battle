<?php
ini_set('display_errors', '0');   // peringatan PHP tidak boleh ikut tercetak ke JSON (XAMPP biasanya menyalakan display_errors)
// API game live RPG Battle. Bagian murid tanpa login; bagian guru butuh sesi login + pemilik game.
require __DIR__ . '/config.php';
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/rpg.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function gagal($pesan, $kode = '') { out(['ok' => false, 'pesan' => $pesan, 'kode' => $kode]); }
function esc_nama($n) { return '"' . $n . '"'; }
function masuk_json()
{
    $in = json_decode((string)file_get_contents('php://input'), true);
    return is_array($in) ? $in : [];
}

rpg_db();
$a = $_GET['a'] ?? '';
$post = $_SERVER['REQUEST_METHOD'] === 'POST';

// =====================================================
//  BAGIAN GURU
// =====================================================
if (in_array($a, ['gstate', 'riwayat', 'aksi'], true)) {
    require __DIR__ . '/inc/auth.php';
    start_session();
    $u = current_user();
    if (!$u || $u['role'] !== 'guru') { http_response_code(401); gagal('Sesi login habis. Muat ulang halaman dan masuk lagi.', 'login'); }
    $game = db_row('SELECT * FROM games WHERE id=? AND user_id=?', [(int)($_GET['game'] ?? 0), $u['id']]);
    if (!$game) gagal('Game tidak ditemukan.');
    if ($a !== 'aksi') session_write_close();   // jangan mengunci sesi login selama polling layar proyektor

    if ($a === 'gstate') {
        $s = rpg_sesi_aktif($game['id']) ?: rpg_sesi_terakhir($game['id']);
        if ($s) $s = rpg_tick($s['id']);
        out(rpg_gstate($game, $s));
    }
    if ($a === 'riwayat') {
        $s = rpg_sesi_aktif($game['id']) ?: rpg_sesi_terakhir($game['id']);
        out(['ok' => true, 'riwayat' => $s ? (json_decode((string)$s['riwayat'], true) ?: []) : []]);
    }

    if (!$post) gagal('Permintaan tidak valid.');
    $csrfOk = hash_equals(csrf_token(), (string)($_SERVER['HTTP_X_CSRF'] ?? ''));
    session_write_close();
    if (!$csrfOk) { http_response_code(400); gagal('Sesi formulir kedaluwarsa. Muat ulang halaman.'); }
    $in = masuk_json();
    $do = (string)($in['do'] ?? '');
    $s = rpg_sesi_aktif($game['id']);
    if ($s) $s = rpg_tick($s['id']);
    $sid = $s ? (int)$s['id'] : 0;

    if ($do === 'sesi_baru') {
        db_q("UPDATE rpg_sesi SET status='selesai', akhir=? WHERE game_id=? AND status<>'selesai'", [json_encode(['batal' => true]), (int)$game['id']]);
        rpg_buat_sesi($game);
    } elseif (!$s) {
        gagal('Belum ada sesi. Buat sesi baru dulu.');
    } elseif ($do === 'atur') {
        if ($s['status'] !== 'lobi') gagal('Peran hanya bisa diatur saat masih di lobi.');
        $p = db_row('SELECT * FROM rpg_pemain WHERE id=? AND sesi_id=?', [(int)($in['p'] ?? 0), $sid]);
        if (!$p) gagal('Murid tidak ditemukan.');
        $tim = (int)($in['tim'] ?? 0);
        $peran = (string)($in['peran'] ?? '');
        live_tx(function () use ($sid, $p, $tim, $peran) {
            if ($tim === 0 || !in_array($peran, rpg_peran_semua(), true) || !in_array($tim, [1, 2], true)) {
                db_q("UPDATE rpg_pemain SET tim=0, peran='' WHERE id=?", [$p['id']]);
                return;
            }
            $lama = db_row('SELECT * FROM rpg_pemain WHERE sesi_id=? AND tim=? AND peran=? AND id<>?', [$sid, $tim, $peran, $p['id']]);
            if ($lama) db_q('UPDATE rpg_pemain SET tim=?, peran=? WHERE id=?', [(int)$p['tim'], (string)$p['peran'], $lama['id']]); // tukar tempat
            db_q('UPDATE rpg_pemain SET tim=?, peran=? WHERE id=?', [$tim, $peran, $p['id']]);
        });
    } elseif ($do === 'acak') {
        if ($s['status'] !== 'lobi') gagal('Peran hanya bisa diatur saat masih di lobi.');
        $jml = rpg_jumlah_unit(rpg_cfg_sesi($s));
        live_tx(function () use ($sid, $jml) {
            $semua = live_acak(rpg_pemain_semua($sid));
            db_q("UPDATE rpg_pemain SET tim=0, peran='' WHERE sesi_id=?", [$sid]);
            foreach ($semua as $k => $p) {
                if ($k >= $jml) break;
                db_q('UPDATE rpg_pemain SET tim=?, peran=? WHERE id=?', [rpg_unit_tim($k), rpg_unit_peran($k), $p['id']]);
            }
        });
    } elseif ($do === 'keluarkan') {
        $pid = (int)($in['p'] ?? 0);
        $p = db_row('SELECT * FROM rpg_pemain WHERE id=? AND sesi_id=?', [$pid, $sid]);
        if (!$p) gagal('Murid tidak ditemukan.');
        if ($s['status'] !== 'lobi' && rpg_unit_idx($p['tim'], $p['peran']) >= 0) gagal('Murid ini sedang bermain. Gunakan "Ganti pemain" agar slotnya diisi murid lain.');
        db_q('DELETE FROM rpg_pemain WHERE id=?', [$pid]);
    } elseif ($do === 'ganti') {
        $i = (int)($in['u'] ?? -1);
        $baru = db_row('SELECT * FROM rpg_pemain WHERE id=? AND sesi_id=?', [(int)($in['p'] ?? 0), $sid]);
        if ($i < 0 || $i >= rpg_jumlah_unit(rpg_cfg_sesi($s)) || !$baru) gagal('Data penggantian tidak valid.');
        $slot = rpg_slot_map($sid);
        if (rpg_unit_idx($baru['tim'], $baru['peran']) >= 0) gagal('Murid pengganti harus penonton (belum punya peran).');
        $tim = rpg_unit_tim($i); $peran = rpg_unit_peran($i);
        live_tx(function () use ($sid, $s, $i, $slot, $baru, $tim, $peran) {
            if (isset($slot[$i])) db_q("UPDATE rpg_pemain SET tim=0, peran='' WHERE id=?", [$slot[$i]['id']]);
            db_q('UPDATE rpg_pemain SET tim=?, peran=? WHERE id=?', [$tim, $peran, $baru['id']]);
            db_q('UPDATE rpg_giliran SET pemain_id=? WHERE sesi_id=? AND ronde=? AND u=?', [$baru['id'], $sid, (int)$s['ronde'], $i]);
        });
    } elseif ($do === 'cfg') {
        if ($s['status'] !== 'lobi') gagal('Pengaturan hanya bisa diubah saat masih di lobi.');
        $baruCfg = rpg_cfg_bersih($in['cfg'] ?? []);
        $baruCfg['pilih_mandiri'] = rpg_cfg_sesi($s)['pilih_mandiri'];     // diatur lewat tombol sendiri (aksi "mandiri")
        db_q('UPDATE rpg_sesi SET cfg=? WHERE id=?', [json_encode($baruCfg), $sid]);
    } elseif ($do === 'mandiri') {
        if ($s['status'] !== 'lobi') gagal('Mode pilih peran hanya bisa diubah saat masih di lobi.');
        $on = !empty($in['nilai']);
        $c = rpg_cfg_sesi($s); $c['pilih_mandiri'] = $on;
        live_tx(function () use ($sid, $c, $on) {
            db_q('UPDATE rpg_sesi SET cfg=? WHERE id=?', [json_encode($c), $sid]);
            if (!$on) db_q("UPDATE rpg_pemain SET tim=0, peran='' WHERE sesi_id=?", [$sid]);   // semua kembali ke lobi; guru yang mengatur
        });
    } elseif ($do === 'mulai') {
        if ($s['status'] !== 'lobi') gagal('Pertandingan sudah dimulai.');
        list($ok, $alasan) = rpg_bisa_mulai($sid);
        if (!$ok) gagal($alasan);
        $bank = count((json_decode((string)$game['data'], true) ?: [])['soal'] ?? []);
        if ($bank < 8) gagal('Bank soal kurang dari 8 soal.');
        rpg_mulai($game, $s);
    } elseif ($do === 'tutup_pilih') {
        if ($s['status'] !== 'pilih') gagal('Tahap memilih tidak sedang berjalan.');
        rpg_mulai_soal($sid);
    } elseif ($do === 'tutup') {
        if ($s['status'] !== 'soal') gagal('Tahap menjawab tidak sedang berjalan.');
        rpg_tutup($sid);
    } elseif ($do === 'lanjut') {
        if ($s['status'] !== 'hasil') gagal('Belum waktunya lanjut.');
        rpg_lanjut($sid);
    } elseif ($do === 'jeda') {
        if (!in_array($s['status'], ['pilih', 'soal', 'hasil'], true)) gagal('Game tidak bisa dijeda sekarang.');
        db_q("UPDATE rpg_sesi SET status='jeda', sblm=?, jeda_at=? WHERE id=?", [$s['status'], microtime(true), $sid]);
    } elseif ($do === 'lanjut_jeda') {
        if ($s['status'] !== 'jeda') gagal('Game tidak sedang dijeda.');
        $d = microtime(true) - (float)$s['jeda_at'];
        db_q("UPDATE rpg_sesi SET status=sblm, fase_mulai=fase_mulai+?, sblm='' WHERE id=?", [$d, $sid]);
    } elseif ($do === 'akhiri') {
        if (!in_array($s['status'], ['pilih', 'soal', 'hasil', 'jeda'], true)) gagal('Game belum berjalan.');
        live_tx(function () use ($sid) {
            $x = rpg_get($sid);
            list($w, $alasan) = rpg_pemenang_akhir(rpg_units($x));
            rpg_selesai_dalam($x, $w, 'guru_' . $alasan);
        });
    } else {
        gagal('Perintah tidak dikenal.');
    }
    $s2 = rpg_sesi_aktif($game['id']) ?: rpg_sesi_terakhir($game['id']);
    out(rpg_gstate($game, $s2));
}

// =====================================================
//  BAGIAN MURID
// =====================================================
if ($a === 'cek') {
    $g = db_row('SELECT id, aktif FROM games WHERE id=?', [(int)($_GET['id'] ?? 0)]);
    if (!$g) out(['ada' => false]);
    $s = rpg_sesi_aktif($g['id']);
    if (!$s) { $t = rpg_sesi_terakhir($g['id']); out(['ada' => true, 'aktif' => (bool)$g['aktif'], 'sesi' => null, 'terakhir' => $t ? (int)$t['id'] : 0]); }
    out(['ada' => true, 'aktif' => (bool)$g['aktif'], 'sesi' => ['id' => (int)$s['id'], 'status' => $s['status']]]);
}

if ($a === 'gabung' && $post) {
    $in = masuk_json();
    $g = db_row('SELECT id, token, aktif FROM games WHERE id=?', [(int)($in['id'] ?? 0)]);
    if (!$g || !hash_equals($g['token'], (string)($in['token'] ?? ''))) gagal('Game tidak dikenali.');
    if (!$g['aktif']) gagal('Game sedang ditutup oleh guru.');
    $s = rpg_sesi_aktif($g['id']);
    if (!$s) gagal('Belum ada sesi yang dibuka. Tunggu guru membuka lobi.', 'belum');
    $nama = mb_substr(trim(preg_replace('/\s+/', ' ', (string)($in['nama'] ?? ''))), 0, 40);
    if (mb_strlen($nama) < 2) gagal('Tulis namamu (minimal 2 huruf).');
    $ptoken = (string)($in['ptoken'] ?? '');
    $now = microtime(true);
    $ada = null;
    foreach (rpg_pemain_semua($s['id']) as $p) {
        if (mb_strtolower($p['nama'], 'UTF-8') === mb_strtolower($nama, 'UTF-8')) { $ada = $p; break; }
    }
    if ($ada) {
        if ($ptoken !== '' && hash_equals($ada['token'], $ptoken)) {
            $tok = $ada['token'];
        } elseif ($now - (float)$ada['seen'] > 8) {           // pemilik lama sudah putus: boleh masuk kembali
            $tok = bin2hex(random_bytes(8));
            db_q('UPDATE rpg_pemain SET token=?, seen=? WHERE id=?', [$tok, $now, $ada['id']]);
        } else {
            gagal('Nama itu sudah dipakai murid lain yang sedang online. Pilih atau tulis nama yang berbeda.', 'nama');
        }
    } else {
        if ((int)db_val('SELECT COUNT(*) FROM rpg_pemain WHERE sesi_id=?', [$s['id']]) >= 100) gagal('Lobi penuh.');
        $tok = bin2hex(random_bytes(8));
        db_q('INSERT INTO rpg_pemain(sesi_id,token,nama,seen) VALUES(?,?,?,?)', [$s['id'], $tok, $nama, $now]);
    }
    out(['ok' => true, 'sesi' => (int)$s['id'], 'p' => $tok, 'nama' => $nama]);
}

if ($a === 'state') {
    $sid = (int)($_GET['sesi'] ?? 0);
    $p = db_row('SELECT * FROM rpg_pemain WHERE sesi_id=? AND token=?', [$sid, (string)($_GET['p'] ?? '')]);
    if (!$p) gagal('Kamu tidak ada di sesi ini.', 'keluar');
    $now = microtime(true);
    if ($now - (float)$p['seen'] > 2.5) db_q('UPDATE rpg_pemain SET seen=? WHERE id=?', [$now, $p['id']]);
    $s = rpg_tick($sid);
    if (!$s) gagal('Sesi tidak ditemukan.', 'keluar');
    out(rpg_murid_state($s, $p));
}

// Murid memilih / melepas peran sendiri di lobi (bila guru mengizinkan)
if ($a === 'peran' && $post) {
    $in = masuk_json();
    $sid = (int)($in['sesi'] ?? 0);
    $p = db_row('SELECT * FROM rpg_pemain WHERE sesi_id=? AND token=?', [$sid, (string)($in['p'] ?? '')]);
    if (!$p) gagal('Kamu tidak ada di sesi ini.', 'keluar');
    $res = live_tx(function () use ($sid, $p, $in) {
        $s = rpg_get($sid);
        if (!$s) return ['Sesi tidak ditemukan.', 'keluar'];
        if ($s['status'] !== 'lobi') return ['Pertandingan sudah dimulai, peran tidak bisa diubah lagi.', 'fase'];
        $cfg = rpg_cfg_sesi($s);
        if (empty($cfg['pilih_mandiri'])) return ['Guru yang mengatur peran di pertandingan ini.', 'kunci'];
        if (($in['do'] ?? '') === 'lepas') {
            db_q("UPDATE rpg_pemain SET tim=0, peran='' WHERE id=?", [$p['id']]);
            return null;
        }
        $tim = (int)($in['tim'] ?? 0); $peran = (string)($in['peran'] ?? '');
        if (!in_array($tim, [1, 2], true) || !in_array($peran, rpg_peran_semua(), true) || ($peran === 'fighter' && empty($cfg['pakai_fighter']))) return ['Peran tidak valid.', ''];
        $i = rpg_unit_idx($tim, $peran);
        $slot = rpg_slot_map($sid);
        if (isset($slot[$i]) && (int)$slot[$i]['id'] !== (int)$p['id']) return [esc_nama($slot[$i]['nama']) . ' sudah memegang peran itu. Minta ia keluar dulu, atau pilih peran lain.', 'penuh'];
        db_q('UPDATE rpg_pemain SET tim=?, peran=? WHERE id=?', [$tim, $peran, $p['id']]);
        return null;
    });
    if ($res) gagal($res[0], $res[1]);
    $p = db_row('SELECT * FROM rpg_pemain WHERE id=?', [$p['id']]);
    out(rpg_murid_state(rpg_get($sid), $p));
}

if ($a === 'pilih' && $post) {
    $in = masuk_json();
    $sid = (int)($in['sesi'] ?? 0);
    $p = db_row('SELECT * FROM rpg_pemain WHERE sesi_id=? AND token=?', [$sid, (string)($in['p'] ?? '')]);
    if (!$p) gagal('Kamu tidak ada di sesi ini.', 'keluar');
    $s = rpg_tick($sid);
    if (!$s || $s['status'] !== 'pilih') gagal('Waktu memilih sudah habis.', 'fase');
    if ((int)($in['ronde'] ?? 0) !== (int)$s['ronde']) gagal('Giliran sudah berganti.', 'fase');
    list($ok, $pesan, $kode) = rpg_simpan_pilihan($s, $p, (string)($in['skill'] ?? ''), (int)($in['target'] ?? -1));
    if (!$ok) gagal($pesan, $kode);
    rpg_tick($sid);
    out(['ok' => true]);
}

if ($a === 'jawab' && $post) {
    $in = masuk_json();
    $sid = (int)($in['sesi'] ?? 0);
    $p = db_row('SELECT * FROM rpg_pemain WHERE sesi_id=? AND token=?', [$sid, (string)($in['p'] ?? '')]);
    if (!$p) gagal('Kamu tidak ada di sesi ini.', 'keluar');
    $s = rpg_tick($sid);
    if (!$s || $s['status'] !== 'soal') gagal('Waktu untuk soal ini sudah habis.', 'tutup');
    if ((int)($in['ronde'] ?? 0) !== (int)$s['ronde']) gagal('Giliran sudah berganti.', 'ronde');
    $res = live_tx(function () use ($s, $p, $in) {
        $g = db_row('SELECT * FROM rpg_giliran WHERE sesi_id=? AND ronde=? AND pemain_id=?', [$s['id'], $s['ronde'], $p['id']]);
        if (!$g) return ['gagal', 'Karaktermu tidak ikut giliran ini.', 'fase'];
        if ((int)$g['dijawab']) return ['ok', '', 'dobel'];
        $ms = (int)round((microtime(true) - (float)$s['fase_mulai']) * 1000);
        if ($ms > (int)$g['durasi'] * 1000 + 1000) {
            db_q('UPDATE rpg_giliran SET dijawab=1, benar=0, ms=? WHERE id=?', [$ms, $g['id']]);
            return ['gagal', 'Waktu untuk soal ini sudah habis.', 'tutup'];
        }
        $jawab = $in['jawab'] ?? '';
        $jawab = is_string($jawab) ? mb_substr($jawab, 0, 100) : (string)(int)$jawab;
        $benar = rpg_nilai($s, $g, $jawab) ? 1 : 0;
        db_q('UPDATE rpg_giliran SET dijawab=1, jawab=?, benar=?, ms=? WHERE id=?', [$jawab, $benar, max(0, $ms), $g['id']]);
        return ['ok', '', ''];
    });
    rpg_tick($sid);
    if ($res[0] === 'gagal') gagal($res[1], $res[2]);
    out(['ok' => true]);
}

http_response_code(400);
gagal('Permintaan tidak dikenal.');

// =====================================================
//  Ringkasan keadaan
// =====================================================
function rpg_unit_tampil($s, array $units, array $slot, $penuh = false)
{
    $now = microtime(true);
    $out = [];
    $kunci = []; $sudah = [];
    if (in_array($s['status'], ['pilih', 'soal', 'jeda', 'hasil'], true) && (int)$s['ronde'] > 0) {
        foreach (db_rows('SELECT u, dikunci, dijawab, auto, sukses, skill FROM rpg_giliran WHERE sesi_id=? AND ronde=?', [$s['id'], $s['ronde']]) as $g) {
            $kunci[(int)$g['u']] = (int)$g['dikunci']; $sudah[(int)$g['u']] = (int)$g['dijawab'];
        }
    }
    foreach ($units as $u) {
        $i = (int)$u['i'];
        $p = $slot[$i] ?? null;
        $row = ['i' => $i, 'tim' => (int)$u['tim'], 'peran' => $u['peran'], 'nama' => $p ? $p['nama'] : '', 'hp' => (int)$u['hp'], 'mx' => (int)$u['mx'],
            'hidup' => $u['hp'] > 0, 'kunci' => !empty($kunci[$i]), 'sudah' => !empty($sudah[$i]), 'ada' => isset($kunci[$i]),
            'online' => $p ? ($now - (float)$p['seen']) < 6 : false];
        if ($penuh) { $row['cd'] = $u['cd']; $row['kutuk'] = (int)$u['kutuk']; $row['siap'] = (int)$u['siap']; $row['atk'] = (int)$u['atk']; $row['def'] = (int)$u['def']; $row['pid'] = $p ? (int)$p['id'] : 0; }
        $out[] = $row;
    }
    return $out;
}

function rpg_akhir_info($s)
{
    return json_decode((string)$s['akhir'], true) ?: [];
}

function rpg_gstate($game, $s)
{
    if (!$s) return ['ok' => true, 'sesi' => 0, 'st' => 'kosong'];
    $sid = (int)$s['id'];
    $cfg = rpg_cfg_sesi($s);
    $now = microtime(true);
    $slot = rpg_slot_map($sid);
    $units = rpg_units($s);
    if (!$units) $units = rpg_buat_unit($cfg);       // lobi: tampilkan formasi dengan stat awal
    $pem = [];
    foreach (rpg_pemain_semua($sid) as $p) {
        $i = rpg_unit_idx($p['tim'], $p['peran']);
        $pem[] = ['id' => (int)$p['id'], 'nama' => $p['nama'], 'tim' => (int)$p['tim'], 'peran' => $p['peran'], 'u' => $i,
            'online' => ($now - (float)$p['seen']) < 6];
    }
    $bank = count((json_decode((string)$game['data'], true) ?: [])['soal'] ?? []);
    list($bisa, $alasan) = $s['status'] === 'lobi' ? rpg_bisa_mulai($sid) : [true, ''];
    $maksR = $s['status'] === 'lobi' ? rpg_maks_dari_data(json_decode((string)$game['data'], true) ?: [], (int)$cfg['ulang'], (int)$cfg['soal_per_putaran']) : rpg_giliran_maks($s);
    $r = [
        'ok' => true, 'sesi' => $sid, 'st' => $s['status'], 'ronde' => (int)$s['ronde'], 'sblm' => $s['sblm'],
        'cfg' => $cfg, 'bank' => $bank, 'unit' => rpg_unit_tampil($s, $units, $slot, true), 'pemain' => $pem,
        'bisa_mulai' => $bisa, 'alasan' => $alasan,
        'sisa' => rpg_sisa_ms($s, $cfg), 'total' => rpg_total_ms($s, $cfg),
        'soal_sisa' => max(0, $maksR - (int)$s['ronde']), 'soal_total' => $maksR,
        'hidup' => [1 => rpg_hidup($units, 1), 2 => rpg_hidup($units, 2)],
        'pemenang' => (int)$s['pemenang'], 'akhir' => rpg_akhir_info($s),
        'n_riw' => count(json_decode((string)$s['riwayat'], true) ?: []),
        'perkiraan' => rpg_maks_dari_data(json_decode((string)$game['data'], true) ?: [], (int)$cfg['ulang'], (int)$cfg['soal_per_putaran']),
        'putaran' => rpg_panjang_putaran(json_decode((string)$game['data'], true) ?: [], (int)$cfg['soal_per_putaran']),
    ];
    if (in_array($s['status'], ['hasil', 'selesai'], true) || ($s['status'] === 'jeda' && $s['sblm'] === 'hasil')) {
        $r['kejadian'] = json_decode((string)$s['kejadian'], true) ?: [];
    }
    if ($s['status'] === 'selesai' && $cfg['peringkat']) $r['peringkat'] = rpg_peringkat($sid);
    return $r;
}

function rpg_murid_state($s, $p)
{
    $sid = (int)$s['id'];
    $cfg = rpg_cfg_sesi($s);
    $slot = rpg_slot_map($sid);
    $units = rpg_units($s);
    if (!$units) $units = rpg_buat_unit($cfg);
    $ui = rpg_unit_idx($p['tim'], $p['peran']);
    if ($ui >= 0 && (!isset($slot[$ui]) || (int)$slot[$ui]['id'] !== (int)$p['id'])) $ui = -1;
    $st = $s['status'];
    $r = [
        'ok' => true, 'st' => $st, 'ronde' => (int)$s['ronde'], 'nama' => $p['nama'],
        'tim' => $ui >= 0 ? rpg_unit_tim($ui) : 0, 'peran' => $ui >= 0 ? rpg_unit_peran($ui) : '', 'aku' => $ui,
        'unit' => rpg_unit_tampil($s, $units, $slot, false), 'mandiri' => !empty($cfg['pilih_mandiri']), 'fighter' => !empty($cfg['pakai_fighter']),
        'petunjuk' => $st === 'lobi' ? rpg_petunjuk($cfg) : null,
        'hidup' => [1 => rpg_hidup($units, 1), 2 => rpg_hidup($units, 2)],
        'sisa' => rpg_sisa_ms($s, $cfg), 'total' => rpg_total_ms($s, $cfg), 'sblm' => $s['sblm'],
    ];
    if ($ui >= 0) {
        $u = $units[$ui];
        $r['hp'] = (int)$u['hp']; $r['mx'] = (int)$u['mx']; $r['mati'] = $u['hp'] <= 0;
    }
    $fase = $st === 'jeda' ? $s['sblm'] : $st;
    if ($ui >= 0 && $fase !== 'lobi' && $fase !== 'selesai' && (int)$s['ronde'] > 0) {
        $g = db_row('SELECT * FROM rpg_giliran WHERE sesi_id=? AND ronde=? AND u=?', [$sid, $s['ronde'], $ui]);
        $u = $units[$ui];
        if ($g && (int)$g['pemain_id'] === (int)$p['id']) {
            $r['ikut'] = true;
            if ($fase === 'pilih') {
                $ada = rpg_skill_tersedia($u);
                foreach ($ada as &$sk) {
                    $sk['tg'] = rpg_target_sah($units, $u, $sk);
                    if ($sk['tgt'] !== 'tidak' && !$sk['tg']) { $sk['ok'] = false; $sk['kunci'] = true; }
                }
                unset($sk);
                $r['skill'] = $ada;
                $r['dikunci'] = (bool)$g['dikunci'];
                if ($g['dikunci']) $r['pilihan'] = ['skill' => $g['skill'], 'target' => (int)$g['target'], 'auto' => (bool)$g['auto']];
            } elseif ($fase === 'soal') {
                $r['sudah'] = (bool)$g['dijawab'];
                $r['durasi'] = (int)$g['durasi'];
                $r['pilihan'] = ['skill' => $g['skill'], 'target' => (int)$g['target'], 'auto' => (bool)$g['auto']];
                if (!$g['dijawab']) $r['soal'] = rpg_soal_untuk($s, $g);
                $sisa = ((int)$g['durasi']) * 1000 - (int)round((($st === 'jeda' ? (float)$s['jeda_at'] : microtime(true)) - (float)$s['fase_mulai']) * 1000);
                $r['sisa'] = max(0, $sisa); $r['total'] = (int)$g['durasi'] * 1000;
            } elseif ($fase === 'hasil') {
                $dmg = 0; $heal = 0;
                foreach (json_decode((string)$s['kejadian'], true) ?: [] as $e) {
                    if ($e['k'] === 'serang') foreach ($e['t'] as $t) { if ((int)$t['u'] === $ui) $dmg += (int)$t['d']; if (!empty($t['tk']) && (int)$t['tk']['u'] === $ui) $dmg += (int)$t['tk']['d']; if (!empty($t['fgd'])) { if ((int)$t['fgd']['u'] === $ui) $dmg += (int)$t['fgd']['d']; if (!empty($t['fgd']['tk']) && (int)$t['fgd']['tk']['u'] === $ui) $dmg += (int)$t['fgd']['tk']['d']; } }
                    if ($e['k'] === 'heal') foreach ($e['h'] as $t) if ((int)$t['u'] === $ui) $heal += (int)$t['n'];
                }
                $r['hasil'] = ['sukses' => (bool)$g['sukses'], 'skill' => $g['skill'], 'auto' => (bool)$g['auto'], 'dmg' => $dmg, 'heal' => $heal];
            }
        }
    }
    if ($st === 'selesai') {
        $r['pemenang'] = (int)$s['pemenang'];
        $r['akhir'] = rpg_akhir_info($s);
    }
    return $r;
}
