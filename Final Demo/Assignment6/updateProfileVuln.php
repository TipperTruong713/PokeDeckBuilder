<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

$user_id = $_SESSION['user_ID_VUL'];

$conn = new mysqli("localhost", "root", "", "PokeDeckBuilder");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Update password
$current_password = $_POST['current-password'];
$new_password = $_POST['new-password'];
$confirm_password = $_POST['confirm-new-password'];

// Check if new passwords match
if ($new_password !== $confirm_password) {
    echo "<script>alert('New passwords do not match.'); window.location.href='updateProfileVuln.php';</script>";
    exit();
}

// VULNERABLE: No prepared statements - susceptible to SQL injection at $new_password & $current_password
$update_sql = "UPDATE User SET Password = '$new_password' WHERE (User_ID = '$user_id') AND (Password = '$current_password')";
$result = $conn->query($update_sql);
if ($result) {
    echo "<script>alert('Password updated successfully!'); window.location.href='profileVuln.html';</script>";
} else {
    echo "<script>alert('Current password is incorrect or update failed.'); window.location.href='profileVuln.html';</script>";
}

$conn->close();
?>