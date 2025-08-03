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

$sql = "SELECT * FROM pokemon_card p JOIN card c ON p.Card_ID = c.Card_ID WHERE 1=1";
$params = array();

// search term filter
if (!empty($searchTerm)) {
    $sql .= " AND c.Card_Name LIKE ?";
    $params[] = "%" . $searchTerm . "%";
}

// pokemon type filter  
if (!empty($pokemonType)) {
    $sql .= " AND p.Type LIKE ?";
    $params[] = "%" . $pokemonType . "%";
}

// rarity filter
if (!empty($rarity)) {
    $sql .= " AND c.Rarity = ?";
    $params[] = $rarity;
}

// execute the query
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
    <!-- navigation header -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <span>PokéDeck Builder</span>
            </div>
            <div class="nav-links">
                <a href="dashboard.html" class="nav-link">Dashboard</a>
                <a href="mydecks.html" class="nav-link">My Decks</a>
                <a href="browseCard.html" class="nav-link active">Browse Cards</a>
                <a href="collection.html" class="nav-link">Collection</a>
                <a href="profile.html" class="nav-link">Profile</a>
                <a href="index.html" class="nav-link logout">Logout</a>
            </div>
        </div>
    </nav>

    <!-- browse cards container -->
    <div class="cards-container">
        <div class="cards-header">
            <h1>Browse Cards</h1>
        </div>

        <!-- filters section -->
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

        <!-- cards grid -->
        <div class="cards-grid">
            <?php if (isset($error_message)): ?>
                <p>Error: <?php echo $error_message; ?></p>
            <?php elseif (empty($cards)): ?>
                <p>No cards found matching your criteria.</p>
            <?php else: ?>
                <?php foreach ($cards as $card): ?>
                    <div class="card-item">
                        <div class="card-image">
                            <img src="<?php echo !empty($card['Card_Image']) ? $card['Card_Image'] : 'placeholder.jpg'; ?>" alt="<?php echo htmlspecialchars($card['Card_Name']); ?>">
                            <span class="card-rarity <?php echo strtolower($card['Rarity']); ?>"><?php echo ucfirst($card['Rarity']); ?></span>
                        </div>
                        <div class="card-info">
                            <h3><?php echo htmlspecialchars($card['Card_Name']); ?></h3>
                            <p class="card-type"><?php echo htmlspecialchars($card['Type']); ?> - <?php echo htmlspecialchars($card['Stage']); ?></p>
                            <p class="card-hp">HP <?php echo htmlspecialchars($card['HP']); ?></p>
                            <div class="card-actions">
                                <button class="view-btn">View Details</button>
                                <button class="add-btn">Add to Collection</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>