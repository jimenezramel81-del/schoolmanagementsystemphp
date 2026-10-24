<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/icons.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?= e(SCHOOL_NAME) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
<main class="login-wrap">
    <section class="login-hero">
        <span class="brand-badge big"><?= icon('book-open', 28) ?></span>
        <h1><?= e(SCHOOL_NAME) ?></h1>
        <p><?= e(SCHOOL_TAGLINE) ?></p>
        <ul>
            <li><?= icon('check-circle', 18) ?> Students, instructors &amp; enrollment</li>
            <li><?= icon('check-circle', 18) ?> Class schedules, grades &amp; attendance</li>
            <li><?= icon('check-circle', 18) ?> Printable reports &amp; Excel export</li>
        </ul>
    </section>

    <section class="login-card">
        <h2>Welcome back</h2>
        <p class="muted">Sign in to continue to your dashboard.</p>

        <?php if ($error): ?>
            <div class="form-error"><?= icon('alert', 18) ?><span><?= e($error) ?></span></div>
        <?php endif; ?>

        <form method="post" action="login_process.php" autocomplete="on">
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="username">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sign in</button>
        </form>
    </section>
</main>
</body>
</html>
