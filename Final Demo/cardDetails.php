<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if card_id is provided
if (!isset($_GET['card_id']) || !is_numeric($_GET['card_id'])) {
    header("Location: browse_cards.php");
    exit();
}

$card_id = (int)$_GET['card_id'];

// Database connection
$conn = new mysqli("localhost", "root", "", "PokeDeckBuilder");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get card details with Pokemon/Trainer specific information
$sql = "SELECT c.*, 
        pc.Stage, pc.Type as Pokemon_Type, pc.HP, pc.Weakness, pc.Ability, pc.Retreat_Cost,
        tc.Trainer_Card_Type
        FROM Card c
        LEFT JOIN Pokemon_Card pc ON c.Card_ID = pc.Card_ID
        LEFT JOIN Trainer_Card tc ON c.Card_ID = tc.Card_ID
        WHERE c.Card_ID = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $card_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: browse_cards.php");
    exit();
}

$card = $result->fetch_assoc();
$stmt->close();

// Get attacks if it's a Pokemon card
$attacks = array();
if (strtolower($card['Card_Type']) == 'pokemon') {
    $attack_sql = "SELECT Attack_Name, Attack_Damage, Attack_Description 
                   FROM Pokemon_Card_Attack 
                   WHERE Card_ID = ?";
    $stmt = $conn->prepare($attack_sql);
    $stmt->bind_param("i", $card_id);
    $stmt->execute();
    $attack_result = $stmt->get_result();
    
    while ($attack = $attack_result->fetch_assoc()) {
        $attacks[] = $attack;
    }
    $stmt->close();
}

$conn->close();

// Determine the back link based on referrer
$back_link = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'collection.php') !== false 
             ? 'collection.php' 
             : 'browse_cards.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pokemon Deck Builder - <?php echo htmlspecialchars($card['Card_Name']); ?></title>
    <link rel="stylesheet" href="styles.css">
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
                <a href="collection.php" class="nav-link">Collection</a>
                <a href="profile.html" class="nav-link">Profile</a>
                <a href="logout.php" class="nav-link logout">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Card Details Container -->
    <div class="card-details-container">
        <div class="card-details-header">
            <a href="<?php echo $back_link; ?>" class="back-btn">← Back</a>
            <h1><?php echo htmlspecialchars($card['Card_Name']); ?></h1>
        </div>

        <div class="card-details-content">
            <!-- Card Image Section -->
            <div class="card-image-section">
                <div class="card-image-large">
                    <?php 
                    $imagePath = 'default.jpg';
                    if (!empty($card['Card_Image'])) {
                        if (strpos($card['Card_Image'], 'uploads/') === 0) {
                            $imagePath = $card['Card_Image'];
                        } else {
                            $imagePath = 'uploads/' . $card['Card_Image'];
                        }
                        if (!file_exists($imagePath)) {
                            $imagePath = 'default.jpg';
                        }
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($card['Card_Name']); ?>">
                    <span class="card-rarity-large <?php echo strtolower(str_replace(' ', '-', $card['Rarity'])); ?>">
                        <?php echo htmlspecialchars($card['Rarity']); ?>
                    </span>
                </div>
            </div>

            <!-- Card Information Section -->
            <div class="card-info-section">
                <!-- Basic Card Info -->
                <div class="info-card">
                    <h2>Basic Information</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Card Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($card['Card_Name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Card Type:</span>
                            <span class="info-value"><?php echo htmlspecialchars($card['Card_Type']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Rarity:</span>
                            <span class="info-value"><?php echo htmlspecialchars($card['Rarity']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Artist:</span>
                            <span class="info-value"><?php echo htmlspecialchars($card['Artist'] ?: 'Unknown'); ?></span>
                        </div>
                    </div>
                    <?php if (!empty($card['Card_Description'])): ?>
                    <div class="info-description">
                        <span class="info-label">Description:</span>
                        <p><?php echo htmlspecialchars($card['Card_Description']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Pokemon Specific Info -->
                <?php if (strtolower($card['Card_Type']) == 'pokemon'): ?>
                <div class="info-card">
                    <h2>Pokémon Details</h2>
                    <div class="pokemon-stats">
                        <div class="stat-row">
                            <div class="stat-item">
                                <span class="stat-label">Stage:</span>
                                <span class="stat-value"><?php echo htmlspecialchars($card['Stage'] ?: 'Basic'); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Type:</span>
                                <span class="stat-value type-badge <?php echo strtolower($card['Pokemon_Type']); ?>">
                                    <?php echo htmlspecialchars($card['Pokemon_Type'] ?: 'Unknown'); ?>
                                </span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">HP:</span>
                                <span class="stat-value hp-value"><?php echo htmlspecialchars($card['HP'] ?: '?'); ?></span>
                            </div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-item">
                                <span class="stat-label">Weakness:</span>
                                <span class="stat-value type-badge <?php echo strtolower($card['Weakness']); ?>">
                                    <?php echo htmlspecialchars($card['Weakness'] ?: 'None'); ?>
                                </span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Retreat Cost:</span>
                                <span class="stat-value"><?php echo htmlspecialchars($card['Retreat_Cost'] ?? '0'); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Ability:</span>
                                <span class="stat-value"><?php echo htmlspecialchars($card['Ability'] ?: 'None'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pokemon Attacks -->
                <?php if (!empty($attacks)): ?>
                <div class="info-card">
                    <h2>Attacks</h2>
                    <div class="attacks-list">
                        <?php foreach ($attacks as $attack): ?>
                        <div class="attack-item">
                            <div class="attack-header">
                                <span class="attack-name"><?php echo htmlspecialchars($attack['Attack_Name']); ?></span>
                                <span class="attack-damage"><?php echo htmlspecialchars($attack['Attack_Damage']); ?></span>
                            </div>
                            <?php if (!empty($attack['Attack_Description'])): ?>
                            <p class="attack-description"><?php echo htmlspecialchars($attack['Attack_Description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Trainer Specific Info -->
                <?php if (strtolower($card['Card_Type']) == 'trainer'): ?>
                <div class="info-card">
                    <h2>Trainer Details</h2>
                    <div class="info-item">
                        <span class="info-label">Trainer Type:</span>
                        <span class="info-value"><?php echo htmlspecialchars($card['Trainer_Card_Type'] ?: 'Unknown'); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>