<?php
session_start();
require "db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $_SESSION['login_error'] = "Please enter both username and password.";
    header("Location: login.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT u.ID, u.USERNAME, u.PASSWORD, u.FULLNAME, t.USERTYPE
    FROM tbl_users u
    JOIN tbl_usertype t ON u.USERTYPE_ID = t.ID
    WHERE u.USERNAME = ?
");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    if (password_verify($password, $user['PASSWORD'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['ID'];
        $_SESSION['username'] = $user['USERNAME'];
        $_SESSION['fullname'] = $user['FULLNAME'];
        $_SESSION['usertype'] = $user['USERTYPE'];
        header("Location: dashboard.php");
        exit;
    }
}

$_SESSION['login_error'] = "Invalid username or password.";
header("Location: login.php");
exit;
