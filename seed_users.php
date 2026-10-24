<?php
// ==========================================================
// seed_users.php
// Log in as an Admin, open this page, and click the button.
// DELETE this file afterward - it's a demo-data tool, not for a live site.
// Bulk-inserts sample users (default 5,000) into tbl_users, spread
// across the five usertypes, with signup dates over the past year.
//
// Every generated account uses the password: Password123!
// (hashed once and reused - hashing 5,000 separate bcrypt passwords
// would take several minutes).
// ==========================================================
require "auth_check.php";
require "db.php";
require "layout.php";
$active = 'users';
require_access('users');   // Admin only
set_time_limit(300);

$firstNames = ['James','Mary','John','Patricia','Robert','Jennifer','Michael','Linda','William','Elizabeth',
    'David','Barbara','Richard','Susan','Joseph','Jessica','Thomas','Sarah','Charles','Karen',
    'Christopher','Nancy','Daniel','Lisa','Matthew','Betty','Anthony','Margaret','Mark','Sandra',
    'Paul','Ashley','Steven','Kimberly','Andrew','Emily','Kenneth','Donna','Joshua','Michelle'];

$lastNames = ['Smith','Johnson','Williams','Brown','Jones','Garcia','Miller','Davis','Rodriguez','Martinez',
    'Hernandez','Lopez','Gonzalez','Wilson','Anderson','Thomas','Taylor','Moore','Jackson','Martin',
    'Lee','Perez','Thompson','White','Harris','Sanchez','Clark','Ramirez','Lewis','Robinson',
    'Walker','Young','Allen','King','Wright','Scott','Torres','Nguyen','Hill','Flores'];

// Roughly realistic distribution: mostly students, fewer staff
$usertypeWeights = [
    'Student'    => 70,
    'Instructor' => 15,
    'Dean'       => 5,
    'Registrar'  => 5,
    'Admin'      => 5,
];

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_seed']) && csrf_valid()) {
    $count = max(1, min(20000, (int) ($_POST['count'] ?? 5000)));

    $typeIds = [];
    $res = $conn->query("SELECT ID, USERTYPE FROM tbl_usertype");
    while ($row = $res->fetch_assoc()) $typeIds[$row['USERTYPE']] = $row['ID'];

    $missing = array_diff(array_keys($usertypeWeights), array_keys($typeIds));
    if ($missing) {
        $message = "tbl_usertype is missing: " . implode(', ', $missing) . ". Import school_system.sql (or run migration_v3_roles_access.sql) first.";
    } else {
        $pool = [];
        foreach ($usertypeWeights as $type => $weight) {
            for ($i = 0; $i < $weight; $i++) $pool[] = $typeIds[$type];
        }

        $sharedHash = password_hash('Password123!', PASSWORD_DEFAULT);

        // Continue after the highest existing userNNNNN so re-running never collides
        $m = $conn->query("SELECT MAX(CAST(SUBSTRING(USERNAME, 5) AS UNSIGNED)) AS m FROM tbl_users WHERE USERNAME REGEXP '^user[0-9]+$'")->fetch_assoc();
        $startAt = ((int) $m['m']) + 1;

        $conn->autocommit(FALSE);
        $stmt = $conn->prepare("INSERT INTO tbl_users (USERNAME, PASSWORD, FULLNAME, USERTYPE_ID, CREATED_AT) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssis", $username, $sharedHash, $fullname, $usertypeId, $createdAt);

        for ($i = 0; $i < $count; $i++) {
            $username   = "user" . str_pad($startAt + $i, 5, '0', STR_PAD_LEFT);
            $fullname   = $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
            $usertypeId = $pool[array_rand($pool)];
            // Spread signups over the past 365 days, weighted toward recent months (a realistic growth curve)
            $daysAgo    = (int) (365 * (1 - sqrt(mt_rand(0, 10000) / 10000)));
            $createdAt  = date('Y-m-d H:i:s', strtotime("-$daysAgo days"));
            $stmt->execute();
        }

        $conn->commit();
        $conn->autocommit(TRUE);
        $message = number_format($count) . " users added (userNNNNN). Password for all of them: Password123!";
    }
}
render_header('Seed Sample Users', 'users', ['subtitle' => 'Demo-data tool. Delete seed_users.php before going live.']);
?>
<section class="card narrow-card">
    <?php if ($message): ?><div class="toast toast-success"><?= icon('check-circle', 18) ?><span><?= e($message) ?></span></div><?php endif; ?>
    <p class="lead">Adds sample login accounts across all five user types, so the Users page, charts and Excel export have plenty of data to work with.</p>
    <form method="post" class="stack">
        <?= csrf_field() ?>
        <div class="field">
            <label for="count">How many records?</label>
            <input type="number" id="count" name="count" value="5000" min="1" max="20000" required>
        </div>
        <button class="btn btn-primary" type="submit" name="btn_seed"><?= icon('plus', 16) ?> Insert records</button>
    </form>
</section>
<?php render_footer(); ?>
