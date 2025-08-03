<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: LoginPage.html'); 
    exit;
}

$user_id = $_SESSION['user_id'];
$isPost  = ($_SERVER['REQUEST_METHOD'] === 'POST');
$deck_id = $isPost ? (int)($_POST['deck_id'] ?? 0)
                   : (int)($_GET ['deck_id'] ?? 0);

/* (POST) */
if ($isPost) {
    $name = trim($_POST['deck_name']       ?? '');
    $type = trim($_POST['deck_type']       ?? 'Standard');
    $desc = trim($_POST['deck_description']?? '');
    $qty  = $_POST['qty'] ?? [];                 // qty[card_id] = n

    if ($name === '' || !in_array($type,['Standard','Expanded','Theme'])) {
        die('Bad input.');
    }

    if ($deck_id) {                                  // update
        $own = $pdo->prepare("SELECT 1 FROM deck WHERE deck_id=? AND user_id=?");
        $own->execute([$deck_id,$user_id]);
        if (!$own->fetchColumn()) die('Deck not found.');

        $pdo->prepare("UPDATE deck SET deck_name=?, deck_type=?, deck_description=? WHERE deck_id=?")
            ->execute([$name,$type,$desc,$deck_id]);

        $pdo->prepare("DELETE FROM deck_cards WHERE deck_id=?")->execute([$deck_id]);
    } else {                                         // create
        $pdo->prepare("INSERT INTO deck (deck_name,deck_type,deck_description,user_id)
                       VALUES (?,?,?,?)")
            ->execute([$name,$type,$desc,$user_id]);
        $deck_id = $pdo->lastInsertId();
    }

    /* insert composition */
    $ins = $pdo->prepare("INSERT INTO deck_cards (deck_id,card_id,quantity_in_deck) VALUES (?,?,?)");
    foreach ($qty as $cid=>$n) {
        $cid=(int)$cid; $n=max(0,(int)$n);
        if ($cid && $n) $ins->execute([$deck_id,$cid,$n]);
    }
    header('Location: mydecks.html');
    exit;
}

/* (GET) */
/* get user’s collection + existing deck quantities in one query */
$col = $pdo->prepare("
    SELECT c.card_id, c.card_name, c.card_type, c.card_image,
           pc.hp,
           cc.quantity_in_collection AS avail,
           COALESCE(dc.quantity_in_deck,0)          AS in_deck
    FROM collection col
    JOIN collections_cards cc ON cc.collection_id = col.collection_id
    JOIN card c               ON c.card_id        = cc.card_id
    LEFT JOIN pokemon_card pc ON pc.card_id       = c.card_id
    LEFT JOIN deck_cards dc   ON dc.card_id       = c.card_id AND dc.deck_id = ?
    WHERE col.user_id = ?
    ORDER BY c.card_name ASC");
$col->execute([$deck_id,$user_id]);
$cards = $col->fetchAll();

/* fetch deck meta if editing */
$deck = ['deck_name'=>'','deck_type'=>'Standard','deck_description'=>''];
if ($deck_id) {
    $d = $pdo->prepare("SELECT deck_name,deck_type,deck_description
                        FROM deck WHERE deck_id=? AND user_id=?");
    $d->execute([$deck_id,$user_id]);
    $tmp = $d->fetch(PDO::FETCH_ASSOC);
    if ($tmp) $deck = $tmp;
}

/* deck stats */
$total=$poke=$train=$ener=0;
foreach ($cards as $c){
    $total += $c['in_deck'];
    if     ($c['card_type']=='Pokemon') $poke  += $c['in_deck'];
    elseif ($c['card_type']=='Trainer') $train += $c['in_deck'];
    elseif ($c['card_type']=='Energy')  $ener  += $c['in_deck'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>PokéDeck Builder – Deck Editor</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="decks.css">
</head>
<body>
<nav class="navbar">
   <div class="nav-container">
        <div class="nav-logo"><span>PokéDeck Builder</span></div>
        <div class="nav-links">
            <a href="dashboard.html"  class="nav-link">Dashboard</a>
            <a href="mydecks.html"    class="nav-link active">My Decks</a>
            <a href="browseCard.html" class="nav-link">Browse Cards</a>
            <a href="collection.html" class="nav-link">Collection</a>
            <a href="profile.html"    class="nav-link">Profile</a>
            <a href="logout.php"      class="nav-link logout">Logout</a>
        </div>
    </div>
</nav>

<form action="deck-editor.php" method="POST" class="deck-editor-container">
    <input type="hidden" name="deck_id" value="<?= $deck_id ?>">

    <div class="editor-header">
        <div class="deck-info">
            <input type="text" name="deck_name"  class="deck-name-input"
                   value="<?= htmlspecialchars($deck['deck_name']) ?>" placeholder="Deck Name" required>
            <select name="deck_type" class="deck-type-select">
                <?php foreach(['Standard','Expanded','Theme'] as $fmt): ?>
                    <option value="<?= $fmt ?>" <?= $fmt==$deck['deck_type']?'selected':'';?>><?= $fmt ?></option>
                <?php endforeach;?>
            </select>
        </div>
        <div class="deck-actions">
            <button class="save-deck-btn">Save Deck</button>
            <a href="mydecks.html" class="cancel-btn">Cancel</a>
        </div>
    </div>

    <div class="deck-stats-bar">
        <div class="stat-item"><span class="stat-label">Total Cards</span><span class="stat-value"><?= $total ?>/60</span></div>
        <div class="stat-item"><span class="stat-label">Pokemon</span>     <span class="stat-value"><?= $poke  ?></span></div>
        <div class="stat-item"><span class="stat-label">Trainers</span>    <span class="stat-value"><?= $train ?></span></div>
        <div class="stat-item"><span class="stat-label">Energy</span>      <span class="stat-value"><?= $ener  ?></span></div>
    </div>

    <textarea name="deck_description" class="deck-description"
              placeholder="Deck description…"><?= htmlspecialchars($deck['deck_description']) ?></textarea>

    <h2 style="margin-top:40px;">Add / remove cards</h2>
    <p style="margin-bottom:15px;">Set quantity (0–4 for Pokémon/Trainer).</p>

    <div class="cards-grid">
        <?php foreach ($cards as $c): ?>
            <div class="card-item collection-card" style="padding:10px;">
                <div class="card-image">
                    <img src="<?= htmlspecialchars($c['card_image'] ?: 'placeholder.jpg') ?>" alt="<?= $c['card_name'] ?>">
                    <span class="card-rarity <?= strtolower(str_replace(' ','-',$c['card_type'])) ?>">
                        <?= $c['card_type'] ?>
                    </span>
                </div>

                <div class="card-info" style="padding:0 10px;">
                    <h3 style="margin:5px 0;"><?= htmlspecialchars($c['card_name']) ?></h3>
                    <?php if ($c['card_type']=='Pokemon'): ?>
                        <p class="card-hp" style="margin:0;">HP <?= $c['hp'] ?: '?' ?></p>
                    <?php endif; ?>

                    <p style="font-size:12px;margin:4px 0;color:#666;">
                        Owned: <?= $c['avail'] ?>
                    </p>

                    <label style="font-size:13px;">In deck:
                        <input type="number"
                               name="qty[<?= $c['card_id'] ?>]"
                               value="<?= $c['in_deck'] ?>"
                               min="0"
                               max="<?= $c['avail'] ?>"
                               style="width:60px;">
                    </label>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</form>
</body>
</html>
