<?php
function start_session()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('RGKSESS');
        session_set_cookie_params(['lifetime' => 0, 'path' => base_path() . '/', 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function current_user()
{
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $u = db_row('SELECT * FROM users WHERE id=? AND aktif=1', [(int)$_SESSION['uid']]);
        if (!$u) unset($_SESSION['uid']);
    }
    return $u;
}

function home_for($u) { return $u['role'] === 'admin' ? 'admin/' : 'guru/'; }

function require_login($role = null)
{
    $u = current_user();
    if (!$u) { flash('Silakan masuk terlebih dahulu.', 'err'); redirect('index.php'); }
    if ($role && $u['role'] !== $role) redirect(home_for($u));
    return $u;
}
