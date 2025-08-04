<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_ID'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

// Check if card_id is provided
if (!isset($_POST['card_id'])) {
    echo json_encode(['success' => false, 'message' => 'No card specified']);
    exit();
}

$user_id = $_SESSION['user_ID'];
$card_id = (int)$_POST['card_id'];

// Database connection
$conn = new mysqli("localhost", "root", "", "PokeDeckBuilder");

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Check if cards exist
$check_collection = "SELECT Collection_ID FROM Collection WHERE User_ID = ?";
$stmt = $conn->prepare($check_collection);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Create collection for user
    $create_collection = "INSERT INTO Collection (User_ID) VALUES (?)";
    $stmt = $conn->prepare($create_collection);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $collection_id = $conn->insert_id;
} else {
    $collection = $result->fetch_assoc();
    $collection_id = $collection['Collection_ID'];
}
$stmt->close();

// Check if card already exists in collection
$check_card = "SELECT Quantity_In_Collection FROM Collections_Cards WHERE Collection_ID = ? AND Card_ID = ?";
$stmt = $conn->prepare($check_card);
$stmt->bind_param("ii", $collection_id, $card_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Card exists, increment quantity
    $update_card = "UPDATE Collections_Cards SET Quantity_In_Collection = Quantity_In_Collection + 1 
                   WHERE Collection_ID = ? AND Card_ID = ?";
    $stmt = $conn->prepare($update_card);
    $stmt->bind_param("ii", $collection_id, $card_id);
} else {
    // Card doesn't exist, insert new
    $insert_card = "INSERT INTO Collections_Cards (Collection_ID, Card_ID, Quantity_In_Collection) VALUES (?, ?, 1)";
    $stmt = $conn->prepare($insert_card);
    $stmt->bind_param("ii", $collection_id, $card_id);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Card added to collection!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add card']);
}

$stmt->close();
$conn->close();
?>