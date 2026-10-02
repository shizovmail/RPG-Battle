<?php
// API game live (tarik tambang). Bagian murid tanpa login; bagian guru butuh sesi login + pemilik game.
require __DIR__ . '/config.php';
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/live.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function gagal($pesan, $kode = '') { out(['ok' => false, 'pesan' => $pesan, 'kode' => $kode]); }
function masuk_json()
{
    $in = json_decode((string)file_get_contents('php://input'), true);
    return is_array($in) ? $in : [];
}

$a = $_GET['a'] ?? '';
$post = $_SERVER['REQUEST_METHOD'] === 'POST';

// =====================================================
//  BAGIAN GURU
// =====================================================
if (in_array($a, ['gstate', 'aksi'], true)) {
    require __DIR__ . '/inc/auth.php';
    start_session();
    $u = current_user();
    if (!$u || $u['role'] !== 'guru') { http_response_code(401); gagal('Sesi login habis. Muat ulang halaman dan masuk lagi.', 'login'); }
    $game = db_row('SELECT * FROM games WHERE id=? AND user_id=?', [(int)($_GET['game'] ?? 0), $u['id']]);
    if (!$game) gagal('Game tidak ditemukan.');

    if ($a === 'gstate') {
        $s = live_sesi_aktif($game['id']) ?: live_sesi_terakhir($game['id']);
        if ($s) $s = live_tick($s['id']);
        out(live_gstate($game, $s));
    }

    if (!$post) gagal('Permintaan tidak valid.');
    if (!hash_equals(csrf_token(), (string)($_SERVER['HTTP_X_CSRF'] ?? ''))) { http_response_code(400); gagal('Sesi formulir kedaluwarsa. Muat ulang halaman.'); }
    $in = masuk_json();
    $do = (string)($in['do'] ?? '');
    $s = live_sesi_aktif($game['id']);
    if ($s) $s = live_tick($s['id']);
    $sid = $s ? (int)$s['id'] : 0;
    $gagalPesan = null;

    if ($do === 'sesi_baru') {
        if ($s) { db_q("UPDATE live_sesi SET status='selesai', akhir=? WHERE id=?", [json_encode(['batal' => true]), $sid]); }
        live_buat_sesi($game);
    } elseif (!$s) {
        gagal('Belum ada sesi. Buat sesi baru dulu.');
    } elseif ($do === 'tim' || $do === 'gabung' || $do === 'pisah' || $do === 'keluarkan' || $do === 'acak' || $do === 'cfg' || $do === 'mulai') {
        if ($s['status'] !== 'lobi') gagal('Pengaturan tim hanya bisa diubah saat masih di lobi.');
        if ($do === 'tim') {
            $tim = (int)($in['tim'] ?? 0);
            if (!in_array($tim, [0, 1, 2], true)) gagal('Tim tidak valid.');
            $p = db_row('SELECT * FROM live_pemain WHERE id=? AND sesi_id=?', [(int)($in['p'] ?? 0), $sid]);
            if (!$p) gagal('Pemain tidak ditemukan.');
            if ($p['gabung_ke'] !== null) gagal('Murid ini bermain di HP temannya. Pisahkan dulu.');
            db_q('UPDATE live_pemain SET tim=? WHERE id=?', [$tim, $p['id']]);
            db_q('UPDATE live_pemain SET tim=? WHERE gabung_ke=?', [$tim, $p['id']]);
        } elseif ($do === 'gabung') {
            $p = db_row('SELECT * FROM live_pemain WHERE id=? AND sesi_id=?', [(int)($in['p'] ?? 0), $sid]);
            $h = db_row('SELECT * FROM live_pemain WHERE id=? AND sesi_id=?', [(int)($in['ke'] ?? 0), $sid]);
            if (!$p || !$h || $p['id'] === $h['id']) gagal('Pemain tidak valid.');
            if ($h['gabung_ke'] !== null) gagal('HP tujuan itu sendiri sudah ikut HP lain.');
            if (db_val('SELECT COUNT(*) FROM live_pemain WHERE gabung_ke=?', [$p['id']])) gagal('Murid ini sudah menjadi tuan rumah HP. Pisahkan pasangannya dulu.');
            if (db_val('SELECT COUNT(*) FROM live_pemain WHERE gabung_ke=?', [$h['id']])) gagal('Satu HP maksimal dipakai 2 murid.');
            db_q('UPDATE live_pemain SET gabung_ke=?, tim=? WHERE id=?', [$h['id'], $h['tim'], $p['id']]);
        } elseif ($do === 'pisah') {
            db_q('UPDATE live_pemain SET gabung_ke=NULL, tim=0 WHERE id=? AND sesi_id=?', [(int)($in['p'] ?? 0), $sid]);
        } elseif ($do === 'keluarkan') {
            $pid = (int)($in['p'] ?? 0);
            db_q('UPDATE live_pemain SET gabung_ke=NULL, tim=0 WHERE gabung_ke=? AND sesi_id=?', [$pid, $sid]);
            db_q('DELETE FROM live_pemain WHERE id=? AND sesi_id=?', [$pid, $sid]);
        } elseif ($do === 'acak') {
            $hosts = array_values(array_filter(live_pemain_semua($sid), function ($p) { return $p['gabung_ke'] === null; }));
            $hosts = live_acak($hosts);
            foreach ($hosts as $i => $p) {
                $t = ($i % 2) + 1;
                db_q('UPDATE live_pemain SET tim=? WHERE id=?', [$t, $p['id']]);
                db_q('UPDATE live_pemain SET tim=? WHERE gabung_ke=?', [$t, $p['id']]);
            }
        } elseif ($do === 'cfg') {
            $bank = count((json_decode((string)$game['data'], true) ?: [])['soal'] ?? []);
            $cfg = live_cfg_bersih($in['cfg'] ?? []);
            if ($cfg['jumlah'] > $bank) $cfg['jumlah'] = 0;
            db_q('UPDATE live_sesi SET cfg=? WHERE id=?', [json_encode($cfg), $sid]);
        } elseif ($do === 'mulai') {
            list($ok, $alasan) = live_bisa_mulai($sid);
            if (!$ok) gagal($alasan);
            $cfg = live_cfg_sesi($s);
            $pools = live_pilih_pools($game, $cfg);
            $n = count($pools[0] ?? []);
            if ($n < 2) gagal('Bank soal kurang dari 2 soal.');
            if ($cfg['mode'] === 'acak_anggota') {
                $K = live_hitung_tim($sid)[1];
                if ($n < $K) gagal('Mode "acak anggota" membutuhkan soal minimal sebanyak HP per tim (' . $K . '), sedangkan soal sesi hanya ' . $n . '. Tambah soal di bank atau naikkan "jumlah soal pertandingan".');
            }
            db_q("UPDATE live_sesi SET status='soal', ronde=1, total=?, seed=?, soal=?, ronde_mulai=?, pos=0, riwayat='[]', pending=0 WHERE id=?",
                [$n, random_int(1, 2000000000), json_encode(['p' => $pools], JSON_UNESCAPED_UNICODE), microtime(true), $sid]);
        }
    } elseif ($do === 'tutup') {
        if ($s['status'] !== 'soal') gagal('Tarikan tidak sedang berjalan.');
        live_tutup($sid);
    } elseif ($do === 'lanjut') {
        if ($s['status'] !== 'hasil') gagal('Belum waktunya lanjut.');
        live_lanjut($sid);
    } elseif ($do === 'jeda') {
        if (!in_array($s['status'], ['soal', 'hasil'], true)) gagal('Game tidak bisa dijeda sekarang.');
        db_q("UPDATE live_sesi SET status='jeda', sblm=?, jeda_at=? WHERE id=?", [$s['status'], microtime(true), $sid]);
    } elseif ($do === 'lanjut_jeda') {
        if ($s['status'] !== 'jeda') gagal('Game tidak sedang dijeda.');
        $d = microtime(true) - (float)$s['jeda_at'];
        db_q('UPDATE live_sesi SET status=sblm, ronde_mulai=ronde_mulai+?, hasil_mulai=hasil_mulai+?, sblm=\'\' WHERE id=?', [$d, $d, $sid]);
    } elseif ($do === 'akhiri') {
        if (!in_array($s['status'], ['soal', 'hasil', 'jeda'], true)) gagal('Game belum berjalan.');
        live_tx(function () use ($sid) {
            $x = live_get($sid);
            $pos = (int)$x['pos'];
            live_selesai($x, $pos > 0 ? 1 : ($pos < 0 ? 2 : 3));
        });
    } else {
        gagal('Perintah tidak dikenal.');
    }
    $s2 = live_sesi_aktif($game['id']) ?: live_sesi_terakhir($game['id']);
    out(live_gstate($game, $s2));
}

// =====================================================
//  BAGIAN MURID
// =====================================================
if ($a === 'cek') {
    $g = db_row('SELECT id, aktif FROM games WHERE id=?', [(int)($_GET['id'] ?? 0)]);
    if (!$g) out(['ada' => false]);
    $s = live_sesi_aktif($g['id']);
    if (!$s) { $t = live_sesi_terakhir($g['id']); out(['ada' => true, 'aktif' => (bool)$g['aktif'], 'sesi' => null, 'terakhir' => $t ? (int)$t['id'] : 0]); }
    out(['ada' => true, 'aktif' => (bool)$g['aktif'], 'sesi' => ['id' => (int)$s['id'], 'status' => $s['status']]]);
}

if ($a === 'gabung' && $post) {
    $in = masuk_json();
    $g = db_row('SELECT id, token, aktif FROM games WHERE id=?', [(int)($in['id'] ?? 0)]);
    if (!$g || !hash_equals($g['token'], (string)($in['token'] ?? ''))) gagal('Game tidak dikenali.');
    if (!$g['aktif']) gagal('Game sedang ditutup oleh guru.');
    $s = live_sesi_aktif($g['id']);
    if (!$s) gagal('Belum ada sesi yang dibuka. Tunggu guru membuka lobi.', 'belum');
    $nama = mb_substr(trim(preg_replace('/\s+/', ' ', (string)($in['nama'] ?? ''))), 0, 40);
    if (mb_strlen($nama) < 2) gagal('Tulis namamu (minimal 2 huruf).');
    $ptoken = (string)($in['ptoken'] ?? '');
    $now = microtime(true);
    $ada = null;
    foreach (live_pemain_semua($s['id']) as $p) {
        if (mb_strtolower($p['nama'], 'UTF-8') === mb_strtolower($nama, 'UTF-8')) { $ada = $p; break; }
    }
    if ($ada) {
        if ($ptoken !== '' && hash_equals($ada['token'], $ptoken)) {
            $tok = $ada['token'];
        } elseif ($now - (float)$ada["seen"] > 8) {           // pemilik lama sudah putus: boleh masuk kembali
            $tok = bin2hex(random_bytes(8));
            db_q('UPDATE live_pemain SET token=?, seen=? WHERE id=?', [$tok, $now, $ada['id']]);
        } else {
            gagal('Nama itu sudah dipakai murid lain yang sedang online. Pilih atau tulis nama yang berbeda.', 'nama');
        }
    } else {
        if ($s['status'] !== 'lobi') gagal('Game sudah dimulai, kamu belum bisa masuk. Tunggu sesi berikutnya.', 'mulai');
        if ((int)db_val('SELECT COUNT(*) FROM live_pemain WHERE sesi_id=?', [$s['id']]) >= 100) gagal('Lobi penuh.');
        $tok = bin2hex(random_bytes(8));
        db_q('INSERT INTO live_pemain(sesi_id,token,nama,seen) VALUES(?,?,?,?)', [$s['id'], $tok, $nama, $now]);
    }
    out(['ok' => true, 'sesi' => (int)$s['id'], 'p' => $tok, 'nama' => $nama]);
}

if ($a === 'state') {
    $sid = (int)($_GET['sesi'] ?? 0);
    $p = db_row('SELECT * FROM live_pemain WHERE sesi_id=? AND token=?', [$sid, (string)($_GET['p'] ?? '')]);
    if (!$p) gagal('Kamu tidak ada di sesi ini.', 'keluar');
    $now = microtime(true);
    if ($now - (float)$p['seen'] > 2.5) db_q('UPDATE live_pemain SET seen=? WHERE id=?', [$now, $p['id']]);
    $s = live_tick($sid);
    if (!$s) gagal('Sesi tidak ditemukan.', 'keluar');
    $cfg = live_cfg_sesi($s);
    $semua = live_pemain_semua($sid);
    $host = null;
    if ($p['gabung_ke'] !== null) foreach ($semua as $x) if ((int)$x['id'] === (int)$p['gabung_ke']) $host = $x;
    $r = [
        'ok' => true, 'st' => $s['status'], 'ronde' => (int)$s['ronde'], 'total' => (int)$s['total'],
        'extra' => live_fase($s)[2], 'label' => live_label($s)[0], 'sub' => live_label($s)[1],
        'tim' => (int)$p['tim'], 'nama' => live_nama_tampil($p['gabung_ke'] === null ? $p : $host, $semua),
        'numpang' => $host ? $host['nama'] : null,
        'jml' => live_hitung_tim($sid), 'waktu' => (int)$cfg['waktu'],
        'sisa' => live_sisa_ms($s, $cfg), 'sblm' => $s['sblm'],
        'pos' => (int)$s['pos'], 'selisih' => (int)$cfg['selisih'],
    ];
    if (in_array($s['status'], ['soal', 'jeda'], true) && $host === null && in_array((int)$p['tim'], [1, 2], true)) {
        $sudah = (bool)db_val('SELECT COUNT(*) FROM live_jawab WHERE sesi_id=? AND pemain_id=? AND ronde=?', [$sid, $p['id'], $s['ronde']]);
        $r['sudah'] = $sudah;
        if (!$sudah) $r['soal'] = live_soal_untuk($s, $cfg, $p);
    }
    if ($s['status'] === 'hasil') {
        $riw = json_decode((string)$s['riwayat'], true) ?: [];
        $l = end($riw) ?: null;
        $r['hasil'] = $l ? ['win' => (int)$l['win'], 'lang' => (int)$l['lang']] : null;
    }
    if ($s['status'] === 'selesai') {
        $r['pemenang'] = (int)$s['pemenang'];
    }
    out($r);
}

if ($a === 'jawab' && $post) {
    $in = masuk_json();
    $sid = (int)($in['sesi'] ?? 0);
    $p = db_row('SELECT * FROM live_pemain WHERE sesi_id=? AND token=?', [$sid, (string)($in['p'] ?? '')]);
    if (!$p) gagal('Kamu tidak ada di sesi ini.', 'keluar');
    if ($p['gabung_ke'] !== null || !in_array((int)$p['tim'], [1, 2], true)) gagal('HP ini tidak ikut menjawab.');
    $s = live_tick($sid);
    if (!$s || $s['status'] !== 'soal') gagal('Waktu untuk soal ini sudah habis.', 'tutup');
    if ((int)($in['ronde'] ?? 0) !== (int)$s['ronde']) gagal('Tarikan sudah berganti.', 'ronde');
    $cfg = live_cfg_sesi($s);
    $ms = (int)round((microtime(true) - (float)$s['ronde_mulai']) * 1000);
    if ($cfg['waktu'] > 0 && $ms > $cfg['waktu'] * 1000 + 1000) gagal('Waktu untuk soal ini sudah habis.', 'tutup');
    $jawab = $in['jawab'] ?? '';
    $jawab = is_string($jawab) ? mb_substr($jawab, 0, 100) : (string)(int)$jawab;
    $benar = live_nilai($s, $cfg, $p, $jawab) ? 1 : 0;
    $st = db_q('INSERT OR IGNORE INTO live_jawab(sesi_id,pemain_id,ronde,jawab,benar,ms) VALUES(?,?,?,?,?,?)',
        [$sid, $p['id'], $s['ronde'], $jawab, $benar, max(0, $ms)]);
    if ($cfg['waktu'] > 0) live_tick($sid);
    out(['ok' => true, 'baru' => $st->rowCount() > 0]);
}

http_response_code(400);
gagal('Permintaan tidak dikenal.');

// =====================================================
//  Ringkasan keadaan untuk panel guru & layar proyektor
// =====================================================
function live_gstate($game, $s)
{
    if (!$s) return ['ok' => true, 'sesi' => 0, 'st' => 'kosong'];
    $sid = (int)$s['id'];
    $cfg = live_cfg_sesi($s);
    $semua = live_pemain_semua($sid);
    $now = microtime(true);
    $sudahMap = [];
    if (in_array($s['status'], ['soal', 'jeda'], true)) {
        foreach (db_rows('SELECT pemain_id FROM live_jawab WHERE sesi_id=? AND ronde=?', [$sid, $s['ronde']]) as $x) $sudahMap[(int)$x['pemain_id']] = true;
    }
    $pem = [];
    $sudah = [0, 0, 0]; $jml = [0, 0, 0];
    foreach ($semua as $p) {
        $isHost = $p['gabung_ke'] === null;
        $row = ['id' => (int)$p['id'], 'nama' => $p['nama'], 'tim' => (int)$p['tim'], 'gabung_ke' => $p['gabung_ke'] === null ? null : (int)$p['gabung_ke'],
            'online' => ($now - (float)$p['seen']) < 6, 'sudah' => !empty($sudahMap[(int)$p['id']])];
        $pem[] = $row;
        if ($isHost && in_array((int)$p['tim'], [1, 2], true)) { $jml[(int)$p['tim']]++; if ($row['sudah']) $sudah[(int)$p['tim']]++; }
        elseif ($isHost) $jml[0]++;
    }
    $bank = count((json_decode((string)$game['data'], true) ?: [])['soal'] ?? []);
    list($bisa, $alasan) = $s['status'] === 'lobi' ? live_bisa_mulai($sid) : [true, ''];
    $r = [
        'ok' => true, 'sesi' => $sid, 'st' => $s['status'], 'ronde' => (int)$s['ronde'], 'total' => (int)$s['total'],
        'extra' => live_fase($s)[2], 'label' => live_label($s)[0], 'sub' => live_label($s)[1],
        'cfg' => $cfg, 'bank' => $bank, 'pos' => (int)$s['pos'], 'selisih' => (int)$cfg['selisih'],
        'pemain' => $pem, 'jml' => $jml, 'sudah' => $sudah,
        'bisa_mulai' => $bisa, 'alasan' => $alasan,
        'sisa' => live_sisa_ms($s, $cfg), 'sblm' => $s['sblm'],
        'riwayat' => json_decode((string)$s['riwayat'], true) ?: [],
        'pemenang' => (int)$s['pemenang'], 'pending' => (int)$s['pending'],
        'semua_sudah' => $jml[1] + $jml[2] > 0 && $sudah[1] + $sudah[2] === $jml[1] + $jml[2],
    ];
    if (in_array($s['status'], ['soal', 'jeda'], true) && (int)$s['total'] > 0 && (int)$s['ronde'] > 0) {
        $pools = live_pools($s);
        if ($cfg['mode'] === 'acak_anggota') {
            list($pass, $q, $extra) = live_fase($s);
            $n = count($pools[0]);
            $set = live_set_ronde($s['seed'] . '.' . $pass, $n, max(1, $jml[1]), $extra ? $n + $q : $q);
            $no = array_map(function ($i) use ($pools, $pass) { return (int)($pools[$pass][$i]['orig'] ?? $i) + 1; }, $set);
            sort($no);
            $r['soal_set'] = $no;
        } elseif ($cfg['mode'] !== 'acak_murid') {
            $x = live_item($s, $cfg, 0, (int)$s['ronde']);
            if ($x) $r['soal_tampil'] = ['t' => $x['t'], 'q' => $x['q']];
        }
    }
    if ($s['status'] === 'selesai' && $cfg['peringkat']) $r['peringkat'] = live_peringkat($sid);
    return $r;
}
