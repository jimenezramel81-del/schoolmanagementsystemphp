<?php
// ==========================================================
// auth_check.php - include at the top of every protected page
// ==========================================================
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/helpers.php";
require_once __DIR__ . "/access.php";
