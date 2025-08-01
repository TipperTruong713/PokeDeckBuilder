<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

$user_id = $_SESSION['user_ID'];

$conn = new mysqli("localhost", "root", "", "PokeDeckBuilder");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check which form was submitted
if (isset($_POST['update_account'])) {
    // Update account information
    $username = $_POST['username'];
    $email = $_POST['email'];
    
    $sql = "UPDATE User SET Username = ?, Email = ? WHERE User_ID = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ssi", $username, $email, $user_id);
    
    if ($stmt->execute()) {
        echo "<script>alert('Account information updated successfully!'); window.location.href='profile.html';</script>";
    } else {
        echo "<script>alert('Error updating account information.'); window.location.href='profile.html';</script>";
    }
    
    $stmt->close();
    
} elseif (isset($_POST['update_password'])) {
    // Update password
    $current_password = $_POST['current-password'];
    $new_password = $_POST['new-password'];
    $confirm_password = $_POST['confirm-new-password'];
    
    // Check if new passwords match
    if ($new_password !== $confirm_password) {
        echo "<script>alert('New passwords do not match.'); window.location.href='profile.html';</script>";
        exit();
    }
    
    // Verify current password
    $sql = "SELECT Password FROM User WHERE User_ID = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        if ($row['Password'] === $current_password) {
            // Update password
            $update_sql = "UPDATE User SET Password = ? WHERE User_ID = ?";
            $update_stmt = $conn->prepare($update_sql);
            
            if (!$update_stmt) {
                die("Prepare failed: " . $conn->error);
            }
            
            $update_stmt->bind_param("si", $new_password, $user_id);
            
            if ($update_stmt->execute()) {
                echo "<script>alert('Password updated successfully!'); window.location.href='profile.html';</script>";
            } else {
                echo "<script>alert('Error updating password.'); window.location.href='profile.html';</script>";
            }
            
            $update_stmt->close();
        } else {
            echo "<script>alert('Current password is incorrect.'); window.location.href='profile.html';</script>";
        }
    }
    
    $stmt->close();
}

$conn->close();
?>