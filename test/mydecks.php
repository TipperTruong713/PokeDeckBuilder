<?php

session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: LoginPage.html');
    exit;
}

$user_id = $_SESSION['user_id'];

if (isset($_GET['delete'])) {
    $deck_id = (int)$_GET['delete'];

    /* verify ownership */
    $own = $pdo->prepare("SELECT 1 FROM deck WHERE deck_id=? AND user_id=?");
    $own->execute([$deck_id, $user_id]);

    if ($own->fetchColumn()) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM deck_cards WHERE deck_id=?")
                ->execute([$deck_id]);
            $pdo->prepare("DELETE FROM deck WHERE deck_id=?")
                ->execute([$deck_id]);
            $pdo->commit();
            $msg = 'Deck deleted.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = 'Delete failed.';
        }
    } else {
        $msg = 'Deck not found';
    }

    header('Location: mydecks.php?msg=' . urlencode($msg));
    exit;
}


$q        = trim($_GET['q']   ?? '');
$sortKey  = $_GET['sort']     ?? 'name';               // name | cards
$sortSQL  = 'd.deck_name';                             // default

if ($sortKey === 'cards')  $sortSQL = 'total DESC';
if ($sortKey === 'name')   $sortSQL = 'd.deck_name';

$sql = "
SELECT d.deck_id, d.deck_name, d.deck_type, d.deck_description,
       COALESCE(SUM(dc.quantity_in_deck),0)                              AS total,
       SUM(CASE WHEN c.card_type='Pokemon' THEN dc.quantity_in_deck END) AS pokemon,
       SUM(CASE WHEN c.card_type='Trainer' THEN dc.quantity_in_deck END) AS trainers,
       SUM(CASE WHEN c.card_type='Energy'  THEN dc.quantity_in_deck END) AS energy
FROM deck d
LEFT JOIN deck_cards dc ON dc.deck_id = d.deck_id
LEFT JOIN card       c  ON c.card_id  = dc.card_id
WHERE d.user_id = :uid " .
($q !== '' ? "AND d.deck_name LIKE :q " : '') . "
GROUP BY d.deck_id
ORDER BY $sortSQL";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
if ($q !== '') $stmt->bindValue(':q', "%{$q}%");
$stmt->execute();
$decks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>PokéDeck Builder – My Decks</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="decks.css">
</head>
<body>

<nav class="navbar">
    <div class="nav-container">
        <div class="nav-logo"><span>PokéDeck Builder</span></div>
        <div class="nav-links">
            <a href="dashboard.html"  class="nav-link">Dashboard</a>
            <a href="mydecks.php"     class="nav-link active">My Decks</a>
            <a href="browseCard.html" class="nav-link">Browse Cards</a>
            <a href="collection.html" class="nav-link">Collection</a>
            <a href="profile.html"    class="nav-link">Profile</a>
            <a href="logout.php"      class="nav-link logout">Logout</a>
        </div>
    </div>
</nav>

<div class="decks-container">

    <div class="decks-header">
        <h1>My Decks</h1>
        <a href="deck-editor.php" class="new-deck-btn">+ Create New Deck</a>
    </div>

    <?php if ($msg): ?>
        <p style="text-align:center;color:#10b981;margin-bottom:25px;">
            <?= htmlspecialchars($msg) ?>
        </p>
    <?php endif; ?>

    <!-- search / sort -->
    <form class="filters-section" method="GET">
        <div class="filter-group">
            <label for="sort">Sort By</label>
            <select name="sort" id="sort">
                <option value="name"   <?= $sortKey=='name'   ?'selected':'' ?>>Name</option>
                <option value="cards"  <?= $sortKey=='cards'  ?'selected':'' ?>>Card Count</option>
            </select>
        </div>
        <div class="search-group">
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
                   placeholder="Search deck name…">
            <button class="search-btn">Go</button>
        </div>
    </form>

    <?php if (!$decks): ?>
        <p style="color:#fff;text-align:center;margin-top:40px;">No decks match.</p>
    <?php endif; ?>

    <div class="decks-grid">
        <?php foreach ($decks as $d): ?>
        <div class="deck-card">
            <div class="deck-header">
                <h2><?= htmlspecialchars($d['deck_name']) ?></h2>
                <span class="deck-format"><?= htmlspecialchars($d['deck_type']) ?></span>
            </div>

            <div class="deck-stats">
                <div class="deck-stat"><span class="stat-label">Cards</span>
                     <span class="stat-value"><?= $d['total'] ?>/60</span></div>
                <div class="deck-stat"><span class="stat-label">Pokémon</span>
                     <span class="stat-value"><?= $d['pokemon'] ?: 0 ?></span></div>
                <div class="deck-stat"><span class="stat-label">Trainers</span>
                     <span class="stat-value"><?= $d['trainers'] ?: 0 ?></span></div>
                <div class="deck-stat"><span class="stat-label">Energy</span>
                     <span class="stat-value"><?= $d['energy'] ?: 0 ?></span></div>
            </div>

            <p class="deck-description">
                <?= $d['deck_description'] ? htmlspecialchars($d['deck_description']) : '&nbsp;' ?>
            </p>

            <div class="deck-actions">
                <a href="deck-editor.php?deck_id=<?= $d['deck_id'] ?>" class="edit-deck-btn">
                    Edit Deck
                </a>
                <a href="mydecks.php?delete=<?= $d['deck_id'] ?>"
                   class="delete-deck-btn"
                   onclick="return confirm('Delete this deck?');">
                    Delete
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
