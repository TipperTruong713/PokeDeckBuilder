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

// Database connection
$conn = new mysqli("localhost", "root", "", "PokeDeckBuilder");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle deck deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_deck_id'])) {
    $delete_deck_id = (int)$_POST['delete_deck_id'];
    
    // Verify deck belongs to user before deleting
    $verify_sql = "SELECT Deck_ID FROM Deck WHERE Deck_ID = ? AND User_ID = ?";
    $stmt = $conn->prepare($verify_sql);
    $stmt->bind_param("ii", $delete_deck_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Delete deck (cascade will handle deck_cards)
        $delete_sql = "DELETE FROM Deck WHERE Deck_ID = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $delete_deck_id);
        $stmt->execute();
    }
    $stmt->close();
}

// Get all user's decks
$decks_sql = "SELECT d.*, 
              (SELECT COUNT(DISTINCT dc.Card_ID) FROM Deck_Cards dc WHERE dc.Deck_ID = d.Deck_ID) as unique_cards,
              COALESCE((SELECT SUM(dc.Quantity_In_Deck) FROM Deck_Cards dc WHERE dc.Deck_ID = d.Deck_ID), 0) as total_cards,
              (SELECT COUNT(DISTINCT pc.Type) 
               FROM Deck_Cards dc 
               JOIN Pokemon_Card pc ON dc.Card_ID = pc.Card_ID 
               WHERE dc.Deck_ID = d.Deck_ID) as type_count,
              (SELECT GROUP_CONCAT(DISTINCT pc.Type SEPARATOR ',') 
               FROM Deck_Cards dc 
               JOIN Pokemon_Card pc ON dc.Card_ID = pc.Card_ID 
               WHERE dc.Deck_ID = d.Deck_ID 
               LIMIT 3) as pokemon_types
              FROM Deck d 
              WHERE d.User_ID = ? 
              ORDER BY d.Deck_ID DESC";

$stmt = $conn->prepare($decks_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$decks = array();
while ($row = $result->fetch_assoc()) {
    $decks[] = $row;
}
$stmt->close();

// For each deck, get card counts by type
foreach ($decks as &$deck) {
    // Get Pokemon count (sum of quantities, not distinct cards)
    $pokemon_sql = "SELECT COALESCE(SUM(dc.Quantity_In_Deck), 0) as count 
                    FROM Deck_Cards dc 
                    JOIN Card c ON dc.Card_ID = c.Card_ID 
                    WHERE dc.Deck_ID = ? AND LOWER(c.Card_Type) = 'pokemon'";
    $stmt = $conn->prepare($pokemon_sql);
    $stmt->bind_param("i", $deck['Deck_ID']);
    $stmt->execute();
    $result = $stmt->get_result();
    $deck['pokemon_count'] = $result->fetch_assoc()['count'];
    $stmt->close();
    
    // Get Trainer count (sum of quantities, not distinct cards)
    $trainer_sql = "SELECT COALESCE(SUM(dc.Quantity_In_Deck), 0) as count 
                    FROM Deck_Cards dc 
                    JOIN Card c ON dc.Card_ID = c.Card_ID 
                    WHERE dc.Deck_ID = ? AND LOWER(c.Card_Type) = 'trainer'";
    $stmt = $conn->prepare($trainer_sql);
    $stmt->bind_param("i", $deck['Deck_ID']);
    $stmt->execute();
    $result = $stmt->get_result();
    $deck['trainer_count'] = $result->fetch_assoc()['count'];
    $stmt->close();
    
    // Get Energy count (sum of quantities, not distinct cards)
    $energy_sql = "SELECT COALESCE(SUM(dc.Quantity_In_Deck), 0) as count 
                   FROM Deck_Cards dc 
                   JOIN Card c ON dc.Card_ID = c.Card_ID 
                   WHERE dc.Deck_ID = ? AND LOWER(c.Card_Type) = 'energy'";
    $stmt = $conn->prepare($energy_sql);
    $stmt->bind_param("i", $deck['Deck_ID']);
    $stmt->execute();
    $result = $stmt->get_result();
    $deck['energy_count'] = $result->fetch_assoc()['count'];
    $stmt->close();
}
unset($deck); // Break the reference

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pokemon Deck Builder - My Decks</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="decks.css">
</head>
<body class="scrollable-page">
    <!-- Navigation Header -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <span>PokéDeck Builder</span>
            </div>
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="mydecks.php" class="nav-link active">My Decks</a>
                <a href="browse_cards.php" class="nav-link">Browse Cards</a>
                <a href="collection.php" class="nav-link">Collection</a>
                <a href="profile.html" class="nav-link">Profile</a>
                <a href="logout.php" class="nav-link logout">Logout</a>
            </div>
        </div>
    </nav>

    <!-- My Decks Container -->
    <div class="decks-container">
        <div class="decks-header">
            <h1>My Decks</h1>
            <a href="deck-editor.php" class="new-deck-btn">+ Create New Deck</a>
        </div>

        <!-- Decks Grid -->
        <div class="decks-grid">
            <?php if (empty($decks)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px;">
                    <p style="color: white; font-size: 18px; margin-bottom: 20px;">You haven't created any decks yet.</p>
                    <a href="deck-editor.php" class="new-deck-btn">Create Your First Deck</a>
                </div>
            <?php else: ?>
                <?php foreach ($decks as $deck): ?>
                    <div class="deck-card <?php echo ($deck['total_cards'] < 60) ? 'incomplete' : ''; ?>">
                        <div class="deck-header">
                            <h2><?php echo htmlspecialchars($deck['Deck_Name']); ?></h2>
                            <span class="deck-format"><?php echo htmlspecialchars($deck['Deck_Type'] ?: 'Standard'); ?></span>
                        </div>
                        
                        <?php if (!empty($deck['pokemon_types'])): ?>
                        <div class="deck-types">
                            <?php 
                            $types = explode(',', $deck['pokemon_types']);
                            foreach (array_slice($types, 0, 5) as $type): 
                            ?>
                                <span class="type-badge <?php echo strtolower(trim($type)); ?>">
                                    <?php echo htmlspecialchars(trim($type)); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="deck-stats">
                            <div class="deck-stat">
                                <span class="stat-label">Cards</span>
                                <span class="stat-value <?php echo ($deck['total_cards'] < 60) ? 'incomplete' : ''; ?>">
                                    <?php echo $deck['total_cards'] ?: 0; ?>/60
                                </span>
                            </div>
                            <div class="deck-stat">
                                <span class="stat-label">Pokemon</span>
                                <span class="stat-value"><?php echo $deck['pokemon_count']; ?></span>
                            </div>
                            <div class="deck-stat">
                                <span class="stat-label">Trainers</span>
                                <span class="stat-value"><?php echo $deck['trainer_count']; ?></span>
                            </div>
                            <div class="deck-stat">
                                <span class="stat-label">Energy</span>
                                <span class="stat-value"><?php echo $deck['energy_count']; ?></span>
                            </div>
                        </div>
                        
                        <p class="deck-description">
                            <?php echo htmlspecialchars($deck['Deck_Description'] ?: 'No description provided.'); ?>
                        </p>
                        
                        <div class="deck-actions">
                            <a href="deck-editor.php?deck_id=<?php echo $deck['Deck_ID']; ?>" class="edit-deck-btn">
                                Edit Deck
                            </a>
                            <form method="POST" action="mydecks.php" style="display: inline; flex: 1;">
                                <input type="hidden" name="delete_deck_id" value="<?php echo $deck['Deck_ID']; ?>">
                                <button type="submit" class="delete-deck-btn" 
                                        onclick="return confirm('Are you sure you want to delete this deck?')">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>