<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, PORT);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');
?>
