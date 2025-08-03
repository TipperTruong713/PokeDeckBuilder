<?php

session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: LoginPage.html');
    exit;
}

$user_id = $_SESSION['user_id'];

/* Update account info */
if (isset($_POST['update_account'])) {

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');

    if ($username === '' || $email === '') {
        header('Location: profile.html?msg=Please+fill+all+fields');
        exit;
    }

    /* uniqueness check (exclude self) */
    $chk = $pdo->prepare("SELECT 1 FROM user WHERE (user_name=? OR email=?) AND user_id<>?");
    $chk->execute([$username, $email, $user_id]);

    if ($chk->fetch()) {
        header('Location: profile.html?msg=Username+or+email+already+taken');
        exit;
    }

    /* update row */
    $upd = $pdo->prepare("UPDATE user SET user_name=?, email=? WHERE user_id=?");
    $upd->execute([$username, $email, $user_id]);

    header('Location: profile.html?msg=Account+updated+successfully');
    exit;
}

/*  Change password  */
if (isset($_POST['update_password'])) {

    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_new_password'] ?? '';

    if ($new === '' || $current === '' || $confirm === '') {
        header('Location: profile.html?msg=Please+fill+all+password+fields');
        exit;
    }
    if ($new !== $confirm) {
        header('Location: profile.html?msg=New+passwords+do+not+match');
        exit;
    }

    /* fetch current hash */
    $stmt = $pdo->prepare("SELECT password FROM user WHERE user_id=?");
    $stmt->execute([$user_id]);
    $hash = $stmt->fetchColumn();

    if (!$hash || !password_verify($current, $hash)) {
        header('Location: profile.html?msg=Current+password+incorrect');
        exit;
    }

    /* update password */
    $newHash = password_hash($new, PASSWORD_DEFAULT);
    $upd = $pdo->prepare("UPDATE user SET password=? WHERE user_id=?");
    $upd->execute([$newHash, $user_id]);

    header('Location: profile.html?msg=Password+updated+successfully');
    exit;
}

/* nothing matched */
header('Location: profile.html');
exit;
