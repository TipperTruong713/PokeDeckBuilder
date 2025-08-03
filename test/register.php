<?php

require 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: signup.html');           
    exit;
}

$username         = trim($_POST['username']         ?? '');
$email            = trim($_POST['email']            ?? '');
$password         =       $_POST['password']        ?? '';
$confirm_password =       $_POST['confirm_password']?? '';
$profile_image    =       $_FILES['profile_image']['name'] ?? '';

if ($password !== $confirm_password) {
    header('Location: signup.html?error=nomatch');
    exit;
}

$stmt = $pdo->prepare("SELECT 1 FROM user WHERE user_name = ? OR email = ? LIMIT 1");
$stmt->execute([$username, $email]);

if ($stmt->fetchColumn()) {
    header('Location: signup.html?error=taken');
    exit;
}

$profile_path = null;
if ($profile_image && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $dir = 'uploads/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $profile_path = $dir . time() . '_' . basename($profile_image);
    move_uploaded_file($_FILES['profile_image']['tmp_name'], $profile_path);
}

$hashed = password_hash($password, PASSWORD_DEFAULT);

$ins = $pdo->prepare(
    "INSERT INTO user (user_name,email,password,profile_image)
     VALUES (?,?,?,?)"
);
$ins->execute([$username,$email,$hashed,$profile_path]);

header('Location: LoginPage.html?registered=1');
exit;

