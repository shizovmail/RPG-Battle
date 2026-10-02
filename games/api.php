<?php
// API publik untuk game murid: cek status game & simpan skor
require __DIR__ . '/config.php';
require __DIR__ . '/inc/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

$a = $_GET['a'] ?? '';
if ($a === 'status') {
    $g = db_row('SELECT aktif FROM games WHERE id=?', [(int)($_GET['id'] ?? 0)]);
    out(['aktif' => $g ? (bool)$g['aktif'] : false, 'ada' => (bool)$g]);
}

if ($a === 'skor' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($in)) out(['ok' => false, 'pesan' => 'Data tidak valid']);
    $g = db_row('SELECT id, token, aktif FROM games WHERE id=?', [(int)($in['id'] ?? 0)]);
    if (!$g || !hash_equals($g['token'], (string)($in['token'] ?? ''))) out(['ok' => false, 'pesan' => 'Game tidak dikenali']);
    if (!$g['aktif']) out(['ok' => false, 'pesan' => 'Game sedang ditutup']);
    $nama = mb_substr(trim(preg_replace('/\s+/', ' ', (string)($in['nama'] ?? ''))), 0, 50);
    $maks = (int)($in['maks'] ?? 0);
    $skor = (int)($in['skor'] ?? -1);
    $durasi = max(0, min(86400, (int)($in['durasi'] ?? 0)));
    if ($nama === '' || $maks < 1 || $maks > 500 || $skor < 0 || $skor > $maks) out(['ok' => false, 'pesan' => 'Nilai tidak valid']);
    // cegah kiriman ganda beruntun (nama sama dalam 5 detik)
    $dobel = db_val("SELECT COUNT(*) FROM skor WHERE game_id=? AND nama=? AND waktu >= datetime('now','localtime','-5 seconds')", [$g['id'], $nama]);
    if ($dobel) out(['ok' => true]);
    db_q('INSERT INTO skor(game_id,nama,skor,maks,durasi) VALUES(?,?,?,?,?)', [$g['id'], $nama, $skor, $maks, $durasi]);
    out(['ok' => true]);
}
http_response_code(400);
out(['ok' => false, 'pesan' => 'Permintaan tidak dikenal']);
