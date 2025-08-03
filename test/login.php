<?php
session_start();
require_once 'db.php';

// Sanitize input
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($email) || empty($password)) {
    die('Please fill in all fields.');
}

$stmt = $pdo->prepare("SELECT user_id, user_name, password FROM user WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    // Valid login
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_name'] = $user['user_name'];
    $_SESSION['email'] = $email;

    header('Location: dashboard.html'); 
    exit();
}

header('Location: LoginPage.html?error=badcred');
exit;
?>