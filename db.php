<?php
// ==========================================================
// db.php - database connection
// Edit the four values below if your MySQL settings differ.
// (XAMPP default: user "root", empty password.)
// ==========================================================
$host   = "localhost";
$user   = "root";
$pass   = "";
$dbname = "school_system";

// Make every database error throw an exception, on every PHP version,
// so the pages can show a friendly message instead of crashing.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $ex) {
    http_response_code(500);
    die("Database connection failed. Make sure MySQL is running, db.php has the right settings, and school_system.sql has been imported.");
}
