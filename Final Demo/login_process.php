<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli("localhost", "root", "", "PokeDeckBuilder");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user = $_POST['email'];
$pass = $_POST['password'];

$sql = "SELECT User_ID, Username FROM User WHERE Email = ? AND Password = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("ss", $user, $pass);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();

    $_SESSION['user_ID'] = $row['User_ID'];
    $_SESSION['username'] = $row['Username'];
    header("Location: dashboard.php");
    exit();
} else {
    echo "<script>alert('Invalid email or password.'); window.location.href='index.html';</script>";
}

$stmt->close();
$conn->close();
?>