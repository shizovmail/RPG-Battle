<?php
// Konversi game: soal & jawaban dipindah APA ADANYA ke game baru berjenis lain.
// Hanya pengaturan yang berbeda (kembali ke bawaan jenis tujuan, bisa diubah lewat Edit setelahnya).
// Sumber boleh game milik sendiri atau game PUBLIK guru lain (= memakai semua soal guru lain di game sendiri).
require __DIR__ . '/../inc/boot.php';
$u = require_login('guru');

$tpl = list_templates();
$src = bank_game_boleh_lihat((int)($_REQUEST['id'] ?? 0), $u['id']);
if (!$src || !isset($tpl[$src['template']])) { flash('Game tidak ditemukan atau tidak dibuka untukmu.', 'err'); redirect('guru/bank.php'); }
$milik = (int)$src['user_id'] === (int)$u['id'];
$guruSumber = $src['guru_nama'] ?: $src['guru_user'];
$mSrc = $tpl[$src['template']];
$kanon = bank_soal_kanon($src, $tpl);
if (!$kanon) { flash('Game ini tidak punya soal yang bisa dikonversi.', 'err'); redirect($milik ? 'guru/game.php' : 'guru/bank.php'); }
$sumberData = json_decode((string)$src['data'], true) ?: [];

// kandidat jenis tujuan: semua jenis aktif yang berbasis soal. Jenis yang sama hanya untuk game guru lain (itu "salin").
$kandidat = [];
foreach ($tpl as $kode => $m) {
    if (!$m['aktif'] || $m['mode'] !== 'soal') continue;
    if ($milik && $kode === $src['template']) continue;
    $kandidat[$kode] = $m;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ke = (string)($_POST['ke'] ?? '');
    if (!isset($kandidat[$ke])) { $errors[] = 'Jenis game tujuan tidak tersedia.'; }
    else {
        $mT = $kandidat[$ke];
        $hit = bank_konversi_daftar($kanon, $mT);
        $items = [];
        foreach ($hit['baris'] as $b) if ($b['st'] !== 'skip') $items[] = $b['item'];
        // identitas kelas hanya dibawa bila sumbernya milik sendiri (kelas guru lain bukan milikmu)
        $ident = $milik ? ($sumberData['identitas'] ?? ['cara' => 'bebas', 'kelas_id' => null]) : ['cara' => 'bebas', 'kelas_id' => null];
        list($data, $err) = bank_data_baru($items, $mT, $sumberData, $src['template'], $ident);
        if ($err) {
            $errors = array_merge(["Tidak bisa dikonversi ke {$mT['nama']}:"], $err);
        } else {
            $judul = mb_substr(trim((string)($_POST['judul'] ?? '')), 0, 100);
            if ($judul === '') $judul = mb_substr($src['judul'] . ' — ' . preg_replace('/\s*\(.*\)\s*$/u', '', $mT['nama']), 0, 100);
            $vis = vis_valid($_POST['visibilitas'] ?? 'private');
            db_q('INSERT INTO games(user_id,template,judul,slug,kelas,data,token,visibilitas) VALUES(?,?,?,?,?,?,?,?)',
                [$u['id'], $ke, $judul, new_slug($judul), $milik ? (string)$src['kelas'] : '', json_encode($data, JSON_UNESCAPED_UNICODE), bin2hex(random_bytes(8)), $vis]);
            $nid = (int)db()->lastInsertId();
            try {
                generate_game($nid);
                $lewat = $hit['n']['skip']; $cat = $hit['n']['catatan'];
                $ket = $lewat ? ' (' . $lewat . ' soal tidak cocok dengan jenis ini dan dilewati)'
                    : ($cat ? ' (' . $cat . ' soal hanya membawa jawaban benarnya, sesuai batasan jenis ini)' : ' dengan soal & jawaban yang sama persis');
                flash('Game baru "' . $judul . '" dibuat dari "' . $src['judul'] . '": ' . count($items) . ' soal dipindah' . $ket
                    . '. Pengaturan memakai bawaan — silakan sesuaikan di bawah lalu simpan.');
                redirect('guru/buat.php?id=' . $nid);
            } catch (Exception $e) {
                $errors[] = 'Game tersimpan, tetapi file gagal dibuat: ' . $e->getMessage();
            }
        }
    }
}

// pratinjau kecocokan untuk tiap jenis tujuan
$prat = [];
foreach ($kandidat as $kode => $m) {
    $h = bank_konversi_daftar($kanon, $m);
    list($min, $max) = bank_batas($m);
    $masalah = '';
    if ($h['bisa'] < $min) $masalah = "Hanya $h[bisa] soal yang cocok; jenis ini butuh minimal $min soal.";
    elseif ($h['bisa'] > $max) $masalah = "Ada $h[bisa] soal; jenis ini maksimal $max soal. Gunakan Bank Soal untuk memilih sebagian soal.";
    $prat[$kode] = ['h' => $h, 'masalah' => $masalah];
}

page_header('Konversi game', $milik ? 'guru/game.php' : 'guru/bank.php');
?>
<a class="back" href="<?= e(url($milik ? 'guru/game.php' : 'guru/bank.php?game=' . $src['id'])) ?>"><?= $milik ? 'Game saya' : 'Kembali ke Bank Soal' ?></a>
<h1><?= $milik ? '🔁 Konversi game' : '📥 Pakai semua soal ini' ?></h1>
<div class="card">
  <p style="margin:0"><b><?= e($mSrc['ikon'] . ' ' . $src['judul']) ?></b>
    <?= bank_badge($src['visibilitas'], $milik) ?>
    <span class="muted small">· <?= e($mSrc['nama']) ?> · <?= count($kanon) ?> soal<?= $milik ? '' : ' · milik ' . e($guruSumber) ?></span></p>
  <p class="muted small" style="margin:8px 0 0">
    Soal & jawaban dipindahkan <b>persis sama</b> ke game baru; hanya pengaturannya yang kembali ke bawaan jenis tujuan
    (bisa langsung kamu ubah setelah game dibuat). Game asli tidak berubah. Soal yang tidak mungkin dipindah tanpa mengubah isinya
    <b>tidak dipaksakan</b> — kamu akan melihat daftarnya sebelum memutuskan.</p>
</div>
<?php if ($errors): ?><div class="flash err"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form method="post" class="build">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$src['id'] ?>">
  <section class="card">
    <div class="grid2">
      <label>Judul game baru <small>Kosongkan untuk otomatis: «<?= e($src['judul']) ?> — nama jenis game».</small>
        <input name="judul" maxlength="100" value="<?= e($_POST['judul'] ?? '') ?>" placeholder="<?= e($src['judul']) ?>"></label>
      <div>
        <b style="font-size:.93rem">Visibilitas game baru</b>
        <div class="vis-opsi" style="margin-top:6px">
          <label class="vis-pilih"><input type="radio" name="visibilitas" value="private" <?= vis_valid($_POST['visibilitas'] ?? 'private') === 'private' ? 'checked' : '' ?>><span><b>🔒 Privat</b><small>Hanya kamu.</small></span></label>
          <label class="vis-pilih"><input type="radio" name="visibilitas" value="public" <?= vis_valid($_POST['visibilitas'] ?? 'private') === 'public' ? 'checked' : '' ?>><span><b>🌐 Publik</b><small>Guru lain bisa melihat & memakai soalnya.</small></span></label>
        </div>
      </div>
    </div>
  </section>

  <h2>Pilih jenis game tujuan</h2>
  <div class="tpl-grid">
  <?php foreach ($kandidat as $kode => $m): $p = $prat[$kode]; $h = $p['h']; $n = $h['n']; ?>
    <div class="card tpl konv-kartu <?= $p['masalah'] ? 'dim' : '' ?>">
      <div class="tpl-ikon"><?= e($m['ikon']) ?></div>
      <h3><?= e($m['nama']) ?></h3>
      <p class="small" style="margin:6px 0">
        <?php if ($h['bisa'] === count($kanon) && !$n['catatan'] && $p['masalah']): ?>
          <span class="muted">Semua <?= count($kanon) ?> soal cocok dengan jenis ini.</span>
        <?php elseif ($h['bisa'] === count($kanon) && !$n['catatan']): ?>
          <span class="tag ok-tag">✓ Semua <?= count($kanon) ?> soal pindah utuh</span>
        <?php else: ?>
          <b><?= $h['bisa'] ?></b> dari <?= count($kanon) ?> soal bisa dipindah<?= $n['catatan'] ? ' (' . $n['catatan'] . ' dengan catatan)' : '' ?><?= $n['skip'] ? ', <b>' . $n['skip'] . '</b> tidak cocok' : '' ?>.
        <?php endif; ?>
      </p>
      <?php if ($n['catatan'] || $n['skip']): ?>
      <details class="small konv-rinci"><summary>Lihat rincian</summary>
        <ul>
          <?php $tampil = 0; foreach ($h['baris'] as $b): if ($b['st'] === 'ok') continue; if (++$tampil > 12) { echo '<li class="muted">…dan lainnya</li>'; break; } ?>
            <li><b>Soal <?= $b['no'] ?></b> <?= $b['st'] === 'skip' ? '<span style="color:var(--red)">dilewati</span>' : '<span style="color:#b98410">catatan</span>' ?> — <?= e($b['msg']) ?>
              <br><span class="muted"><?= e(mb_strimwidth($b['q'], 0, 70, '…')) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </details>
      <?php endif; ?>
      <?php if ($p['masalah']): ?><p class="small" style="color:var(--red);margin:8px 0"><?= e($p['masalah']) ?></p><?php endif; ?>
      <button class="btn primary wide" name="ke" value="<?= e($kode) ?>" <?= $p['masalah'] ? 'disabled' : '' ?>>Konversi ke <?= e(preg_replace('/\s*\(.*\)\s*$/u', '', $m['nama'])) ?></button>
    </div>
  <?php endforeach; ?>
  </div>
</form>
<?php page_footer();
