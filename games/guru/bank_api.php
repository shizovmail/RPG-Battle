<?php
// API JSON Bank Soal (hanya baca) untuk jendela "Ambil dari Bank Soal" di formulir pembuat game.
// Hanya mengembalikan game milik guru yang masuk + game PUBLIK guru lain. Game privat orang lain tidak pernah keluar dari sini.
require __DIR__ . '/../inc/boot.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function api_keluar($arr, $kode = 200)
{
    http_response_code($kode);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$u = current_user();
if (!$u || $u['role'] !== 'guru') api_keluar(['error' => 'Silakan masuk sebagai guru.'], 401);

$tpl = list_templates();
$kodeT = (string)($_GET['t'] ?? '');
$mT = $tpl[$kodeT] ?? null;
if (!$mT || ($mT['mode'] ?? '') !== 'soal') api_keluar(['error' => 'Jenis game tujuan tidak dikenal.'], 400);
$scope = in_array($_GET['scope'] ?? '', ['saya', 'publik'], true) ? $_GET['scope'] : 'semua';
$aksi = (string)($_GET['aksi'] ?? 'daftar');

// bentuk satu soal untuk dikirim ke peramban: tampilan (s) + status konversi + item siap tambah
function api_soal($i, array $c, $st, $item, $msg)
{
    $s = ['t' => $c['t'], 'q' => $c['q']];
    if ($c['t'] === 'isian') $s['j'] = $c['j']; else { $s['o'] = $c['o']; $s['b'] = $c['b']; }
    return ['i' => $i, 's' => $s, 'st' => $st, 'msg' => $msg] + ($st !== 'skip' ? ['item' => $item] : []);
}

if ($aksi === 'daftar') {
    $out = [];
    foreach (bank_game_daftar($u['id'], $scope, $tpl) as $g) $out[] = bank_game_ringkas($g, $tpl, $mT);
    api_keluar(['games' => $out]);
}

if ($aksi === 'soal') {
    $g = bank_game_boleh_lihat((int)($_GET['game'] ?? 0), $u['id']);
    if (!$g) api_keluar(['error' => 'Game tidak ditemukan atau tidak dibuka untukmu.'], 404);
    $g['milik'] = (int)$g['user_id'] === (int)$u['id'];
    $g['guru'] = $g['guru_nama'] ?: $g['guru_user'];
    $g['kanon'] = bank_soal_kanon($g, $tpl);
    $k = bank_konversi_daftar($g['kanon'], $mT);
    $soal = [];
    foreach ($k['baris'] as $i => $b) $soal[] = api_soal($i, $g['kanon'][$i], $b['st'], $b['item'], $b['msg']);
    api_keluar(['game' => bank_game_ringkas($g, $tpl, $mT), 'soal' => $soal]);
}

if ($aksi === 'cari') {
    $h = bank_cari($u['id'], $_GET['q'] ?? '', $scope, $tpl);
    $games = [];
    foreach ($h['games'] as $g) $games[] = bank_game_ringkas($g, $tpl, $mT);
    $soal = [];
    foreach ($h['soal'] as list($g, $i, $c, $serupa)) {
        list($st, $item, $msg) = bank_ke_target($c, $mT);
        $soal[] = ['game' => bank_game_ringkas($g, $tpl), 'serupa' => $serupa] + api_soal($i, $c, $st, $item, $msg);
    }
    api_keluar(['games' => $games, 'soal' => $soal, 'terpotong' => $h['terpotong']]);
}

api_keluar(['error' => 'Aksi tidak dikenal.'], 400);
