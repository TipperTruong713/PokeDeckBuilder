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

// VULNERABLE: No prepared statements - susceptible to SQL injection
$sql = "SELECT User_ID, Username FROM User WHERE Email = '$user' AND Password = '$pass'";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();

    $_SESSION['user_ID_VUL'] = $row['User_ID'];
    $_SESSION['username_VUL'] = $row['Username'];
    //print_r("Logged into account: " . $row['Username'] . " using password: " . $pass);
    //echo $row;
    echo "<script>alert('Logged into account: " . $row['Username'] . " using password: " . $pass . "'); window.location.href='profileVuln.html';</script>";
    exit();
} else {
    echo "<script>alert('Invalid email or password.'); window.location.href='loginVuln.html';</script>";
}

$conn->close();
?>