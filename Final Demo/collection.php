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

// Handle quantity updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && isset($_POST['card_id'])) {
        $card_id = (int)$_POST['card_id'];
        $action = $_POST['action'];
        
        // Get user's collection ID
        $collection_query = "SELECT Collection_ID FROM Collection WHERE User_ID = ?";
        $stmt = $conn->prepare($collection_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $collection = $result->fetch_assoc();
            $collection_id = $collection['Collection_ID'];
            
            if ($action == 'increase') {
                // Increase quantity
                $update_query = "UPDATE Collections_Cards SET Quantity_In_Collection = Quantity_In_Collection + 1 
                               WHERE Collection_ID = ? AND Card_ID = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("ii", $collection_id, $card_id);
                $stmt->execute();
            } elseif ($action == 'decrease') {
                // Decrease quantity (minimum 1)
                $update_query = "UPDATE Collections_Cards SET Quantity_In_Collection = GREATEST(Quantity_In_Collection - 1, 1) 
                               WHERE Collection_ID = ? AND Card_ID = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("ii", $collection_id, $card_id);
                $stmt->execute();
            } elseif ($action == 'remove') {
                // First, remove the card from user's decks
                $remove_from_decks_query = "DELETE dc FROM Deck_Cards dc 
                                            INNER JOIN Deck d ON dc.Deck_ID = d.Deck_ID 
                                            WHERE d.User_ID = ? AND dc.Card_ID = ?";
                $stmt = $conn->prepare($remove_from_decks_query);
                $stmt->bind_param("ii", $user_id, $card_id);
                $stmt->execute();
                $stmt->close();
    
                // Then remove from collection
                $delete_query = "DELETE FROM Collections_Cards WHERE Collection_ID = ? AND Card_ID = ?";
                $stmt = $conn->prepare($delete_query);
                $stmt->bind_param("ii", $collection_id, $card_id);
                $stmt->execute();
            }
        }
        $stmt->close();
    }
}

// Get collection tracker
$stats_query = "SELECT 
                COUNT(DISTINCT cc.Card_ID) as unique_cards,
                SUM(cc.Quantity_In_Collection) as total_cards
                FROM Collection c
                JOIN Collections_Cards cc ON c.Collection_ID = cc.Collection_ID
                WHERE c.User_ID = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = $stats_result->fetch_assoc();
$unique_cards = $stats['unique_cards'] ?: 0;
$total_cards = $stats['total_cards'] ?: 0;
$stmt->close();

// Get filter parameters
$cardType = isset($_GET['collection-type']) ? $_GET['collection-type'] : '';
$rarity = isset($_GET['collection-rarity']) ? $_GET['collection-rarity'] : '';
$sortBy = isset($_GET['sort-by']) ? $_GET['sort-by'] : 'name';
$searchTerm = isset($_GET['search-collection']) ? $_GET['search-collection'] : '';

// Build query for collection cards
$sql = "SELECT c.Card_ID, c.Card_Name, c.Rarity, c.Card_Type, c.Card_Image,
               cc.Quantity_In_Collection,
               pc.HP, pc.Type as Pokemon_Type, pc.Stage,
               tc.Trainer_Card_Type,
               ec.Energy_Card_Type
        FROM Collection col
        JOIN Collections_Cards cc ON col.Collection_ID = cc.Collection_ID
        JOIN Card c ON cc.Card_ID = c.Card_ID
        LEFT JOIN Pokemon_Card pc ON c.Card_ID = pc.Card_ID
        LEFT JOIN Trainer_Card tc ON c.Card_ID = tc.Card_ID
        LEFT JOIN Energy_Card ec ON c.Card_ID = ec.Card_ID
        WHERE col.User_ID = ?";
        
$params = array($user_id);
$types = "i";

// Apply filters
if (!empty($searchTerm)) {
    $sql .= " AND c.Card_Name LIKE ?";
    $params[] = "%" . $searchTerm . "%";
    $types .= "s";
}

if (!empty($cardType)) {
    $sql .= " AND c.Card_Type = ?";
    $params[] = ucfirst($cardType);
    $types .= "s";
}

if (!empty($rarity)) {
    $sql .= " AND c.Rarity = ?";
    $params[] = str_replace('-', ' ', ucfirst($rarity));
    $types .= "s";
}

// Apply sorting
switch ($sortBy) {
    case 'quantity':
        $sql .= " ORDER BY cc.Quantity_In_Collection DESC";
        break;
    case 'rarity':
        $sql .= " ORDER BY FIELD(c.Rarity, 'Ultra Rare', 'Rare', 'Uncommon', 'Common')";
        break;
    case 'type':
        $sql .= " ORDER BY c.Card_Type ASC";
        break;
    default:
        $sql .= " ORDER BY c.Card_Name ASC";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$cards = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pokemon Deck Builder - My Collection</title>
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
                <a href="mydecks.php" class="nav-link">My Decks</a>
                <a href="browse_cards.php" class="nav-link">Browse Cards</a>
                <a href="collection.php" class="nav-link active">Collection</a>
                <a href="profile.html" class="nav-link">Profile</a>
                <a href="logout.php" class="nav-link logout">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Collection Container -->
    <div class="cards-container">
        <div class="cards-header">
            <h1>My Collection</h1>
        </div>

        <!-- Collection Stats -->
        <div class="collection-stats">
            <div class="stat-card">
                <div class="stat-content">
                    <h3>Total Cards</h3>
                    <p class="stat-number"><?php echo $total_cards; ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-content">
                    <h3>Unique Cards</h3>
                    <p class="stat-number"><?php echo $unique_cards; ?></p>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <form method="GET" action="collection.php">
            <div class="filters-section">
                <div class="filter-group">
                    <label for="collection-type">Card Type</label>
                    <select id="collection-type" name="collection-type">
                        <option value="">All Types</option>
                        <option value="pokemon" <?php echo ($cardType == 'pokemon') ? 'selected' : ''; ?>>Pokemon</option>
                        <option value="trainer" <?php echo ($cardType == 'trainer') ? 'selected' : ''; ?>>Trainer</option>
                        <option value="energy" <?php echo ($cardType == 'energy') ? 'selected' : ''; ?>>Energy</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="collection-rarity">Rarity</label>
                    <select id="collection-rarity" name="collection-rarity">
                        <option value="">All Rarities</option>
                        <option value="common" <?php echo ($rarity == 'common') ? 'selected' : ''; ?>>Common</option>
                        <option value="uncommon" <?php echo ($rarity == 'uncommon') ? 'selected' : ''; ?>>Uncommon</option>
                        <option value="rare" <?php echo ($rarity == 'rare') ? 'selected' : ''; ?>>Rare</option>
                        <option value="ultra-rare" <?php echo ($rarity == 'ultra-rare') ? 'selected' : ''; ?>>Ultra Rare</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="sort-by">Sort By</label>
                    <select id="sort-by" name="sort-by">
                        <option value="name" <?php echo ($sortBy == 'name') ? 'selected' : ''; ?>>Name</option>
                        <option value="quantity" <?php echo ($sortBy == 'quantity') ? 'selected' : ''; ?>>Quantity</option>
                        <option value="rarity" <?php echo ($sortBy == 'rarity') ? 'selected' : ''; ?>>Rarity</option>
                        <option value="type" <?php echo ($sortBy == 'type') ? 'selected' : ''; ?>>Type</option>
                    </select>
                </div>
                <div class="search-group">
                    <input type="text" id="search-collection" name="search-collection" placeholder="Search your collection..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit" class="search-btn">Search</button>
                </div>
            </div>
        </form>

        <!-- Collection Grid -->
        <div class="cards-grid">
            <?php if (empty($cards)): ?>
                <p>Your collection is empty. Start adding cards from the Browse Cards page!</p>
            <?php else: ?>
                <?php foreach ($cards as $card): ?>
                    <div class="card-item collection-card">
                        <div class="card-image">
                            <?php 
                            $imagePath = 'placeholder.jpg';
                            if (!empty($card['Card_Image'])) {
                                if (strpos($card['Card_Image'], 'uploads/') === 0) {
                                    $imagePath = $card['Card_Image'];
                                } else {
                                    $imagePath = 'uploads/' . $card['Card_Image'];
                                }
                                if (!file_exists($imagePath)) {
                                    $imagePath = 'placeholder.jpg';
                                }
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($card['Card_Name']); ?>">
                            <span class="card-rarity <?php echo strtolower(str_replace(' ', '-', $card['Rarity'])); ?>">
                                <?php echo htmlspecialchars($card['Rarity']); ?>
                            </span>
                            <span class="card-quantity">x<?php echo $card['Quantity_In_Collection']; ?></span>
                        </div>
                        <div class="card-info">
                            <h3><?php echo htmlspecialchars($card['Card_Name']); ?></h3>
                            
                            <?php if (strtolower($card['Card_Type']) == 'pokemon'): ?>
                                <p class="card-type">
                                    <?php echo htmlspecialchars($card['Pokemon_Type'] ?: 'Unknown'); ?> - 
                                    <?php echo htmlspecialchars($card['Stage'] ?: 'Basic'); ?>
                                </p>
                                <p class="card-hp">HP <?php echo htmlspecialchars($card['HP'] ?: '?'); ?></p>
                            <?php elseif (strtolower($card['Card_Type']) == 'trainer'): ?>
                                <p class="card-type">
                                    Trainer - <?php echo htmlspecialchars($card['Trainer_Card_Type'] ?: 'Unknown'); ?>
                                </p>
                            <?php elseif ($card['Card_Type'] == 'energy'): ?>
                                <p class="card-type">
                                    Energy - <?php echo htmlspecialchars($card['Energy_Card_Type'] ?: 'Unknown'); ?>
                                </p>
                            <?php else: ?>
                                <p class="card-type"><?php echo htmlspecialchars($card['Card_Type']); ?></p>
                            <?php endif; ?>
                            
                            <form method="POST" action="collection.php" class="quantity-controls">
                                <input type="hidden" name="card_id" value="<?php echo $card['Card_ID']; ?>">
                                <button type="submit" name="action" value="decrease" class="qty-btn minus">-</button>
                                <span class="qty-display"><?php echo $card['Quantity_In_Collection']; ?></span>
                                <button type="submit" name="action" value="increase" class="qty-btn plus">+</button>
                            </form>
                            
                            <div class="card-actions">
                                <button class="view-btn" onclick="viewCard(<?php echo $card['Card_ID']; ?>)">View Details</button>
                                <form method="POST" action="collection.php" style="display: inline;">
                                    <input type="hidden" name="card_id" value="<?php echo $card['Card_ID']; ?>">
                                    <button type="submit" name="action" value="remove" class="remove-btn">Remove</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function viewCard(cardId) {
            window.location.href = 'cardDetails.php?card_id=' + cardId;
        }
    </script>
</body>
</html>