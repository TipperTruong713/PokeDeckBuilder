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

// Get total decks count
$sql = "SELECT COUNT(*) as total_decks FROM Deck WHERE User_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$total_decks = $result->fetch_assoc()['total_decks'];
$stmt->close();

// Get total cards collected
$sql = "SELECT SUM(Quantity_In_Collection) as total_cards 
        FROM Collections_Cards cc
        JOIN Collection c ON cc.Collection_ID = c.Collection_ID
        WHERE c.User_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$total_cards = $result->fetch_assoc()['total_cards'] ?? 0;
$stmt->close();

// Get recent decks (limit to 3)
$sql = "SELECT d.Deck_ID, d.Deck_Name, d.Deck_Type, 
        COUNT(dc.Card_ID) as card_count,
        GROUP_CONCAT(DISTINCT pc.Type) as types
        FROM Deck d
        LEFT JOIN Deck_Cards dc ON d.Deck_ID = dc.Deck_ID
        LEFT JOIN Card c ON dc.Card_ID = c.Card_ID
        LEFT JOIN Pokemon_Card pc ON c.Card_ID = pc.Card_ID
        WHERE d.User_ID = ?
        GROUP BY d.Deck_ID
        ORDER BY d.Deck_ID DESC
        LIMIT 3";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_decks = $stmt->get_result();
$stmt->close();

// Get username
$sql = "SELECT Username FROM User WHERE User_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$username = $result->fetch_assoc()['Username'];
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pokemon Deck Builder - Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <!-- Navigation Header -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <span>PokéDeck Builder</span>
            </div>
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="mydecks.html" class="nav-link">My Decks</a>
                <a href="browse_cards.php" class="nav-link">Browse Cards</a>
                <a href="collection.html" class="nav-link">Collection</a>
                <a href="profile.html" class="nav-link">Profile</a>
                <a href="logout.php" class="nav-link logout">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Dashboard Content -->
    <div class="dashboard-container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>Welcome back, <?php echo htmlspecialchars($username); ?>!</h1>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-content">
                    <h3>Total Decks</h3>
                    <p class="stat-number"><?php echo $total_decks; ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-content">
                    <h3>Cards Collected</h3>
                    <p class="stat-number"><?php echo $total_cards; ?></p>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Recent Decks Section -->
            <div class="content-card full-width">
                <h2>Recent Decks</h2>
                <div class="deck-list">
                    <?php 
                    if ($recent_decks->num_rows > 0) {
                        while ($deck = $recent_decks->fetch_assoc()) {
                            $types = $deck['types'] ? explode(',', $deck['types']) : [];
                            $type_display = !empty($types) ? implode('/', $types) . ' Type' : 'No Type';
                    ?>
                    <div class="deck-item">
                        <div class="deck-info">
                            <h4><?php echo htmlspecialchars($deck['Deck_Name']); ?></h4>
                            <p><?php echo htmlspecialchars($type_display); ?> • <?php echo $deck['card_count']; ?> cards</p>
                        </div>
                        <a href="deck-editor.php?deck_id=<?php echo $deck['Deck_ID']; ?>" class="edit-btn">Edit</a>
                    </div>
                    <?php 
                        }
                    } else {
                    ?>
                    <p style="text-align: center; color: #6b7280; padding: 20px;">No decks created yet. Start building your first deck!</p>
                    <?php } ?>
                </div>
                <a href="mydecks.php" class="action-btn">View All Decks</a>
            </div>
        </div>
    </div>
</body>
</html>
<?php
$conn->close();
?>