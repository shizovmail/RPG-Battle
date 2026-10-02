<?php
// =====================================================
//  BANK SOAL
//  - Visibilitas soal per game: private (hanya pembuat) / public (semua guru ber-akun bisa melihat & memakai)
//  - Bentuk soal "kanonik": satu format perantara agar soal dari jenis game mana pun bisa dipakai di jenis game lain
//  - Konversi game: soal & jawaban dipindah apa adanya, hanya pengaturan yang kembali ke bawaan jenis tujuan
//  - Pencarian soal lintas game yang boleh dilihat guru yang sedang masuk
//
//  Bentuk kanonik satu soal:
//    pilihan ganda : ['t'=>'pg',    'q'=>..., 'o'=>[...], 'b'=>indeks benar]
//    isian singkat : ['t'=>'isian', 'q'=>..., 'j'=>[jawaban diterima, ...]]
//    (opsional)      'w' = durasi detik (RPG Battle), 'k' = kategori peran (RPG Battle model kategori)
// =====================================================

function vis_valid($v) { return $v === 'public' ? 'public' : 'private'; }

/** Dipanggil formulir setelah game tersimpan. Bila kolom tidak dikirim, visibilitas lama dibiarkan. */
function simpan_visibilitas($gid, $userId)
{
    if (!isset($_POST['visibilitas'])) return;
    db_q('UPDATE games SET visibilitas=? WHERE id=? AND user_id=?', [vis_valid($_POST['visibilitas']), (int)$gid, (int)$userId]);
}

function bank_badge($vis, $milik = true)
{
    if ($vis === 'public') return '<span class="tag vis-pub" title="Soal game ini bisa dilihat & dipakai guru lain">🌐 Publik</span>';
    return '<span class="tag vis-priv" title="' . ($milik ? 'Hanya kamu yang bisa melihat soal game ini' : 'Privat') . '">🔒 Privat</span>';
}

/** Kartu pilihan Privat/Publik untuk formulir pembuat game. */
function bank_vis_card($game = null)
{
    $v = vis_valid($_POST['visibilitas'] ?? ($game['visibilitas'] ?? 'private'));
    ?>
  <section class="card">
    <h2>Siapa yang boleh melihat soal ini?</h2>
    <div class="vis-opsi">
      <label class="vis-pilih"><input type="radio" name="visibilitas" value="private" <?= $v === 'private' ? 'checked' : '' ?>>
        <span><b>🔒 Privat</b><small>Hanya kamu yang bisa melihat dan memakai ulang soal & jawaban game ini.</small></span></label>
      <label class="vis-pilih"><input type="radio" name="visibilitas" value="public" <?= $v === 'public' ? 'checked' : '' ?>>
        <span><b>🌐 Publik</b><small>Guru lain yang punya akun bisa melihat soal <u>beserta jawabannya</u> di Bank Soal, lalu memakainya sebagian atau seluruhnya di game mereka. Mereka mendapat salinan; game dan nilai muridmu tidak berubah.</small></span></label>
    </div>
  </section>
<?php
}

/** Tombol + kerangka jendela "Ambil dari Bank Soal" untuk formulir pembuat game. */
function bank_panel($kodeTemplate)
{
    ?>
  <div class="bank-ajak" id="bank-root" data-t="<?= e($kodeTemplate) ?>" data-api="<?= e(url('guru/bank_api.php')) ?>">
    <div><b>📚 Mulai dari Bank Soal?</b>
      <small>Cari soal buatanmu sendiri atau soal publik guru lain, lalu tambahkan semua atau sebagian ke game ini. Soal otomatis disesuaikan dengan jenis game ini.</small></div>
    <button type="button" class="btn primary" data-bank-buka>📚 Ambil dari Bank Soal</button>
  </div>
  <script src="<?= e(url('assets/js/bank.js')) ?>"></script>
<?php
}

// ---------- Soal kanonik ----------

function bank_soal_kanon(array $game, array $tpl)
{
    $m = $tpl[$game['template']] ?? null;
    if (!$m || ($m['mode'] ?? 'soal') !== 'soal') return [];
    $d = json_decode((string)$game['data'], true);
    if (!is_array($d)) return [];
    $tetap = isset($m['opsi_tetap']) && is_array($m['opsi_tetap']) ? array_values($m['opsi_tetap']) : null;
    $pasang = !$tetap && empty($m['live']) && (int)($m['jumlah_opsi'] ?? 4) === 1;
    $out = [];
    foreach ((array)($d['soal'] ?? []) as $s) {
        if (!is_array($s)) continue;
        $q = trim((string)($s['q'] ?? ''));
        if (!empty($m['live'])) {
            if (($s['t'] ?? 'pg') === 'isian') {
                $c = ['t' => 'isian', 'q' => $q, 'j' => array_values(array_map('strval', (array)($s['j'] ?? [])))];
            } else {
                $c = ['t' => 'pg', 'q' => $q, 'o' => array_values(array_map('strval', (array)($s['o'] ?? []))), 'b' => (int)($s['b'] ?? 0)];
            }
            if (isset($s['w'])) $c['w'] = (int)$s['w'];
            if (!empty($s['k'])) $c['k'] = (string)$s['k'];
        } elseif ($tetap) {
            $c = ['t' => 'pg', 'q' => $q, 'o' => $tetap, 'b' => (int)($s['b'] ?? 0)];
        } elseif ($pasang) {
            $o = (array)($s['o'] ?? []);
            $c = ['t' => 'isian', 'q' => $q, 'j' => isset($o[0]) && trim((string)$o[0]) !== '' ? [(string)$o[0]] : []];
        } else {
            $c = ['t' => 'pg', 'q' => $q, 'o' => array_values(array_map('strval', (array)($s['o'] ?? []))), 'b' => (int)($s['b'] ?? 0)];
        }
        $out[] = $c;
    }
    return $out;
}

/** Teks jawaban benar untuk ditampilkan. */
function bank_jawaban_teks(array $c)
{
    if (($c['t'] ?? 'pg') === 'isian') return implode(' / ', $c['j'] ?? []);
    return (string)(($c['o'] ?? [])[$c['b'] ?? 0] ?? '');
}

// ---------- Konversi satu soal ke jenis game tujuan ----------
// Hasil: [status, item|null, pesan]   status: ok (utuh) | catatan (dipindah, ada bagian yang tidak terbawa) | skip (tidak bisa)

function bank_ke_target(array $c, array $m)
{
    $q = trim((string)($c['q'] ?? ''));
    if ($q === '') return ['skip', null, 'pertanyaan kosong'];
    $t = $c['t'] ?? 'pg';

    if (!empty($m['live'])) {                       // Tarik Tambang, RPG Battle: pg & isian dua-duanya didukung
        if ($t === 'isian') {
            if (!$c['j']) return ['skip', null, 'jawaban kosong'];
            $it = ['t' => 'isian', 'q' => $q, 'j' => $c['j']];
        } else {
            if (count($c['o']) < 2) return ['skip', null, 'pilihan jawaban kurang dari 2'];
            $it = ['t' => 'pg', 'q' => $q, 'o' => $c['o'], 'b' => $c['b']];
        }
        if (isset($c['w'])) $it['w'] = $c['w'];
        if (isset($c['k'])) $it['k'] = $c['k'];
        return ['ok', $it, ''];
    }

    $tetap = isset($m['opsi_tetap']) && is_array($m['opsi_tetap']) ? array_values($m['opsi_tetap']) : null;
    if ($tetap) {                                   // Benar atau Salah
        if ($t !== 'pg') return ['skip', null, 'soal isian tidak bisa dijadikan Benar/Salah'];
        if (count($c['o']) !== 2) return ['skip', null, 'bukan soal dengan dua pilihan ' . implode('/', $tetap)];
        $peta = [];
        foreach ($c['o'] as $i => $o) {
            $k = array_search(mb_strtolower(trim($o)), array_map(function ($x) { return mb_strtolower($x); }, $tetap), true);
            if ($k === false) return ['skip', null, 'pilihannya bukan ' . implode('/', $tetap)];
            $peta[$i] = $k;
        }
        if (count(array_unique($peta)) !== 2) return ['skip', null, 'pilihannya bukan ' . implode('/', $tetap)];
        return ['ok', ['q' => $q, 'o' => [], 'b' => $peta[$c['b']] ?? 0], ''];
    }

    if ((int)($m['jumlah_opsi'] ?? 4) === 1) {      // Pasangan, TTS: satu jawaban per soal
        if ($t === 'pg') {
            $jaw = trim((string)($c['o'][$c['b']] ?? ''));
            $cat = count($c['o']) > 1 ? 'hanya jawaban benar yang dipakai, pilihan lain tidak ikut' : '';
        } else {
            $jaw = trim((string)($c['j'][0] ?? ''));
            $cat = count($c['j']) > 1 ? 'hanya jawaban pertama yang dipakai, jawaban alternatif tidak ikut' : '';
        }
        if ($jaw === '') return ['skip', null, 'jawaban kosong'];
        if (!empty($m['jawaban_kata'])) {
            $k = normal_kata($jaw);
            $max = (int)($m['maks_huruf'] ?? 15);
            if (strlen($k) < 2 || strlen($k) > $max) return ['skip', null, "jawaban \"$jaw\" harus 2–$max huruf/angka (satu kata)"];
        }
        return [$cat ? 'catatan' : 'ok', ['q' => $q, 'o' => [$jaw], 'b' => 0], $cat];
    }

    // Kuis, Labirin: pilihan ganda
    if ($t !== 'pg') return ['skip', null, 'soal isian tidak punya pilihan jawaban'];
    $maks = (int)($m['jumlah_opsi'] ?? 4);
    if (count($c['o']) > $maks) return ['skip', null, "pilihan melebihi $maks"];
    if (count($c['o']) < (int)($m['min_opsi'] ?? 2)) return ['skip', null, 'pilihan jawaban kurang'];
    return ['ok', ['q' => $q, 'o' => $c['o'], 'b' => $c['b']], ''];
}

/** Konversi seluruh daftar soal kanonik. Mengembalikan per soal: [no, status, item, pesan] dan ringkasannya. */
function bank_konversi_daftar(array $kanon, array $m)
{
    $baris = []; $n = ['ok' => 0, 'catatan' => 0, 'skip' => 0];
    foreach ($kanon as $i => $c) {
        list($st, $item, $msg) = bank_ke_target($c, $m);
        $n[$st]++;
        $baris[] = ['no' => $i + 1, 'st' => $st, 'item' => $item, 'msg' => $msg, 'q' => $c['q'] ?? ''];
    }
    return ['baris' => $baris, 'n' => $n, 'bisa' => $n['ok'] + $n['catatan']];
}

/** Batas jumlah soal jenis tujuan: [min, max]. */
function bank_batas(array $m)
{
    if (($m['kode'] ?? '') === 'rpg_battle') return [8, 500];
    return [(int)($m['min_soal'] ?? 1), (int)($m['max_soal'] ?? 100)];
}

/**
 * Susun kolom `data` game baru dari soal yang sudah dikonversi. Pengaturan = bawaan jenis tujuan.
 * Mengembalikan [data|null, errors[]].
 */
function bank_data_baru(array $items, array $m, array $sumberData, $sumberTpl, $ident)
{
    $err = [];
    if (!empty($m['live'])) {
        $jenis = [];
        foreach ($items as $it) $jenis[$it['t']] = true;
        $jenis = array_values(array_intersect(['pg', 'isian'], array_keys($jenis)));
        if (($m['kode'] ?? '') === 'rpg_battle') {
            require_once __DIR__ . '/rpg.php';
            $mode = ($sumberTpl === 'rpg_battle' && ($sumberData['mode_soal'] ?? '') === 'kategori') ? 'kategori' : 'biasa';
            $soal = rpg_parse_soal($items, $jenis, $err, $mode);
            $data = ['mode' => 'soal', 'jenis' => $jenis, 'mode_soal' => $mode, 'soal' => $soal, 'pengaturan' => rpg_cfg_bersih([]), 'identitas' => $ident];
        } else {
            $soal = live_parse_soal($items, $jenis, $err);
            $data = ['mode' => 'soal', 'jenis' => $jenis, 'soal' => $soal, 'pengaturan' => live_cfg_bersih([]), 'identitas' => $ident];
        }
    } else {
        list($soal, $err) = parse_soal($m, $items);
        $raw = [];
        foreach ($m['pengaturan'] ?? [] as $f) $raw[$f['nama']] = $f['default'] ?? '';
        $data = ['mode' => 'soal', 'pengaturan' => parse_pengaturan($m, $raw), 'soal' => $soal, 'identitas' => $ident];
    }
    return [$err ? null : $data, $err];
}

// ---------- Akses & daftar game ----------

/** Game yang boleh dilihat guru ini: miliknya sendiri + semua game publik guru lain (yang akunnya aktif). */
function bank_game_boleh_lihat($gid, $userId)
{
    return db_row("SELECT g.*, u.nama AS guru_nama, u.username AS guru_user FROM games g JOIN users u ON u.id=g.user_id
        WHERE g.id=? AND (g.user_id=? OR (g.visibilitas='public' AND u.aktif=1))", [(int)$gid, (int)$userId]);
}

function bank_game_daftar($userId, $scope, array $tpl)
{
    $sql = 'SELECT g.id, g.user_id, g.template, g.judul, g.data, g.visibilitas, g.updated_at, u.nama AS guru_nama, u.username AS guru_user
            FROM games g JOIN users u ON u.id=g.user_id WHERE ';
    if ($scope === 'saya') { $sql .= 'g.user_id=?'; }
    elseif ($scope === 'publik') { $sql .= "g.user_id<>? AND g.visibilitas='public' AND u.aktif=1"; }
    else { $sql .= "(g.user_id=? OR (g.visibilitas='public' AND u.aktif=1))"; }
    $sql .= ' ORDER BY g.updated_at DESC, g.id DESC';
    $out = [];
    foreach (db_rows($sql, [(int)$userId]) as $g) {
        $m = $tpl[$g['template']] ?? null;
        if (!$m || ($m['mode'] ?? 'soal') !== 'soal') continue;     // spin & pembagi kelompok tidak punya soal
        $g['kanon'] = bank_soal_kanon($g, $tpl);
        if (!$g['kanon']) continue;
        $g['milik'] = (int)$g['user_id'] === (int)$userId;
        $g['guru'] = $g['guru_nama'] ?: $g['guru_user'];
        $out[] = $g;
    }
    return $out;
}

/** Ringkasan game untuk daftar (tanpa isi soal). $mTarget (opsional) menghitung berapa soal yang cocok. */
function bank_game_ringkas(array $g, array $tpl, $mTarget = null)
{
    $r = ['id' => (int)$g['id'], 'judul' => $g['judul'], 'ikon' => $tpl[$g['template']]['ikon'] ?? '🎮',
        'jenis' => $tpl[$g['template']]['nama'] ?? $g['template'], 'guru' => $g['guru'], 'milik' => $g['milik'],
        'vis' => $g['visibilitas'], 'n' => count($g['kanon']), 'diubah' => date('d/m/Y', strtotime($g['updated_at']))];
    if ($mTarget) $r['cocok'] = bank_konversi_daftar($g['kanon'], $mTarget)['bisa'];
    return $r;
}

function bank_token_cari($q)
{
    $q = mb_strtolower(trim((string)$q));
    $t = array_values(array_filter(preg_split('/\s+/u', $q) ?: [], function ($x) { return $x !== ''; }));
    return array_slice($t, 0, 6);
}

function bank_cocok(array $token, $teks)
{
    $teks = mb_strtolower($teks);
    foreach ($token as $t) if (mb_strpos($teks, $t) === false) return false;
    return true;
}

function bank_teks_soal(array $c)
{
    return ($c['q'] ?? '') . ' ' . implode(' ', $c['o'] ?? []) . ' ' . implode(' ', $c['j'] ?? []);
}

/** Kunci identitas soal: pertanyaan + semua pilihan + jawaban benar sama (tanpa peduli huruf besar/kecil & spasi). */
function bank_kunci_soal(array $c)
{
    $n = function ($s) { return preg_replace('/\s+/u', ' ', mb_strtolower(trim((string)$s))); };
    if (($c['t'] ?? 'pg') === 'isian') {
        $j = array_map($n, $c['j'] ?? []); sort($j);
        return 'i|' . $n($c['q']) . '|' . implode('~', $j);
    }
    $o = array_map($n, $c['o'] ?? []); sort($o);
    return 'p|' . $n($c['q']) . '|' . implode('~', $o) . '|' . $n($c['o'][$c['b'] ?? 0] ?? '');
}

/**
 * Cari di judul game, nama guru, dan isi soal (pertanyaan, pilihan, jawaban).
 * Soal yang identik di beberapa game (hasil salin) digabung: ditampilkan sekali, game lainnya masuk daftar 'serupa'.
 * Mengembalikan ['games' => game yang judul/gurunya cocok, 'soal' => [[game, indeks, kanon, serupa[]], ...], 'terpotong' => bool]
 */
function bank_cari($userId, $q, $scope, array $tpl, $batas = 120)
{
    $token = bank_token_cari($q);
    $hasil = ['games' => [], 'soal' => [], 'terpotong' => false];
    if (!$token) return $hasil;
    $peta = [];
    foreach (bank_game_daftar($userId, $scope, $tpl) as $g) {
        if (bank_cocok($token, $g['judul'] . ' ' . $g['guru'] . ' ' . $g['guru_user'])) $hasil['games'][] = $g;
        foreach ($g['kanon'] as $i => $c) {
            if (!bank_cocok($token, bank_teks_soal($c))) continue;
            $k = bank_kunci_soal($c);
            if (isset($peta[$k])) {                       // identik dengan soal yang sudah tampil: catat sebagai "juga ada di"
                $ref = &$hasil['soal'][$peta[$k]];
                $sudah = (int)$ref[0]['id'] === (int)$g['id'] || in_array((int)$g['id'], array_column($ref[3], 'id'), true);
                if (!$sudah) $ref[3][] = ['id' => (int)$g['id'], 'judul' => $g['judul'], 'guru' => $g['guru'], 'milik' => $g['milik']];
                unset($ref);
                continue;
            }
            if (count($hasil['soal']) >= $batas) { $hasil['terpotong'] = true; continue; }
            $peta[$k] = count($hasil['soal']);
            $hasil['soal'][] = [$g, $i, $c, []];
        }
    }
    return $hasil;
}
