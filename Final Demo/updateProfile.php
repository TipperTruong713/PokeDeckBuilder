<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_ID'])) {
    header("Location: index.html");
    exit();
}

$user_id = $_SESSION['user_ID'];

$conn = new mysqli("localhost", "root", "", "PokeDeckBuilder");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check which form was submitted
if (isset($_POST['update_account'])) {
    // Update account information
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    
    // Check if at least one field is provided
    if (empty($username) && empty($email)) {
        echo "<script>alert('Please provide at least one field to update.'); window.location.href='profile.html';</script>";
        exit();
    }
    
    // Build update query dynamically
    $updates = array();
    $types = "";
    $params = array();
    
    if (!empty($username)) {
        $updates[] = "Username = ?";
        $types .= "s";
        $params[] = $username;
    }
    
    if (!empty($email)) {
        $updates[] = "Email = ?";
        $types .= "s";
        $params[] = $email;
    }
    
    // Add user_id to params
    $types .= "i";
    $params[] = $user_id;
    
    $sql = "UPDATE User SET " . implode(", ", $updates) . " WHERE User_ID = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    // Bind parameters dynamically
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo "<script>alert('Account information updated successfully!'); window.location.href='profile.html';</script>";
    } else {
        // Check for duplicate username or email
        if ($conn->errno == 1062) {
            echo "<script>alert('Username or email already exists. Please try different values.'); window.location.href='profile.html';</script>";
        } else {
            echo "<script>alert('Error updating account information.'); window.location.href='profile.html';</script>";
        }
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