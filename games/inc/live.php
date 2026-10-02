<?php
// =====================================================
//  INTI GAME LIVE: TARIK TAMBANG (tim vs tim)
//  Semua penilaian dilakukan di server. Kunci jawaban tidak pernah dikirim ke HP murid.
//  Tim 1 = MERAH (kiri layar), Tim 2 = PUTIH (kanan layar).
//  pos > 0  : tali bergeser ke arah Merah;  pos < 0 : ke arah Putih.
// =====================================================


function live_cfg_bawaan()
{
    return ['waktu' => 30, 'selisih' => 3, 'jumlah' => 0, 'mode' => 'acak_murid',
        'acak_opsi' => true, 'durasi_hasil' => 5, 'hindari_ulang' => true, 'peringkat' => false, 'ulang' => 3];
}

function live_mode_opsi()
{
    return [
        'acak_murid' => 'Acak: setiap murid mendapat soal berbeda (seru, ada faktor keberuntungan)',
        'acak_sama'  => 'Urutan acak, tetapi soal yang muncul SAMA untuk semua murid',
        'urut'       => 'Urut sesuai buatan guru dan SAMA untuk semua murid',
        'acak_anggota' => 'Acak anggota: soal tiap tarikan sama untuk kedua tim, anggota yang mendapatnya diacak (tanpa soal berulang per anggota)',
    ];
}

function live_cfg_bersih($raw)
{
    $d = live_cfg_bawaan();
    $raw = is_array($raw) ? $raw : [];
    $o = [];
    $o['waktu'] = max(0, min(300, (int)($raw['waktu'] ?? $d['waktu'])));
    $o['selisih'] = max(1, min(30, (int)($raw['selisih'] ?? $d['selisih'])));
    $j = (int)($raw['jumlah'] ?? 0);
    $o['jumlah'] = $j <= 0 ? 0 : max(2, $j);
    $m = (string)($raw['mode'] ?? $d['mode']);
    $o['mode'] = array_key_exists($m, live_mode_opsi()) ? $m : $d['mode'];
    foreach (['acak_opsi', 'hindari_ulang', 'peringkat'] as $k) {
        $o[$k] = array_key_exists($k, $raw) ? !empty($raw[$k]) : $d[$k];
    }
    $o['durasi_hasil'] = max(2, min(20, (int)($raw['durasi_hasil'] ?? $d['durasi_hasil'])));
    $o['ulang'] = max(0, min(10, (int)($raw['ulang'] ?? $d['ulang'])));
    return $o;
}

// ---------- Soal ----------
function live_norm($s)
{
    $s = mb_strtolower(trim((string)$s), 'UTF-8');
    $s = preg_replace('/(?<=\d),(?=\d)/', '.', $s);           // 0,5 = 0.5
    $s = preg_replace('/[^\p{L}\p{N}\.\/\-]/u', '', $s);      // buang spasi & tanda baca
    return rtrim($s, '.');
}

function live_parse_soal($raw, array $jenis, &$err)
{
    $err = [];
    $out = [];
    $jenis = array_values(array_intersect($jenis, ['pg', 'isian']));
    foreach ((array)$raw as $row) {
        if (!is_array($row)) continue;
        $q = mb_substr(trim((string)($row['q'] ?? '')), 0, 500);
        $t = (string)($row['t'] ?? ($jenis[0] ?? 'pg'));
        if (!in_array($t, $jenis, true)) $t = $jenis[0] ?? 'pg';
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
            $out[] = ['t' => 'pg', 'q' => $q, 'o' => $opsi, 'b' => max(0, $benar)];
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
            $out[] = ['t' => 'isian', 'q' => $q, 'j' => $lst];
        }
    }
    if (count($out) < 2) $err[] = 'Minimal harus ada 2 soal.';
    if (count($out) > 200) $err[] = 'Maksimal 200 soal.';
    return $out;
}

function live_acak(array $a)
{
    for ($i = count($a) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        $t = $a[$i]; $a[$i] = $a[$j]; $a[$j] = $t;
    }
    return $a;
}

// permutasi deterministik dari seed (agar urutan pilihan bisa dihitung ulang saat menilai)
function live_perm($n, $seed)
{
    $a = $n > 0 ? range(0, $n - 1) : [];
    if ($n < 2) return $a;
    mt_srand((int)$seed & 0x7fffffff);
    for ($i = $n - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        $t = $a[$i]; $a[$i] = $a[$j]; $a[$j] = $t;
    }
    return $a;
}

// Pilih soal untuk satu sesi. Hasilnya beberapa "putaran" soal: putaran 1 + sebanyak pengulangan yang diatur guru.
// Tiap putaran diacak ulang. Soal yang belum pernah dipakai (di sesi sebelumnya dan putaran sebelumnya) diutamakan.
function live_pilih_pools($game, $cfg)
{
    $data = json_decode((string)$game['data'], true) ?: [];
    $bank = array_values($data['soal'] ?? []);
    $n = count($bank);
    $jml = ($cfg['jumlah'] > 0 && $cfg['jumlah'] < $n) ? $cfg['jumlah'] : $n;
    $pakai = array_fill(0, max(1, $n), 0);
    if (!empty($cfg['hindari_ulang'])) {
        foreach (db_rows('SELECT soal FROM live_sesi WHERE game_id=? AND total>0', [(int)$game['id']]) as $r) {
            foreach (live_pools($r) as $pool) foreach ($pool as $x) {
                if (isset($x['orig']) && isset($pakai[$x['orig']])) $pakai[$x['orig']]++;
            }
        }
    }
    $pools = [];
    for ($p = 0; $p <= (int)$cfg['ulang']; $p++) {
        $idx = live_acak($n ? range(0, $n - 1) : []);
        if ($jml < $n) {
            usort($idx, function ($a, $b) use ($pakai) { return $pakai[$a] <=> $pakai[$b]; }); // stabil di PHP 8
            $idx = array_slice($idx, 0, $jml);
        }
        foreach ($idx as $i) $pakai[$i]++;
        sort($idx);   // urutan buatan guru (dipakai mode "urut" di semua putaran, termasuk pengulangan)
        $pool = [];
        foreach ($idx as $i) { $x = $bank[$i]; $x['orig'] = $i; $pool[] = $x; }
        $pools[] = $pool;
    }
    return $pools;
}

// Putaran-putaran soal milik sebuah sesi (kompatibel dengan format lama: satu daftar saja)
function live_pools($s)
{
    $d = json_decode((string)$s['soal'], true) ?: [];
    if (isset($d['p'])) return $d['p'];
    return $d ? [$d] : [];
}

// Tahap tarikan ke-$r: [nomor putaran (0..), urutan dalam putaran (1..N), tarikan penentu?, tarikan terjadwal maksimal]
function live_fase($s, $r = null)
{
    $r = $r === null ? (int)$s['ronde'] : (int)$r;
    $pools = live_pools($s);
    $n = count($pools[0] ?? []);
    if ($n < 1) return [0, 0, false, 0];
    $maks = $n * count($pools);
    if ($r > $maks) return [count($pools) - 1, $r - $maks, true, $maks];
    return [intdiv($r - 1, $n), ($r - 1) % $n + 1, false, $maks];
}

// Label tarikan untuk ditampilkan: [judul, keterangan]
function live_label($s)
{
    list($pass, $q, $extra, $maks) = live_fase($s);
    $r = (int)$s['ronde']; $n = (int)$s['total'];
    if ($extra) return ['Tarikan penentu', 'satu soal tambahan'];
    if ($pass === 0) return ['Tarikan ' . $r . ' / ' . $n, ''];
    return ['Tarikan ' . $r, 'pengulangan soal ke-' . $pass];
}

// ---------- Mode "acak anggota" ----------
// Tiap tim punya K anggota (HP), soal sesi berjumlah N (syarat: K <= N).
// Dasar: persegi Latin siklik a[j][c] = (j + c) mod N. Baris j = anggota, kolom c = ronde.
//  - Dalam satu ronde, K anggota mendapat K soal berbeda (kolom berisi simbol berbeda).
//  - Selama N ronde, tiap anggota mendapat N soal berbeda (baris berisi simbol berbeda),
//    jadi tidak ada soal yang muncul dua kali pada anggota yang sama.
//  - Kedua tim memakai kolom (ronde) yang sama => himpunan soal sama; hanya baris (anggota) yang diacak per tim.
//  - Nomor soal, urutan ronde, dan penempatan anggota diacak dengan seed sesi (isotopi persegi Latin tetap valid).
// Ronde ke-(N+1) dst (soal penentu) tidak mungkin bebas ulang, maka kolomnya dipilih acak.
function live_anggota_idx($seed, $n, $K, $tim, $pos, $r)
{
    $sig = live_perm($n, crc32('s' . $seed));
    $row = $K > 0 ? live_perm($K, crc32('a' . $seed . '-' . $tim))[$pos] : 0;
    if ($r <= $n) { $gam = live_perm($n, crc32('c' . $seed)); $c = $gam[$r - 1]; }
    else $c = crc32('e' . $seed . '-' . $r) % $n;
    return $sig[($row + $c) % $n];
}

// himpunan soal (indeks) yang muncul pada ronde $r untuk SETIAP tim
function live_set_ronde($seed, $n, $K, $r)
{
    $sig = live_perm($n, crc32('s' . $seed));
    $c = $r <= $n ? live_perm($n, crc32('c' . $seed))[$r - 1] : crc32('e' . $seed . '-' . $r) % $n;
    $o = [];
    for ($j = 0; $j < $K; $j++) $o[] = $sig[($j + $c) % $n];
    sort($o);
    return $o;
}

function live_anggota_info($s, $pid)
{
    static $c = [];
    $sid = (int)$s['id'];
    if (!isset($c[$sid])) {
        $c[$sid] = ['tim' => [1 => [], 2 => []]];
        foreach (live_slots($sid) as $p) $c[$sid]['tim'][(int)$p['tim']][] = (int)$p['id'];
    }
    foreach ([1, 2] as $t) {
        $pos = array_search((int)$pid, $c[$sid]['tim'][$t], true);
        if ($pos !== false) return [$t, $pos, count($c[$sid]['tim'][$t])];
    }
    return [1, 0, max(1, count($c[$sid]['tim'][1]))];
}

// Soal (lengkap dengan kunci, hanya untuk server) yang tampil untuk pemain $pid pada tarikan $r.
// Putaran pengulangan mengikuti mode yang dipilih guru: "urut" tetap urut, mode acak diacak ulang tiap putaran.
function live_item($s, $cfg, $pid, $r)
{
    $pools = live_pools($s);
    if (!$pools) return null;
    $n = count($pools[0]);
    list($pass, $q, $extra) = live_fase($s, $r);
    $mode = $cfg['mode'];
    $sd = $s['seed'] . '.' . $pass;
    if ($mode === 'acak_anggota') {
        list($t, $pos, $K) = live_anggota_info($s, $pid);
        $i = live_anggota_idx($sd, $n, $K, $t, $pos, $extra ? $n + $q : $q);
    } elseif ($extra) {
        $i = crc32('x' . $sd . '-' . $q . ($mode === 'acak_murid' ? '-' . $pid : '')) % $n;
    } else {
        $m = $mode;
        if ($m === 'urut') $i = $q - 1;
        else $i = live_perm($n, $m === 'acak_sama' ? crc32('u' . $sd) : crc32('u' . $sd . '-' . $pid))[$q - 1];
    }
    return $pools[$pass][$i];
}

function live_perm_opsi($s, $cfg, $pid, $r, $jml)
{
    if (empty($cfg['acak_opsi'])) return $jml > 0 ? range(0, $jml - 1) : [];
    return live_perm($jml, crc32('o' . $s['seed'] . '-' . $pid . '-' . $r));
}

// ---------- Data sesi ----------
function live_get($sid) { return db_row('SELECT * FROM live_sesi WHERE id=?', [(int)$sid]); }
function live_cfg_sesi($s) { return live_cfg_bersih(json_decode((string)$s['cfg'], true)); }
function live_soal_sesi($s) { $p = live_pools($s); return $p[0] ?? []; }

function live_sesi_aktif($gameId)
{
    return db_row("SELECT * FROM live_sesi WHERE game_id=? AND status<>'selesai' ORDER BY id DESC LIMIT 1", [(int)$gameId]);
}
function live_sesi_terakhir($gameId)
{
    return db_row('SELECT * FROM live_sesi WHERE game_id=? ORDER BY id DESC LIMIT 1', [(int)$gameId]);
}

function live_buat_sesi($game)
{
    $data = json_decode((string)$game['data'], true) ?: [];
    $cfg = live_cfg_bersih($data['pengaturan'] ?? []);
    db_q('INSERT INTO live_sesi(game_id,cfg) VALUES(?,?)', [(int)$game['id'], json_encode($cfg)]);
    return live_get((int)db()->lastInsertId());
}

function live_pemain_semua($sid) { return db_rows('SELECT * FROM live_pemain WHERE sesi_id=? ORDER BY id', [(int)$sid]); }

// "Slot" = satu HP yang bertanding (murid yang digabung ke HP temannya tidak dihitung sendiri)
function live_slots($sid)
{
    return db_rows('SELECT * FROM live_pemain WHERE sesi_id=? AND gabung_ke IS NULL AND tim IN (1,2) ORDER BY id', [(int)$sid]);
}

function live_nama_tampil($p, array $semua)
{
    $n = $p['nama'];
    foreach ($semua as $x) if ((int)$x['gabung_ke'] === (int)$p['id']) $n .= ' & ' . $x['nama'];
    return $n;
}

function live_bisa_mulai($sid)
{
    $semua = live_pemain_semua($sid);
    $n = [0 => 0, 1 => 0, 2 => 0];
    foreach ($semua as $p) {
        if ($p['gabung_ke'] !== null) continue;
        $n[(int)$p['tim']]++;
    }
    if ($n[0] > 0) return [false, 'Masih ada ' . $n[0] . ' murid yang belum masuk tim. Tekan "Acak tim" atau atur manual.'];
    if ($n[1] < 1 || $n[2] < 1) return [false, 'Setiap tim minimal 1 HP pemain.'];
    if ($n[1] !== $n[2]) return [false, 'Jumlah HP kedua tim harus sama (Merah ' . $n[1] . ' vs Putih ' . $n[2] . '). Gabungkan dua murid dalam 1 HP atau keluarkan satu murid.'];
    return [true, ''];
}

// ---------- Perpindahan status (dipanggil malas oleh setiap permintaan) ----------
function live_tick($sid)
{
    $s = live_get($sid);
    if (!$s) return null;
    $now = microtime(true);
    $cfg = live_cfg_sesi($s);
    if ($s['status'] === 'soal') {
        $waktu = (int)$cfg['waktu'];
        if ($waktu > 0) {
            $habis = $now >= (float)$s['ronde_mulai'] + $waktu + 1.0; // toleransi 1 dtk untuk jaringan
            if ($habis || live_semua_menjawab($s)) live_tutup($sid);
        }
    } elseif ($s['status'] === 'hasil') {
        if ((int)$cfg['waktu'] > 0 && $now >= (float)$s['hasil_mulai'] + (int)$cfg['durasi_hasil']) live_lanjut($sid);
    }
    return live_get($sid);
}

function live_semua_menjawab($s)
{
    $slots = live_slots($s['id']);
    if (!$slots) return false;
    $n = (int)db_val('SELECT COUNT(*) FROM live_jawab j JOIN live_pemain p ON p.id=j.pemain_id WHERE j.sesi_id=? AND j.ronde=? AND p.gabung_ke IS NULL AND p.tim IN (1,2)', [$s['id'], $s['ronde']]);
    return $n >= count($slots);
}

function live_tx(callable $fn)
{
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try { $r = $fn(); $pdo->exec('COMMIT'); return $r; }
    catch (Throwable $e) { $pdo->exec('ROLLBACK'); throw $e; }
}

// Tutup ronde: hitung tim mana yang lebih banyak benar (lalu waktu gabungan tercepat) & geser tali
function live_tutup($sid)
{
    live_tx(function () use ($sid) {
        $s = live_get($sid);
        if (!$s || $s['status'] !== 'soal') return;
        $cfg = live_cfg_sesi($s);
        $r = (int)$s['ronde'];
        $b = [1 => 0, 2 => 0]; $m = [1 => 0, 2 => 0];
        $rows = db_rows('SELECT p.tim, j.benar, j.ms FROM live_jawab j JOIN live_pemain p ON p.id=j.pemain_id
                         WHERE j.sesi_id=? AND j.ronde=? AND p.gabung_ke IS NULL AND p.tim IN (1,2)', [$sid, $r]);
        foreach ($rows as $x) {
            if ($x['benar']) { $b[(int)$x['tim']]++; $m[(int)$x['tim']] += (int)$x['ms']; }
        }
        $win = 0; $lang = 0;
        if ($b[1] !== $b[2]) { $win = $b[1] > $b[2] ? 1 : 2; $lang = abs($b[1] - $b[2]); }
        elseif ($b[1] > 0 && $m[1] !== $m[2]) { $win = $m[1] < $m[2] ? 1 : 2; $lang = 1; } // jumlah benar sama: waktu gabungan tercepat
        $pos = (int)$s['pos'] + ($win === 1 ? $lang : ($win === 2 ? -$lang : 0));
        $riw = json_decode((string)$s['riwayat'], true) ?: [];
        $riw[] = ['r' => $r, 'b1' => $b[1], 'b2' => $b[2], 'win' => $win, 'lang' => $lang, 'pos' => $pos, 'cepat' => ($win && $b[1] === $b[2]) ? 1 : 0];
        $pending = abs($pos) >= (int)$cfg['selisih'] ? ($pos > 0 ? 1 : 2) : 0;
        if (live_fase($s)[2]) $pending = $win ?: 3;   // tarikan penentu: pemenangnya = pemenang pertandingan (3 = seri bila benar-benar sama)
        db_q("UPDATE live_sesi SET status='hasil', pos=?, riwayat=?, hasil_mulai=?, pending=? WHERE id=?",
            [$pos, json_encode($riw), microtime(true), $pending, $sid]);
    });
}

// Setelah layar hasil. Aturan:
//  - selisih langkah tercapai            -> selesai (pemenang = sisi tali)
//  - soal belum habis / masih ada pengulangan soal -> tarikan berikutnya
//  - semua putaran habis, tali berpindah   -> pemenang = sisi tali yang lebih jauh
//  - semua putaran habis, tali di tengah   -> SATU tarikan penentu; pemenang tarikan itu = pemenang pertandingan
function live_lanjut($sid)
{
    live_tx(function () use ($sid) {
        $s = live_get($sid);
        if (!$s || $s['status'] !== 'hasil') return;
        $r = (int)$s['ronde']; $pos = (int)$s['pos'];
        if ((int)$s['pending']) { live_selesai($s, (int)$s['pending']); return; }
        list($pass, $q, $extra, $maks) = live_fase($s);
        if ($r >= $maks) {
            if ($pos !== 0) { live_selesai($s, $pos > 0 ? 1 : 2); return; }
        }
        db_q("UPDATE live_sesi SET status='soal', ronde=?, ronde_mulai=?, pending=0 WHERE id=?", [$r + 1, microtime(true), $sid]);
    });
}

function live_ringkas_pemain($sid)
{
    $semua = live_pemain_semua($sid);
    $out = [];
    foreach (live_slots($sid) as $p) {
        $st = db_row('SELECT COALESCE(SUM(benar),0) benar, COALESCE(SUM(CASE WHEN benar=1 THEN ms ELSE 0 END),0) ms FROM live_jawab WHERE sesi_id=? AND pemain_id=?', [$sid, $p['id']]);
        $out[] = ['id' => (int)$p['id'], 'nama' => live_nama_tampil($p, $semua), 'tim' => (int)$p['tim'], 'benar' => (int)$st['benar'], 'ms' => (int)$st['ms']];
    }
    return $out;
}

// dipanggil di dalam transaksi
function live_selesai($s, $pemenang)
{
    $sid = (int)$s['id'];
    $ring = live_ringkas_pemain($sid);
    $akhir = ['pemenang' => $pemenang, 'ronde' => (int)$s['ronde'], 'pos' => (int)$s['pos']];
    if (!(int)$s['disimpan']) {
        foreach ($ring as $p) {
            db_q('INSERT INTO skor(game_id,nama,skor,maks,durasi) VALUES(?,?,?,?,?)',
                [(int)$s['game_id'], mb_substr($p['nama'] . ' (' . ($p['tim'] === 1 ? 'Merah' : 'Putih') . ')', 0, 50),
                    min($p['benar'], (int)$s['ronde']), max(1, (int)$s['ronde']), (int)round($p['ms'] / 1000)]);
        }
    }
    db_q("UPDATE live_sesi SET status='selesai', pemenang=?, akhir=?, disimpan=1 WHERE id=?", [$pemenang, json_encode($akhir), $sid]);
}

function live_peringkat($sid, $maks = 5)
{
    $r = live_ringkas_pemain($sid);
    usort($r, function ($a, $b) { return [$b['benar'], $a['ms']] <=> [$a['benar'], $b['ms']]; });
    return array_map(function ($p) { return ['nama' => $p['nama'], 'tim' => $p['tim'], 'benar' => $p['benar']]; }, array_slice($r, 0, $maks));
}

// ---------- Tarikan: soal untuk seorang pemain ----------
function live_soal_untuk($s, $cfg, $p)
{
    if ((int)$s['ronde'] < 1) return null;
    $x = live_item($s, $cfg, (int)$p['id'], (int)$s['ronde']);
    if (!$x) return null;
    if ($x['t'] === 'isian') return ['t' => 'isian', 'q' => $x['q']];
    $perm = live_perm_opsi($s, $cfg, (int)$p['id'], (int)$s['ronde'], count($x['o']));
    $o = [];
    foreach ($perm as $k) $o[] = $x['o'][$k];
    return ['t' => 'pg', 'q' => $x['q'], 'o' => $o];
}

// Nilai jawaban murid (jawab = indeks tampilan untuk pilihan ganda, teks untuk isian)
function live_nilai($s, $cfg, $p, $jawab)
{
    $x = live_item($s, $cfg, (int)$p['id'], (int)$s['ronde']);
    if ($x['t'] === 'isian') {
        $u = live_norm($jawab);
        if ($u === '') return false;
        foreach ($x['j'] as $j) if (live_norm($j) === $u) return true;
        return false;
    }
    $perm = live_perm_opsi($s, $cfg, (int)$p['id'], (int)$s['ronde'], count($x['o']));
    $k = (int)$jawab;
    return isset($perm[$k]) && $perm[$k] === (int)$x['b'];
}

function live_sisa_ms($s, $cfg)
{
    $w = (int)$cfg['waktu'];
    if ($w <= 0) return null;
    $ref = $s['status'] === 'jeda' ? (float)$s['jeda_at'] : microtime(true);
    if ($s['status'] === 'jeda' && $s['sblm'] !== 'soal') return null;
    if ($s['status'] !== 'soal' && $s['status'] !== 'jeda') return null;
    return max(0, (int)round(($w - ($ref - (float)$s['ronde_mulai'])) * 1000));
}

function live_tim_nama($t) { return $t === 1 ? 'Merah' : ($t === 2 ? 'Putih' : ''); }

function live_hitung_tim($sid)
{
    $n = [0, 0, 0];
    foreach (live_pemain_semua($sid) as $p) if ($p['gabung_ke'] === null) $n[(int)$p['tim']]++;
    return $n;
}
