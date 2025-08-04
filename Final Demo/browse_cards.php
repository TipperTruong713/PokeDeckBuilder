<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";     
$dbname = "pokedeckbuilder";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$cardType = isset($_GET['card-type']) ? $_GET['card-type'] : '';
$pokemonType = isset($_GET['pokemon-type']) ? $_GET['pokemon-type'] : '';
$rarity = isset($_GET['rarity']) ? $_GET['rarity'] : '';
$pack = isset($_GET['pack']) ? $_GET['pack'] : '';
$searchTerm = isset($_GET['search-cards']) ? $_GET['search-cards'] : '';

// Updated SQL to get all cards (Pokemon, Trainer, and Energy)
$sql = "SELECT c.Card_ID, c.Card_Name, c.Rarity, c.Artist, c.Card_Type, c.Card_Image, c.Card_Description,
               pc.HP, pc.Type as Pokemon_Type, pc.Stage,
               tc.Trainer_Card_Type
        FROM Card c
        LEFT JOIN Pokemon_Card pc ON c.Card_ID = pc.Card_ID
        LEFT JOIN Trainer_Card tc ON c.Card_ID = tc.Card_ID
        WHERE 1=1";

$params = array();

// Search term filter
if (!empty($searchTerm)) {
    $sql .= " AND c.Card_Name LIKE ?";
    $params[] = "%" . $searchTerm . "%";
}

// Card type filter
if (!empty($cardType)) {
    $sql .= " AND c.Card_Type = ?";
    $params[] = ucfirst($cardType);
}

// Pokemon type filter (only applies to Pokemon cards)
if (!empty($pokemonType) && $cardType !== 'trainer' && $cardType !== 'energy') {
    $sql .= " AND pc.Type = ?";
    $params[] = ucfirst($pokemonType);
}

// Rarity filter
if (!empty($rarity)) {
    $sql .= " AND c.Rarity = ?";
    $params[] = str_replace('-', ' ', ucfirst($rarity));
}

$sql .= " ORDER BY c.Card_Name ASC";

// Execute the query
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $cards = array();
    $error_message = "Error fetching cards: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pokemon Deck Builder - Browse Cards</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="scrollable-page">
    <!-- Navigation header -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <span>PokéDeck Builder</span>
            </div>
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="mydecks.php" class="nav-link">My Decks</a>
                <a href="browse_cards.php" class="nav-link active">Browse Cards</a>
                <a href="collection.php" class="nav-link">Collection</a>
                <a href="profile.html" class="nav-link">Profile</a>
                <a href="logout.php" class="nav-link logout">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Browse cards container -->
    <div class="cards-container">
        <div class="cards-header">
            <h1>Browse Cards</h1>
        </div>

        <!-- Filters section -->
        <form method="GET" action="browse_cards.php">
            <div class="filters-section">
                <div class="filter-group">
                    <label for="card-type">Card Type</label>
                    <select id="card-type" name="card-type">
                        <option value="">All Types</option>
                        <option value="pokemon" <?php echo ($cardType == 'pokemon') ? 'selected' : ''; ?>>Pokemon</option>
                        <option value="trainer" <?php echo ($cardType == 'trainer') ? 'selected' : ''; ?>>Trainer</option>
                        <option value="energy" <?php echo ($cardType == 'energy') ? 'selected' : ''; ?>>Energy</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="pokemon-type">Pokemon Type</label>
                    <select id="pokemon-type" name="pokemon-type">
                        <option value="">All Types</option>
                        <option value="fire" <?php echo ($pokemonType == 'fire') ? 'selected' : ''; ?>>Fire</option>
                        <option value="water" <?php echo ($pokemonType == 'water') ? 'selected' : ''; ?>>Water</option>
                        <option value="grass" <?php echo ($pokemonType == 'grass') ? 'selected' : ''; ?>>Grass</option>
                        <option value="electric" <?php echo ($pokemonType == 'electric') ? 'selected' : ''; ?>>Electric</option>
                        <option value="psychic" <?php echo ($pokemonType == 'psychic') ? 'selected' : ''; ?>>Psychic</option>
                        <option value="fighting" <?php echo ($pokemonType == 'fighting') ? 'selected' : ''; ?>>Fighting</option>
                        <option value="dark" <?php echo ($pokemonType == 'dark') ? 'selected' : ''; ?>>Dark</option>
                        <option value="steel" <?php echo ($pokemonType == 'steel') ? 'selected' : ''; ?>>Steel</option>
                        <option value="fairy" <?php echo ($pokemonType == 'fairy') ? 'selected' : ''; ?>>Fairy</option>
                        <option value="dragon" <?php echo ($pokemonType == 'dragon') ? 'selected' : ''; ?>>Dragon</option>
                        <option value="normal" <?php echo ($pokemonType == 'normal') ? 'selected' : ''; ?>>Normal</option>
                        <option value="ice" <?php echo ($pokemonType == 'ice') ? 'selected' : ''; ?>>Ice</option>
                        <option value="ghost" <?php echo ($pokemonType == 'ghost') ? 'selected' : ''; ?>>Ghost</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="rarity">Rarity</label>
                    <select id="rarity" name="rarity">
                        <option value="">All Rarities</option>
                        <option value="common" <?php echo ($rarity == 'common') ? 'selected' : ''; ?>>Common</option>
                        <option value="uncommon" <?php echo ($rarity == 'uncommon') ? 'selected' : ''; ?>>Uncommon</option>
                        <option value="rare" <?php echo ($rarity == 'rare') ? 'selected' : ''; ?>>Rare</option>
                        <option value="ultra-rare" <?php echo ($rarity == 'ultra-rare') ? 'selected' : ''; ?>>Ultra Rare</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="pack">Pack</label>
                    <select id="pack" name="pack">
                        <option value="">All Packs</option>
                        <option value="base-set" <?php echo ($pack == 'base-set') ? 'selected' : ''; ?>>Base Set</option>
                        <option value="jungle" <?php echo ($pack == 'jungle') ? 'selected' : ''; ?>>Jungle</option>
                        <option value="fossil" <?php echo ($pack == 'fossil') ? 'selected' : ''; ?>>Fossil</option>
                        <option value="team-rocket" <?php echo ($pack == 'team-rocket') ? 'selected' : ''; ?>>Team Rocket</option>
                    </select>
                </div>
                <div class="search-group">
                    <input type="text" id="search-cards" name="search-cards" placeholder="Search by card name..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit" class="search-btn">Search</button>
                </div>
            </div>
        </form>

        <!-- Cards grid -->
        <div class="cards-grid">
            <?php if (isset($error_message)): ?>
                <p>Error: <?php echo $error_message; ?></p>
            <?php elseif (empty($cards)): ?>
                <p>No cards found matching your criteria.</p>
            <?php else: ?>
                <?php foreach ($cards as $card): ?>
                    <div class="card-item">
                        <div class="card-image">
                            <?php 
                            // Check if card has an image, and prepend uploads/ path if it exists
                            $imagePath = 'default.jpg'; // Default placeholder
                            if (!empty($card['Card_Image'])) {
                                // If the Card_Image already contains uploads/, use as is
                                if (strpos($card['Card_Image'], 'uploads/') === 0) {
                                    $imagePath = $card['Card_Image'];
                                } else {
                                    // Otherwise, prepend uploads/ to the filename
                                    $imagePath = 'uploads/' . $card['Card_Image'];
                                }
                                
                                // Check if file actually exists, fallback to placeholder if not
                                if (!file_exists($imagePath)) {
                                    $imagePath = 'default.jpg';
                                }
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($card['Card_Name']); ?>">
                            <span class="card-rarity <?php echo strtolower(str_replace(' ', '-', $card['Rarity'])); ?>">
                                <?php echo htmlspecialchars($card['Rarity']); ?>
                            </span>
                        </div>
                        <div class="card-info">
                            <h3><?php echo htmlspecialchars($card['Card_Name']); ?></h3>
                            
                            <?php if ($card['Card_Type'] == 'pokemon'): ?>
                                <p class="card-type">
                                    <?php echo htmlspecialchars($card['Pokemon_Type'] ?: 'Unknown'); ?> - 
                                    <?php echo htmlspecialchars($card['Stage'] ?: 'Basic'); ?>
                                </p>
                                <p class="card-hp">HP <?php echo htmlspecialchars($card['HP'] ?: '?'); ?></p>
                            <?php elseif ($card['Card_Type'] == 'trainer'): ?>
                                <p class="card-type">
                                    Trainer - <?php echo htmlspecialchars($card['Trainer_Card_Type'] ?: 'Unknown'); ?>
                                </p>
                            <?php else: ?>
                                <p class="card-type"><?php echo htmlspecialchars($card['Card_Type']); ?></p>
                            <?php endif; ?>
                            
                            <div class="card-actions">
                                <button class="view-btn" onclick="viewCard(<?php echo $card['Card_ID']; ?>)">View Details</button>
                                <button class="add-btn" onclick="addToCollection(<?php echo $card['Card_ID']; ?>)">Add to Collection</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <button class="page-btn">Previous</button>
            <span class="page-info">Page 1 of 1</span>
            <button class="page-btn">Next</button>
        </div>
    </div>

    <script>
        function viewCard(cardId) {
            window.location.href = 'cardDetails.php?card_id=' + cardId;
        }
        
        function addToCollection(cardId) {
            // Send request to add card to collection
            fetch('add_to_collection.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'card_id=' + cardId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error adding card to collection');
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html>