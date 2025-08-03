<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Total decks
$stmt = $pdo->prepare("SELECT COUNT(*) FROM deck WHERE user_id = ?");
$stmt->execute([$user_id]);
$deck_count = $stmt->fetchColumn();

// Total cards in collection
$stmt = $pdo->prepare("SELECT SUM(quantity_in_collection) FROM collections_cards cc 
                       JOIN collection c ON cc.collection_id = c.collection_id 
                       WHERE c.user_id = ?");
$stmt->execute([$user_id]);
$card_count = $stmt->fetchColumn();
$card_count = $card_count ?: 0;

// Recent decks
$stmt = $pdo->prepare("SELECT d.deck_id, d.deck_name, d.deck_type,
                             COALESCE(SUM(dc.quantity_in_deck), 0) AS total_cards
                       FROM deck d
                       LEFT JOIN deck_cards dc ON d.deck_id = dc.deck_id
                       WHERE d.user_id = ?
                       GROUP BY d.deck_id
                       ORDER BY d.deck_id DESC
                       LIMIT 3");
$stmt->execute([$user_id]);
$recent_decks = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'deck_count' => $deck_count,
    'card_count' => $card_count,
    'recent_decks' => $recent_decks
]);
