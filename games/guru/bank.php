<?php
// Bank Soal: telusuri semua judul game (milikmu + publik guru lain), cari soal & jawaban, lihat isi, pakai di game lain.
require __DIR__ . '/../inc/boot.php';
$u = require_login('guru');
$tpl = list_templates();
$scope = in_array($_GET['scope'] ?? '', ['saya', 'publik'], true) ? $_GET['scope'] : 'semua';
$q = trim((string)($_GET['q'] ?? ''));
$gid = (int)($_GET['game'] ?? 0);

function bank_jawab_html(array $c)
{
    if (($c['t'] ?? 'pg') === 'isian') {
        return '<div class="bs-jawab">✍️ Jawaban: <b>' . e(implode(' / ', $c['j'])) . '</b></div>';
    }
    $h = '<ol class="bs-opsi" type="A">';
    foreach ($c['o'] as $i => $o) $h .= '<li class="' . ($i === (int)$c['b'] ? 'benar' : '') . '">' . e($o) . ($i === (int)$c['b'] ? ' <b>✓</b>' : '') . '</li>';
    return $h . '</ol>';
}
function bank_kartu_game(array $g, array $tpl, $q = '')
{
    $t = $tpl[$g['template']];
    $lihat = url('guru/bank.php?game=' . $g['id']);
    ?>
  <article class="card game bank-game">
    <div class="game-ikon"><?= e($t['ikon']) ?></div>
    <div class="game-info">
      <h3><?= e($g['judul']) ?> <?= bank_badge($g['visibilitas'], $g['milik']) ?></h3>
      <p class="muted small"><b><?= $g['milik'] ? 'Milikmu' : e($g['guru']) ?></b> · <?= e($t['nama']) ?> · <?= count($g['kanon']) ?> soal · diubah <?= e(date('d/m/Y', strtotime($g['updated_at']))) ?></p>
    </div>
    <div class="actions">
      <a class="btn small primary" href="<?= e($lihat) ?>">Lihat soal & jawaban</a>
      <a class="btn small" href="<?= e(url('guru/konversi.php?id=' . $g['id'])) ?>"><?= $g['milik'] ? 'Konversi' : 'Pakai semua' ?></a>
    </div>
  </article>
<?php
}

// ---------- Tampilan satu game ----------
if ($gid) {
    $g = bank_game_boleh_lihat($gid, $u['id']);
    if (!$g || !isset($tpl[$g['template']])) { flash('Game tidak ditemukan atau tidak dibuka untukmu.', 'err'); redirect('guru/bank.php'); }
    $milik = (int)$g['user_id'] === (int)$u['id'];
    $guru = $g['guru_nama'] ?: $g['guru_user'];
    $kanon = bank_soal_kanon($g, $tpl);
    $t = $tpl[$g['template']];
    page_header('Bank Soal: ' . $g['judul'], 'guru/bank.php');
    ?>
<a class="back" href="<?= e(url('guru/bank.php')) ?>">Bank Soal</a>
<div class="sec-head">
  <div>
    <h1><?= e($t['ikon'] . ' ' . $g['judul']) ?></h1>
    <p class="muted"><?= bank_badge($g['visibilitas'], $milik) ?> · <?= $milik ? 'milikmu' : 'dibuat oleh <b>' . e($guru) . '</b>' ?> · <?= e($t['nama']) ?> · <?= count($kanon) ?> soal</p>
  </div>
  <div class="actions">
    <a class="btn primary" href="<?= e(url('guru/konversi.php?id=' . $g['id'])) ?>"><?= $milik ? '🔁 Konversi ke jenis game lain' : '📥 Pakai semua soal ini' ?></a>
    <?php if ($milik): ?><a class="btn" href="<?= e(url('guru/buat.php?id=' . $g['id'])) ?>">Edit</a><?php endif; ?>
  </div>
</div>
<?php if (!$milik): ?><div class="flash ok">Ini soal publik milik <b><?= e($guru) ?></b>. Kamu bisa memakai <b>semua</b> soalnya lewat tombol di atas, atau <b>sebagian</b> lewat tombol <i>Ambil dari Bank Soal</i> saat membuat game. Kamu mendapat salinan — perubahan di game aslinya tidak memengaruhi gamemu.</div><?php endif; ?>
<?php foreach ($kanon as $i => $c): ?>
  <div class="card bs-soal">
    <div class="bs-no">Soal <?= $i + 1 ?><?= ($c['t'] ?? 'pg') === 'isian' ? ' · isian singkat' : '' ?></div>
    <p class="bs-q"><?= nl2br(e($c['q'])) ?></p>
    <?= bank_jawab_html($c) ?>
  </div>
<?php endforeach; ?>
<?php page_footer(); exit; }

// ---------- Daftar / pencarian ----------
$hasil = $q !== '' ? bank_cari($u['id'], $q, $scope, $tpl) : null;
$semua = $q === '' ? bank_game_daftar($u['id'], $scope, $tpl) : [];
$nSaya = db_val('SELECT COUNT(*) FROM games WHERE user_id=?', [$u['id']]);
page_header('Bank Soal', 'guru/bank.php');
?>
<div class="sec-head"><div><h1>📚 Bank Soal</h1>
  <p class="muted">Semua judul game dan soalnya: milikmu sendiri (privat maupun publik) dan soal <b>publik</b> dari guru lain.
  Cari kata kunci untuk melihat soal beserta jawabannya.</p></div></div>

<form class="filter" method="get">
  <input name="q" value="<?= e($q) ?>" placeholder="Cari soal, jawaban, judul game, atau nama guru…" autofocus>
  <select name="scope">
    <option value="semua" <?= $scope === 'semua' ? 'selected' : '' ?>>Semua (milikmu + publik)</option>
    <option value="saya" <?= $scope === 'saya' ? 'selected' : '' ?>>Hanya milikku</option>
    <option value="publik" <?= $scope === 'publik' ? 'selected' : '' ?>>Publik guru lain</option>
  </select>
  <button class="btn primary">Cari</button>
  <?php if ($q !== ''): ?><a class="btn" href="<?= e(url('guru/bank.php?scope=' . $scope)) ?>">Hapus pencarian</a><?php endif; ?>
</form>

<?php if ($hasil !== null): ?>
  <?php if (!$hasil['games'] && !$hasil['soal']): ?>
    <div class="card empty"><p>Tidak ada judul game, guru, atau soal yang cocok dengan “<?= e($q) ?>”.</p></div>
  <?php endif; ?>
  <?php if ($hasil['games']): ?>
    <h2>Game yang cocok (<?= count($hasil['games']) ?>)</h2>
    <div class="game-list"><?php foreach ($hasil['games'] as $g) bank_kartu_game($g, $tpl); ?></div>
  <?php endif; ?>
  <?php if ($hasil['soal']): ?>
    <h2>Soal yang cocok (<?= count($hasil['soal']) ?><?= $hasil['terpotong'] ? '+' : '' ?>)</h2>
    <?php if ($hasil['terpotong']): ?><p class="muted small">Hasil dibatasi; persempit kata kuncinya untuk hasil yang lebih tepat.</p><?php endif; ?>
    <?php foreach ($hasil['soal'] as list($g, $i, $c, $serupa)): $t = $tpl[$g['template']]; ?>
      <div class="card bs-soal">
        <div class="bs-no"><?= e($t['ikon']) ?> <a href="<?= e(url('guru/bank.php?game=' . $g['id'])) ?>"><?= e($g['judul']) ?></a>
          · <?= $g['milik'] ? 'milikmu' : e($g['guru']) ?> <?= bank_badge($g['visibilitas'], $g['milik']) ?> · soal <?= $i + 1 ?></div>
        <p class="bs-q"><?= nl2br(e($c['q'])) ?></p>
        <?= bank_jawab_html($c) ?>
        <?php if ($serupa): ?><p class="muted small" style="margin:8px 0 0">Soal yang sama juga ada di:
          <?php foreach (array_slice($serupa, 0, 4) as $k => $s): ?><?= $k ? ', ' : '' ?><a href="<?= e(url('guru/bank.php?game=' . $s['id'])) ?>"><?= e($s['judul']) ?></a> (<?= $s['milik'] ? 'milikmu' : e($s['guru']) ?>)<?php endforeach; ?><?= count($serupa) > 4 ? ' dan ' . (count($serupa) - 4) . ' lainnya' : '' ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
<?php else: ?>
  <?php if (!$semua): ?>
    <div class="card empty"><p><?= $scope === 'publik' ? 'Belum ada guru lain yang menjadikan soalnya publik.' : 'Belum ada game dengan soal.' ?></p>
      <a class="btn primary" href="<?= e(url('guru/')) ?>">Buat game</a></div>
  <?php else: ?>
    <h2>Semua judul (<?= count($semua) ?>)</h2>
    <div class="game-list"><?php foreach ($semua as $g) bank_kartu_game($g, $tpl); ?></div>
  <?php endif; ?>
<?php endif; ?>
<?php page_footer();
