<?php
require_once '../config/bootstrap.php';
require_once '../config/flash.php';
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$adminExists = $stmt->fetchColumn();

if ($adminExists > 0) {
    set_flash('error', 'Admin already exists.');
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        "INSERT INTO users (fullname, email, password, role)
         VALUES (?, ?, ?, 'admin')"
    );
    $stmt->execute([$_POST['fullname'], $_POST['email'], $hash]);

    header("Location: login.php");
    exit;
}
?>

<form method="post">
    <input name="fullname" placeholder="Full Name" required>
    <input name="email" type="email" placeholder="Email" required>
    <input name="password" type="password" placeholder="Password" required>
    <button>Register</button>
</form>
