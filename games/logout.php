<?php
require __DIR__ . '/inc/boot.php';
$_SESSION = [];
session_destroy();
header('Location: ' . url('index.php'));
