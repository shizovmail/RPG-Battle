<?php
function data_dir()
{
    static $d = null;
    if ($d !== null) return $d;
    $d = DATA_DIR;
    if (!is_dir($d)) @mkdir($d, 0775, true);
    if (!is_dir($d) || !is_writable($d)) {
        $d = APP_DIR . DIRECTORY_SEPARATOR . 'data';
        if (!is_dir($d)) @mkdir($d, 0775, true);
        if (!file_exists($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "Require all denied\n");
    }
    return $d;
}

function db_file()
{
    return data_dir() . DIRECTORY_SEPARATOR . DB_FILE;
}

function db()
{
    static $pdo = null;
    if ($pdo) return $pdo;
    if (!extension_loaded('pdo_sqlite')) {
        http_response_code(500);
        die('Ekstensi <b>pdo_sqlite</b> belum aktif. Buka C:\\xampp\\php\\php.ini, hapus tanda ; di depan '
            . '<code>extension=pdo_sqlite</code> dan <code>extension=sqlite3</code>, lalu restart Apache.');
    }
    $pdo = new PDO('sqlite:' . db_file());
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    db_install($pdo);
    db_migrate($pdo);
    return $pdo;
}

function db_install(PDO $pdo)
{
    $ada = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
    if ($ada) return;
    $now = "(datetime('now','localtime'))";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        nama TEXT,
        role TEXT NOT NULL DEFAULT 'guru',
        aktif INTEGER NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT $now);
    CREATE TABLE IF NOT EXISTS settings(k TEXT PRIMARY KEY, v TEXT);
    CREATE TABLE IF NOT EXISTS templates(kode TEXT PRIMARY KEY, aktif INTEGER NOT NULL DEFAULT 1);
    CREATE TABLE IF NOT EXISTS kelas(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        nama TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS murid(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kelas_id INTEGER NOT NULL REFERENCES kelas(id) ON DELETE CASCADE,
        nama TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS games(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        template TEXT NOT NULL,
        judul TEXT NOT NULL,
        slug TEXT NOT NULL,
        kelas TEXT,
        data TEXT,
        token TEXT NOT NULL,
        aktif INTEGER NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT $now,
        updated_at TEXT DEFAULT $now);
    CREATE TABLE IF NOT EXISTS skor(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        game_id INTEGER NOT NULL REFERENCES games(id) ON DELETE CASCADE,
        nama TEXT NOT NULL,
        skor INTEGER NOT NULL,
        maks INTEGER NOT NULL,
        durasi INTEGER,
        waktu TEXT DEFAULT $now);
    CREATE INDEX IF NOT EXISTS idx_games_user ON games(user_id);
    CREATE INDEX IF NOT EXISTS idx_skor_game ON skor(game_id);
    ");
    $st = $pdo->prepare('INSERT INTO users(username,password,nama,role) VALUES(?,?,?,?)');
    $st->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Administrator', 'admin']);
    $st = $pdo->prepare('INSERT OR IGNORE INTO settings(k,v) VALUES(?,?)');
    foreach (['nama_sekolah' => 'SMP Kartini 2', 'tagline' => 'Belajar sambil bermain', 'url_publik' => DEFAULT_PUBLIC_URL, 'logo' => ''] as $k => $v) {
        $st->execute([$k, $v]);
    }
}

function db_q($sql, array $p = [])
{
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st;
}
function db_row($sql, array $p = []) { $r = db_q($sql, $p)->fetch(); return $r ?: null; }
function db_rows($sql, array $p = []) { return db_q($sql, $p)->fetchAll(); }
function db_val($sql, array $p = []) { return db_q($sql, $p)->fetchColumn(); }

// Migrasi bertahap (aman dijalankan berulang).
// Versi 2 = tabel game live (tarik tambang). Versi 3 = visibilitas soal (private/public) untuk Bank Soal.
function db_migrate(PDO $pdo)
{
    $v = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    if ($v >= 3) return;
    if ($v < 2) db_migrate_v2($pdo);
    db_migrate_v3($pdo);
}

function db_migrate_v3(PDO $pdo)
{
    $ada = false;
    foreach ($pdo->query('PRAGMA table_info(games)')->fetchAll() as $c) {
        if ($c['name'] === 'visibilitas') { $ada = true; break; }
    }
    // game lama otomatis PRIVAT: tidak ada soal guru yang tiba-tiba terbuka untuk guru lain
    if (!$ada) $pdo->exec("ALTER TABLE games ADD COLUMN visibilitas TEXT NOT NULL DEFAULT 'private'");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_games_vis ON games(visibilitas)');
    $pdo->exec('PRAGMA user_version = 3');
}

function db_migrate_v2(PDO $pdo)
{
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS live_sesi(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        game_id INTEGER NOT NULL REFERENCES games(id) ON DELETE CASCADE,
        status TEXT NOT NULL DEFAULT 'lobi',
        ronde INTEGER NOT NULL DEFAULT 0,
        total INTEGER NOT NULL DEFAULT 0,
        seed INTEGER NOT NULL DEFAULT 0,
        soal TEXT NOT NULL DEFAULT '[]',
        cfg TEXT NOT NULL DEFAULT '{}',
        pos INTEGER NOT NULL DEFAULT 0,
        riwayat TEXT NOT NULL DEFAULT '[]',
        ronde_mulai REAL NOT NULL DEFAULT 0,
        hasil_mulai REAL NOT NULL DEFAULT 0,
        sblm TEXT NOT NULL DEFAULT '',
        jeda_at REAL NOT NULL DEFAULT 0,
        pending INTEGER NOT NULL DEFAULT 0,
        pemenang INTEGER NOT NULL DEFAULT 0,
        akhir TEXT NOT NULL DEFAULT '{}',
        disimpan INTEGER NOT NULL DEFAULT 0,
        dibuat TEXT DEFAULT (datetime('now','localtime')));
    CREATE TABLE IF NOT EXISTS live_pemain(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sesi_id INTEGER NOT NULL REFERENCES live_sesi(id) ON DELETE CASCADE,
        token TEXT NOT NULL,
        nama TEXT NOT NULL,
        tim INTEGER NOT NULL DEFAULT 0,
        gabung_ke INTEGER,
        seen REAL NOT NULL DEFAULT 0);
    CREATE TABLE IF NOT EXISTS live_jawab(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sesi_id INTEGER NOT NULL REFERENCES live_sesi(id) ON DELETE CASCADE,
        pemain_id INTEGER NOT NULL REFERENCES live_pemain(id) ON DELETE CASCADE,
        ronde INTEGER NOT NULL,
        jawab TEXT,
        benar INTEGER NOT NULL DEFAULT 0,
        ms INTEGER NOT NULL DEFAULT 0,
        UNIQUE(sesi_id, pemain_id, ronde));
    CREATE INDEX IF NOT EXISTS idx_live_sesi_game ON live_sesi(game_id);
    CREATE INDEX IF NOT EXISTS idx_live_pemain_sesi ON live_pemain(sesi_id);
    CREATE INDEX IF NOT EXISTS idx_live_jawab_sesi ON live_jawab(sesi_id, ronde);
    ");
    $pdo->exec('PRAGMA user_version = 2');
}
