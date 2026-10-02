<?php
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function base_path()
{
    static $b = null;
    if ($b !== null) return $b;
    $doc = str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $app = str_replace('\\', '/', (string)realpath(APP_DIR));
    $b = ($doc !== '' && stripos($app, $doc) === 0) ? substr($app, strlen($doc)) : '/app';
    $b = '/' . trim($b, '/');
    return $b = ($b === '/' ? '' : $b);
}
function url($p = '') { return base_path() . '/' . ltrim($p, '/'); }
function redirect($p) { header('Location: ' . url($p)); exit; }

function setting($k, $def = '')
{
    static $c = null;
    if ($c === null) {
        $c = [];
        foreach (db_rows('SELECT k,v FROM settings') as $r) $c[$r['k']] = $r['v'];
    }
    return (isset($c[$k]) && $c[$k] !== '') ? $c[$k] : $def;
}
function set_setting($k, $v) { db_q('INSERT OR REPLACE INTO settings(k,v) VALUES(?,?)', [$k, $v]); }
function public_url() { return rtrim(setting('url_publik', DEFAULT_PUBLIC_URL), '/'); }

function flash($msg, $type = 'ok') { $_SESSION['flash'][] = [$type, $msg]; }
function take_flash() { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

function csrf_token()
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check()
{
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        die('Sesi formulir kedaluwarsa. Kembali, muat ulang halaman, lalu coba lagi.');
    }
}

function slugify($s)
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return substr($s !== '' ? $s : 'game', 0, 40);
}
function valid_username($u) { return (bool)preg_match('/^[a-z0-9_]{3,30}$/', $u); }
function valid_slug($s) { return (bool)preg_match('/^[a-z0-9-]{1,60}$/', $s); }

// ---------- Template ----------
function list_templates($onlyActive = false)
{
    $out = [];
    $status = [];
    foreach (db_rows('SELECT kode,aktif FROM templates') as $r) $status[$r['kode']] = (int)$r['aktif'];
    foreach (glob(APP_DIR . '/templates/*/manifest.json') ?: [] as $mf) {
        $kode = basename(dirname($mf));
        if (!preg_match('/^[a-z0-9_-]+$/', $kode)) continue;
        if (!is_file(dirname($mf) . '/template.html')) continue;
        $m = json_decode((string)file_get_contents($mf), true);
        if (!is_array($m)) continue;
        if (!isset($status[$kode])) {
            db_q('INSERT OR IGNORE INTO templates(kode,aktif) VALUES(?,1)', [$kode]);
            $status[$kode] = 1;
        }
        $m['kode'] = $kode;
        $m['nama'] = $m['nama'] ?? $kode;
        $m['mode'] = ($m['mode'] ?? 'soal') === 'nama' ? 'nama' : 'soal';
        $m['ikon'] = $m['ikon'] ?? '🎮';
        $m['grup'] = $m['grup'] ?? ($m['mode'] === 'nama' ? 'nama' : 'pilihan');
        $m['aktif'] = $status[$kode];
        if ($onlyActive && !$m['aktif']) continue;
        $out[$kode] = $m;
    }
    uasort($out, function ($a, $b) { return ($a['urutan'] ?? 99) <=> ($b['urutan'] ?? 99); });
    return $out;
}

// ---------- File & folder game ----------
function game_dir($username, $slug) { return APP_DIR . '/games/' . $username . '/' . $slug; }
function game_link($username, $slug) { return public_url() . '/games/' . rawurlencode($username) . '/' . rawurlencode($slug) . '/'; }

function copy_dir($src, $dst)
{
    if (!is_dir($dst)) mkdir($dst, 0775, true);
    foreach (scandir($src) as $f) {
        if ($f === '.' || $f === '..') continue;
        if (preg_match('/\.(php|phtml|phar)$/i', $f)) continue;
        $s = "$src/$f"; $d = "$dst/$f";
        is_dir($s) ? copy_dir($s, $d) : copy($s, $d);
    }
}
function rrmdir($dir)
{
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = "$dir/$f";
        (is_dir($p) && !is_link($p)) ? rrmdir($p) : @unlink($p);
    }
    @rmdir($dir);
}
function delete_game_folder($username, $slug)
{
    if (!valid_username($username) || !valid_slug($slug)) return;
    rrmdir(game_dir($username, $slug));
}

function generate_game($id)
{
    $g = db_row('SELECT g.*, u.username, u.nama AS nama_guru FROM games g JOIN users u ON u.id=g.user_id WHERE g.id=?', [$id]);
    if (!$g) throw new Exception('Data game tidak ditemukan.');
    if (!valid_username($g['username']) || !valid_slug($g['slug'])) throw new Exception('Nama folder tidak valid.');
    $tplDir = APP_DIR . '/templates/' . $g['template'];
    $mf = json_decode((string)@file_get_contents($tplDir . '/manifest.json'), true);
    $html = @file_get_contents($tplDir . '/template.html');
    if ($html === false || !is_array($mf)) throw new Exception('Template "' . $g['template'] . '" tidak ditemukan.');
    if (strpos($html, '/*__GAME_DATA__*/') === false) throw new Exception('template.html tidak memiliki penanda /*__GAME_DATA__*/.');

    $dir = game_dir($g['username'], $g['slug']);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new Exception('Tidak bisa membuat folder game. Periksa izin folder app/games.');
    if (is_dir($tplDir . '/assets')) copy_dir($tplDir . '/assets', $dir . '/assets');

    $data = json_decode((string)$g['data'], true) ?: [];
    $logo = setting('logo');
    $ident = $data['identitas'] ?? ['cara' => 'bebas', 'kelas_id' => null];
    $daftarMurid = [];
    $kelasNamaIdentitas = '';
    if (($ident['cara'] ?? 'bebas') === 'kelas' && !empty($ident['kelas_id'])) {
        $kRow = db_row('SELECT nama FROM kelas WHERE id=? AND user_id=?', [(int)$ident['kelas_id'], (int)$g['user_id']]);
        if ($kRow) {
            $kelasNamaIdentitas = $kRow['nama'];
            $daftarMurid = array_column(db_rows('SELECT nama FROM murid WHERE kelas_id=? ORDER BY id', [(int)$ident['kelas_id']]), 'nama');
        }
    }
    $payload = [
        'id' => (int)$g['id'],
        'token' => $g['token'],
        'judul' => $g['judul'],
        'kelas' => $g['kelas'],
        'guru' => $g['nama_guru'],
        'sekolah' => setting('nama_sekolah'),
        'logo' => $logo ? $logo . '?v=' . substr(md5($logo . @filemtime(APP_DIR . '/' . $logo)), 0, 6) : '',
        'skor' => !empty($mf['skor']),
        'mode' => $data['mode'] ?? 'soal',
        // game live: kunci jawaban tidak boleh ikut ke HP murid (penilaian dilakukan server)
        'soal' => !empty($mf['live']) ? [] : ($data['soal'] ?? []),
        'live' => !empty($mf['live']),
        'nama' => $data['nama'] ?? [],
        'pengaturan' => $data['pengaturan'] ?? new stdClass(),
        'sumber_nama' => $daftarMurid ? 'kelas' : 'bebas',
        'daftar_murid' => $daftarMurid,
        'kelas_murid' => $kelasNamaIdentitas,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $html = str_replace(['/*__GAME_DATA__*/', '__GAME_TITLE__'], [$json, e($g['judul'])], $html);
    if (file_put_contents($dir . '/index.html', $html) === false) throw new Exception('Gagal menulis file index.html.');
    return game_link($g['username'], $g['slug']);
}

function new_slug($judul)
{
    return slugify($judul) . '-' . substr(bin2hex(random_bytes(3)), 0, 5);
}

// ---------- Identitas murid (daftar kelas sebagai "akun tanpa password") ----------
function games_pakai_kelas($userId, $kelasId)
{
    $out = [];
    foreach (db_rows('SELECT id, data FROM games WHERE user_id=?', [$userId]) as $g) {
        $d = json_decode((string)$g['data'], true) ?: [];
        if (($d['identitas']['cara'] ?? '') === 'kelas' && (int)($d['identitas']['kelas_id'] ?? 0) === (int)$kelasId) {
            $out[] = (int)$g['id'];
        }
    }
    return $out;
}
function regen_ids(array $ids)
{
    $n = 0;
    foreach ($ids as $id) { try { generate_game($id); $n++; } catch (Exception $e) {} }
    return $n;
}

// ---------- Parser formulir ----------
function parse_pengaturan(array $m, $raw)
{
    $raw = is_array($raw) ? $raw : [];
    $out = [];
    foreach ($m['pengaturan'] ?? [] as $f) {
        $n = $f['nama']; $t = $f['tipe'] ?? 'text'; $def = $f['default'] ?? null;
        if ($t === 'checkbox') { $out[$n] = !empty($raw[$n]); continue; }
        if ($t === 'number') {
            $v = isset($raw[$n]) && $raw[$n] !== '' ? (int)$raw[$n] : (int)$def;
            if (isset($f['min'])) $v = max((int)$f['min'], $v);
            if (isset($f['max'])) $v = min((int)$f['max'], $v);
            $out[$n] = $v; continue;
        }
        if ($t === 'select') {
            $v = (string)($raw[$n] ?? $def);
            $out[$n] = array_key_exists($v, $f['opsi'] ?? []) ? $v : (string)$def; continue;
        }
        $out[$n] = mb_substr(trim((string)($raw[$n] ?? $def)), 0, 200);
    }
    return $out;
}

function normal_kata($s)
{
    return preg_replace('/[^A-Z0-9]/', '', strtoupper((string)$s));
}

function parse_soal(array $m, $raw)
{
    $out = []; $err = [];
    $tetap = isset($m['opsi_tetap']) && is_array($m['opsi_tetap']) ? array_values($m['opsi_tetap']) : null;
    $n = $tetap ? count($tetap) : (int)($m['jumlah_opsi'] ?? 4);
    $minO = $tetap ? $n : (int)($m['min_opsi'] ?? 2);
    $labelQ = $m['label_soal'] ?? 'Pertanyaan';
    foreach ((array)$raw as $row) {
        if (!is_array($row)) continue;
        $q = mb_substr(trim((string)($row['q'] ?? '')), 0, 500);
        $b = isset($row['b']) ? (int)$row['b'] : -1;
        if ($tetap) {
            if ($q === '') continue;
            $no = count($out) + 1;
            if ($b < 0 || $b >= $n) { $err[] = "Soal $no: pilih jawaban yang tepat."; $b = 0; }
            $out[] = ['q' => $q, 'o' => $tetap, 'b' => $b];
            continue;
        }
        if ($n === 1) $b = 0;
        $opsi = []; $benar = -1;
        for ($i = 0; $i < $n; $i++) {
            $t = mb_substr(trim((string)($row['o'][$i] ?? '')), 0, 200);
            if ($t === '') continue;
            if ($i === $b) $benar = count($opsi);
            $opsi[] = $t;
        }
        if ($q === '' && !$opsi) continue;
        $no = count($out) + 1;
        if ($q === '') $err[] = "Soal $no: $labelQ masih kosong.";
        if ($n === 1) {
            if (!$opsi) $err[] = "Soal $no: " . ($m['label_opsi'] ?? 'jawaban') . ' masih kosong.';
        } else {
            if (count($opsi) < $minO) $err[] = "Soal $no: isi minimal $minO pilihan jawaban.";
            if ($benar < 0) $err[] = "Soal $no: tandai jawaban yang benar (pilihan yang ditandai tidak boleh kosong).";
        }
        if (!empty($m['jawaban_kata']) && $opsi) {
            $k = normal_kata($opsi[0]);
            $max = (int)($m['maks_huruf'] ?? 15);
            if (strlen($k) < 2 || strlen($k) > $max) $err[] = "Soal $no: jawaban harus 2–$max huruf/angka (spasi dan tanda baca diabaikan).";
        }
        $out[] = ['q' => $q, 'o' => $opsi, 'b' => max(0, $benar)];
    }
    $min = (int)($m['min_soal'] ?? 1); $max = (int)($m['max_soal'] ?? 100);
    if (count($out) < $min) $err[] = "Game ini membutuhkan minimal $min soal.";
    if (count($out) > $max) $err[] = "Game ini maksimal $max soal.";
    return [$out, $err];
}

function parse_nama($txt)
{
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', (string)$txt) as $l) {
        $l = mb_substr(trim($l), 0, 60);
        if ($l !== '') $out[] = $l;
    }
    return array_slice($out, 0, 200);
}

function jumlah_isi($g)
{
    $d = json_decode((string)$g['data'], true) ?: [];
    if (($d['mode'] ?? 'soal') === 'nama') return count($d['nama'] ?? []) . ' nama';
    return count($d['soal'] ?? []) . ' soal';
}
