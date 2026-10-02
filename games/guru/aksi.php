<?php
require __DIR__ . '/../inc/boot.php';
$u = require_login('guru');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('guru/game.php');
csrf_check();
$g = db_row('SELECT * FROM games WHERE id=? AND user_id=?', [(int)($_POST['id'] ?? 0), $u['id']]);
if (!$g) { flash('Game tidak ditemukan.', 'err'); redirect('guru/game.php'); }
$act = $_POST['act'] ?? '';

if ($act === 'toggle') {
    db_q('UPDATE games SET aktif=1-aktif WHERE id=?', [$g['id']]);
    flash($g['aktif'] ? 'Game ditutup. Murid akan melihat pesan "game sedang ditutup".' : 'Game dibuka. Murid bisa bermain lagi.');
    if (($_POST['back'] ?? '') === 'hasil') redirect('guru/hasil.php?id=' . $g['id']);
} elseif ($act === 'visibilitas') {
    $baru = $g['visibilitas'] === 'public' ? 'private' : 'public';
    db_q('UPDATE games SET visibilitas=? WHERE id=? AND user_id=?', [$baru, $g['id'], $u['id']]);
    flash($baru === 'public'
        ? 'Soal "' . $g['judul'] . '" sekarang PUBLIK: guru lain bisa melihat soal beserta jawabannya di Bank Soal.'
        : 'Soal "' . $g['judul'] . '" sekarang PRIVAT: hanya kamu yang bisa melihatnya.');
    if (($_POST['back'] ?? '') === 'hasil') redirect('guru/hasil.php?id=' . $g['id']);
} elseif ($act === 'hapus') {
    delete_game_folder($u['username'], $g['slug']);
    db_q('DELETE FROM games WHERE id=?', [$g['id']]);
    flash('Game dihapus.');
} elseif ($act === 'duplikat' || $act === 'ubah_jenis') {
    $tplBaru = $g['template'];
    $judul = $g['judul'] . ' (salinan)';
    if ($act === 'ubah_jenis') {
        $all = list_templates(true);
        $ke = $_POST['ke'] ?? '';
        $asal = $all[$g['template']] ?? null;
        if (!isset($all[$ke]) || !$asal || $all[$ke]['grup'] !== $asal['grup']) { flash('Jenis game tujuan tidak cocok.', 'err'); redirect('guru/game.php'); }
        $tplBaru = $ke;
        $judul = $g['judul'];
        // pengaturan tiap template berbeda: pakai bawaan template tujuan
        $d = json_decode($g['data'], true) ?: [];
        $d['pengaturan'] = parse_pengaturan($all[$ke], array_map(function ($f) { return $f['default'] ?? ''; }, array_column($all[$ke]['pengaturan'] ?? [], null, 'nama')));
        $max = (int)($all[$ke]['jumlah_opsi'] ?? 4);
        if (!empty($d['soal'])) foreach ($d['soal'] as &$s) {
            if (count($s['o']) > $max) {
                $benar = $s['o'][$s['b']];
                $lain = array_values(array_diff_key($s['o'], [$s['b'] => 1]));
                $s['o'] = array_merge([$benar], array_slice($lain, 0, $max - 1));
                $s['b'] = 0;
            }
        }
        unset($s);
        $g['data'] = json_encode($d, JSON_UNESCAPED_UNICODE);
    }
    db_q('INSERT INTO games(user_id,template,judul,slug,kelas,data,token,visibilitas) VALUES(?,?,?,?,?,?,?,?)',
        [$u['id'], $tplBaru, $judul, new_slug($judul), $g['kelas'], $g['data'], bin2hex(random_bytes(8)), 'private']);   // salinan selalu mulai PRIVAT
    $nid = (int)db()->lastInsertId();
    try { generate_game($nid); flash('Game baru dibuat dari "' . $g['judul'] . '".'); redirect('guru/hasil.php?id=' . $nid); }
    catch (Exception $e) { flash('Gagal membuat file game: ' . $e->getMessage(), 'err'); }
}
redirect('guru/game.php');
