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
$deck_id = isset($_GET['deck_id']) ? (int)$_GET['deck_id'] : 0;

// Database connection
$conn = new mysqli("localhost", "root", "", "PokeDeckBuilder");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $deck_name = trim($_POST['deck_name']);
    $deck_type = $_POST['deck_type'];
    $deck_description = trim($_POST['deck_description']);
    $deck_cards = isset($_POST['card_quantity']) ? $_POST['card_quantity'] : array();
    
    // Get deck_id from POST if editing
    if (isset($_POST['deck_id']) && $_POST['deck_id'] > 0) {
        $deck_id = (int)$_POST['deck_id'];
    }
    
    if (empty($deck_name)) {
        $error_message = "Deck name is required.";
    } else {
        if ($deck_id > 0) {
            // Update existing deck
            $update_sql = "UPDATE Deck SET Deck_Name = ?, Deck_Type = ?, Deck_Description = ? 
                          WHERE Deck_ID = ? AND User_ID = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("sssii", $deck_name, $deck_type, $deck_description, $deck_id, $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Clear existing deck cards
            $clear_sql = "DELETE FROM Deck_Cards WHERE Deck_ID = ?";
            $stmt = $conn->prepare($clear_sql);
            $stmt->bind_param("i", $deck_id);
            $stmt->execute();
            $stmt->close();
        } else {
            // Create new deck
            $insert_sql = "INSERT INTO Deck (Deck_Name, Deck_Type, Deck_Description, User_ID) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("sssi", $deck_name, $deck_type, $deck_description, $user_id);
            $stmt->execute();
            $deck_id = $conn->insert_id;
            $stmt->close();
        }
        
        // Add cards to deck
        if (!empty($deck_cards)) {
            $insert_card_sql = "INSERT INTO Deck_Cards (Deck_ID, Card_ID, Quantity_In_Deck) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insert_card_sql);
            
            foreach ($deck_cards as $card_id => $quantity) {
                $quantity = (int)$quantity;
                if ($quantity > 0) {
                    $stmt->bind_param("iii", $deck_id, $card_id, $quantity);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }
        
        // Close connection before redirect
        $conn->close();
        
        header("Location: mydecks.php");
        exit();
    }
}

// Load deck data if editing
$deck_data = array(
    'Deck_Name' => '',
    'Deck_Type' => 'Standard',
    'Deck_Description' => ''
);

$deck_cards = array();

if ($deck_id > 0) {
    // Get deck info
    $deck_sql = "SELECT * FROM Deck WHERE Deck_ID = ? AND User_ID = ?";
    $stmt = $conn->prepare($deck_sql);
    $stmt->bind_param("ii", $deck_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $deck_data = $result->fetch_assoc();
        
        // Get cards in deck
        $cards_sql = "SELECT Card_ID, Quantity_In_Deck FROM Deck_Cards WHERE Deck_ID = ?";
        $stmt = $conn->prepare($cards_sql);
        $stmt->bind_param("i", $deck_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $deck_cards[$row['Card_ID']] = $row['Quantity_In_Deck'];
        }
    } else {
        // Deck not found or doesn't belong to user
        header("Location: mydecks.php");
        exit();
    }
    $stmt->close();
}

// Get user's collection with card details
$collection_sql = "SELECT c.Card_ID, c.Card_Name, c.Card_Type, c.Card_Image,
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
                   WHERE col.User_ID = ?
                   ORDER BY c.Card_Type, c.Card_Name";

$stmt = $conn->prepare($collection_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$collection = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate deck statistics
$total_cards = 0;
$pokemon_count = 0;
$trainer_count = 0;
$energy_count = 0;

if (!empty($deck_cards)) {
    foreach ($deck_cards as $card_id => $quantity) {
        $total_cards += $quantity;
        
        // Find card type from collection
        foreach ($collection as $card) {
            if ($card['Card_ID'] == $card_id) {
                if (strtolower($card['Card_Type']) == 'pokemon') {
                    $pokemon_count += $quantity;
                } elseif (strtolower($card['Card_Type']) == 'trainer') {
                    $trainer_count += $quantity;
                } elseif (strtolower($card['Card_Type']) == 'energy') {
                    $energy_count += $quantity;
                }
                break;
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pokemon Deck Builder - Deck Editor</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="decks.css">
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
                <a href="mydecks.php" class="nav-link active">My Decks</a>
                <a href="browse_cards.php" class="nav-link">Browse Cards</a>
                <a href="collection.php" class="nav-link">Collection</a>
                <a href="profile.html" class="nav-link">Profile</a>
                <a href="logout.php" class="nav-link logout">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Deck Editor Container -->
    <form method="POST" action="deck-editor.php" class="deck-editor-container">
        <?php if ($deck_id > 0): ?>
        <input type="hidden" name="deck_id" value="<?php echo $deck_id; ?>">
        <?php endif; ?>
        <!-- Deck Header -->
        <div class="editor-header">
            <div class="deck-info">
                <input type="text" name="deck_name" class="deck-name-input" 
                       value="<?php echo htmlspecialchars($deck_data['Deck_Name']); ?>" 
                       placeholder="Deck Name" required>
                <select name="deck_type" class="deck-type-select">
                    <option value="Standard" <?php echo $deck_data['Deck_Type'] == 'Standard' ? 'selected' : ''; ?>>Standard</option>
                    <option value="Expanded" <?php echo $deck_data['Deck_Type'] == 'Expanded' ? 'selected' : ''; ?>>Expanded</option>
                    <option value="Theme" <?php echo $deck_data['Deck_Type'] == 'Theme' ? 'selected' : ''; ?>>Theme</option>
                </select>
            </div>
            <div class="deck-actions">
                <button type="submit" class="save-deck-btn">Save Deck</button>
                <a href="mydecks.php" class="cancel-btn">Cancel</a>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
        <div style="background: #fecaca; color: #dc2626; padding: 10px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Deck Stats Bar -->
        <div class="deck-stats-bar">
            <div class="stat-item">
                <span class="stat-label">Total Cards</span>
                <span class="stat-value" id="total-cards"><?php echo $total_cards; ?>/60</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Pokemon</span>
                <span class="stat-value" id="pokemon-count"><?php echo $pokemon_count; ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Trainers</span>
                <span class="stat-value" id="trainer-count"><?php echo $trainer_count; ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Energy</span>
                <span class="stat-value" id="energy-count"><?php echo $energy_count; ?></span>
            </div>
        </div>

        <div class="editor-content">
            <!-- Collection Panel -->
            <div class="collection-panel">
                <h2>Your Collection</h2>
                <div class="collection-filters">
                    <input type="text" placeholder="Search cards..." class="search-input" id="search-cards">
                    <select class="filter-select" id="filter-type">
                        <option value="">All Types</option>
                        <option value="pokemon">Pokemon</option>
                        <option value="trainer">Trainer</option>
                        <option value="energy">Energy</option>
                    </select>
                </div>
                <div class="collection-cards" id="collection-cards">
                    <?php if (empty($collection)): ?>
                        <p style="text-align: center; color: #6b7280; padding: 20px;">
                            Your collection is empty. Add cards from the Browse Cards page first!
                        </p>
                    <?php else: ?>
                        <?php foreach ($collection as $card): ?>
                            <?php 
                            $in_deck = isset($deck_cards[$card['Card_ID']]) ? $deck_cards[$card['Card_ID']] : 0;
                            $available = $card['Quantity_In_Collection'] - $in_deck;
                            ?>
                            <div class="collection-card-item" data-card-id="<?php echo $card['Card_ID']; ?>" 
                                 data-card-type="<?php echo strtolower($card['Card_Type']); ?>"
                                 data-card-name="<?php echo strtolower($card['Card_Name']); ?>">
                                <?php 
                                $imagePath = 'placeholder-card-small.jpg';
                                if (!empty($card['Card_Image'])) {
                                    if (strpos($card['Card_Image'], 'uploads/') === 0) {
                                        $imagePath = $card['Card_Image'];
                                    } else {
                                        $imagePath = 'uploads/' . $card['Card_Image'];
                                    }
                                    if (!file_exists($imagePath)) {
                                        $imagePath = 'placeholder-card-small.jpg';
                                    }
                                }
                                ?>
                                <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                     alt="<?php echo htmlspecialchars($card['Card_Name']); ?>">
                                <div class="card-details">
                                    <h4><?php echo htmlspecialchars($card['Card_Name']); ?></h4>
                                    <?php if (strtolower($card['Card_Type']) == 'pokemon'): ?>
                                        <p><?php echo htmlspecialchars($card['Pokemon_Type'] ?: 'Unknown'); ?> - 
                                           <?php echo htmlspecialchars($card['Stage'] ?: 'Basic'); ?></p>
                                    <?php elseif (strtolower($card['Card_Type']) == 'trainer'): ?>
                                        <p>Trainer - <?php echo htmlspecialchars($card['Trainer_Card_Type'] ?: 'Unknown'); ?></p>
                                    <?php elseif (strtolower($card['Card_Type']) == 'energy'): ?>
                                        <p>Energy - <?php echo htmlspecialchars($card['Energy_Card_Type'] ?: 'Unknown'); ?></p>
                                    <?php else: ?>
                                        <p><?php echo htmlspecialchars($card['Card_Type']); ?></p>
                                    <?php endif; ?>
                                    
                                    <span class="available-qty" data-available="<?php echo $available; ?>">
                                        Available: <?php echo $available; ?>
                                    </span>
                                </div>
                                <button type="button" class="add-card-btn" onclick="addCard(<?php echo $card['Card_ID']; ?>)"
                                        <?php echo $available <= 0 ? 'disabled' : ''; ?>>+</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Deck Panel -->
            <div class="deck-panel">
                <h2>Current Deck</h2>
                <textarea name="deck_description" class="deck-description" 
                          placeholder="Deck description..."><?php echo htmlspecialchars($deck_data['Deck_Description']); ?></textarea>
                
                <div id="deck-cards-container">
                    <?php 
                    $pokemon_cards = array();
                    $trainer_cards = array();
                    $energy_cards = array();
                    
                    foreach ($collection as $card) {
                        if (isset($deck_cards[$card['Card_ID']]) && $deck_cards[$card['Card_ID']] > 0) {
                            $card['quantity'] = $deck_cards[$card['Card_ID']];
                            if (strtolower($card['Card_Type']) == 'pokemon') {
                                $pokemon_cards[] = $card;
                            } elseif (strtolower($card['Card_Type']) == 'trainer') {
                                $trainer_cards[] = $card;
                            } else {
                                $energy_cards[] = $card;
                            }
                        }
                    }
                    ?>
                    
                    <!-- Pokemon Section -->
                    <div class="deck-section">
                        <h3>Pokemon (<span id="pokemon-section-count"><?php echo count($pokemon_cards); ?></span>)</h3>
                        <div class="deck-cards" id="pokemon-cards">
                            <?php foreach ($pokemon_cards as $card): ?>
                                <?php include 'deck-card-template.php'; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Trainers Section -->
                    <div class="deck-section">
                        <h3>Trainers (<span id="trainer-section-count"><?php echo count($trainer_cards); ?></span>)</h3>
                        <div class="deck-cards" id="trainer-cards">
                            <?php foreach ($trainer_cards as $card): ?>
                                <?php include 'deck-card-template.php'; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Energy Section -->
                    <div class="deck-section">
                        <h3>Energy (<span id="energy-section-count"><?php echo count($energy_cards); ?></span>)</h3>
                        <div class="deck-cards" id="energy-cards">
                            <?php foreach ($energy_cards as $card): ?>
                                <?php include 'deck-card-template.php'; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script>
        // Collection data for JavaScript
        const collectionData = <?php echo json_encode($collection); ?>;
        
        // Update deck stats
        function updateDeckStats() {
            let total = 0;
            let pokemon = 0;
            let trainer = 0;
            let energy = 0;
            
            document.querySelectorAll('input[name^="card_quantity"]').forEach(input => {
                const quantity = parseInt(input.value) || 0;
                const cardType = input.dataset.cardType;
                
                total += quantity;
                if (cardType === 'pokemon') pokemon += quantity;
                else if (cardType === 'trainer') trainer += quantity;
                else if (cardType === 'energy') energy += quantity;
            });
            
            document.getElementById('total-cards').textContent = total + '/60';
            document.getElementById('pokemon-count').textContent = pokemon;
            document.getElementById('trainer-count').textContent = trainer;
            document.getElementById('energy-count').textContent = energy;
            
            // Update section counts
            document.getElementById('pokemon-section-count').textContent = 
                document.querySelectorAll('#pokemon-cards .deck-card-item').length;
            document.getElementById('trainer-section-count').textContent = 
                document.querySelectorAll('#trainer-cards .deck-card-item').length;
            document.getElementById('energy-section-count').textContent = 
                document.querySelectorAll('#energy-cards .deck-card-item').length;
            
            // Update available quantities in collection
            updateAvailableQuantities();
        }
        
        // Update available quantities
        function updateAvailableQuantities() {
            document.querySelectorAll('.collection-card-item').forEach(item => {
                const cardId = item.dataset.cardId;
                const card = collectionData.find(c => c.Card_ID == cardId);
                if (card) {
                    const input = document.querySelector(`input[name="card_quantity[${cardId}]"]`);
                    const inDeck = input ? parseInt(input.value) || 0 : 0;
                    const available = card.Quantity_In_Collection - inDeck;
                    
                    const availableSpan = item.querySelector('.available-qty');
                    availableSpan.textContent = 'Available: ' + available;
                    availableSpan.dataset.available = available;
                    
                    const addBtn = item.querySelector('.add-card-btn');
                    addBtn.disabled = available <= 0;
                }
            });
        }
        
        // Add card to deck
        function addCard(cardId) {
            const card = collectionData.find(c => c.Card_ID == cardId);
            if (!card) return;
            
            let existingCard = document.querySelector(`#deck-card-${cardId}`);
            
            if (existingCard) {
                // Card already in deck, increase quantity
                const input = existingCard.querySelector('input[name^="card_quantity"]');
                const currentQty = parseInt(input.value) || 0;
                const available = parseInt(document.querySelector(`.collection-card-item[data-card-id="${cardId}"] .available-qty`).dataset.available);
                
                if (available > 0) {
                    input.value = currentQty + 1;
                    updateQuantity(cardId, currentQty + 1);
                }
            } else {
                // Add new card to deck
                const cardType = card.Card_Type.toLowerCase();
                const section = document.getElementById(cardType + '-cards');
                
                const cardHtml = createDeckCardHtml(card);
                section.insertAdjacentHTML('beforeend', cardHtml);
                
                updateDeckStats();
            }
        }
        
        // Create deck card HTML
        function createDeckCardHtml(card) {
            const imagePath = card.Card_Image ? 
                (card.Card_Image.startsWith('uploads/') ? card.Card_Image : 'uploads/' + card.Card_Image) : 
                'placeholder-card-small.jpg';
            
            let typeInfo = '';
            if (card.Card_Type.toLowerCase() === 'pokemon') {
                typeInfo = `${card.Pokemon_Type || 'Unknown'} - ${card.Stage || 'Basic'}`;
            } else if (card.Card_Type.toLowerCase() === 'trainer') {
                typeInfo = `Trainer - ${card.Trainer_Card_Type || 'Unknown'}`;
            } else {
                typeInfo = card.Card_Type;
            }
            
            return `
                <div class="deck-card-item" id="deck-card-${card.Card_ID}">
                    <img src="${imagePath}" alt="${card.Card_Name}">
                    <div class="card-details">
                        <h4>${card.Card_Name}</h4>
                        <p>${typeInfo}</p>
                    </div>
                    <div class="quantity-control">
                        <button type="button" class="qty-btn" onclick="updateQuantity(${card.Card_ID}, -1)">-</button>
                        <input type="number" name="card_quantity[${card.Card_ID}]" 
                               value="1" min="0" max="${card.Quantity_In_Collection}" 
                               data-card-type="${card.Card_Type.toLowerCase()}"
                               onchange="updateQuantity(${card.Card_ID}, this.value)" readonly>
                        <button type="button" class="qty-btn" onclick="updateQuantity(${card.Card_ID}, 1)">+</button>
                    </div>
                </div>
            `;
        }
        
        // Update card quantity
        function updateQuantity(cardId, change) {
            const input = document.querySelector(`input[name="card_quantity[${cardId}]"]`);
            if (!input) return;
            
            const card = collectionData.find(c => c.Card_ID == cardId);
            let newValue;
            
            if (typeof change === 'number' && change !== 1 && change !== -1) {
                // Direct value set
                newValue = parseInt(change) || 0;
            } else {
                // Increment/decrement
                const currentValue = parseInt(input.value) || 0;
                newValue = currentValue + parseInt(change);
            }
            
            // Check limits
            newValue = Math.max(0, Math.min(newValue, card.Quantity_In_Collection));
            
            // For non-energy cards, max 4 per deck
            if (card.Card_Type.toLowerCase() !== 'energy') {
                newValue = Math.min(newValue, 4);
            }
            
            input.value = newValue;
            
            // Remove card from deck if quantity is 0
            if (newValue === 0) {
                const cardElement = document.getElementById(`deck-card-${cardId}`);
                if (cardElement) {
                    cardElement.remove();
                }
            }
            
            updateDeckStats();
        }
        
        // Filter collection cards
        function filterCollection() {
            const searchTerm = document.getElementById('search-cards').value.toLowerCase();
            const filterType = document.getElementById('filter-type').value;
            
            document.querySelectorAll('.collection-card-item').forEach(item => {
                const cardName = item.dataset.cardName;
                const cardType = item.dataset.cardType;
                
                const matchesSearch = searchTerm === '' || cardName.includes(searchTerm);
                const matchesType = filterType === '' || cardType === filterType;
                
                item.style.display = matchesSearch && matchesType ? 'flex' : 'none';
            });
        }
        
        // Event listeners
        document.getElementById('search-cards').addEventListener('input', filterCollection);
        document.getElementById('filter-type').addEventListener('change', filterCollection);
        
        // Initialize
        updateDeckStats();
    </script>
</body>
</html>