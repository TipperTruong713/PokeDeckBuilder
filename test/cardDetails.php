<?php
session_start();
require_once 'db.php';

if (!isset($_GET['card_id']) || !ctype_digit($_GET['card_id'])) {
    die('Card ID missing.');
}
$card_id = (int)$_GET['card_id'];

/* fetch base card row + Pokémon or Trainer extras */
$card = $pdo->prepare("
  SELECT c.*,
         pc.type        AS poke_type, pc.stage, pc.hp, pc.weakness,
         pc.ability,    pc.retreat_cost,
         tc.trainer_card_type
  FROM card c
  LEFT JOIN pokemon_card pc ON pc.card_id = c.card_id
  LEFT JOIN trainer_card tc ON tc.card_id = c.card_id
  WHERE c.card_id = ?");
$card->execute([$card_id]);
$card = $card->fetch(PDO::FETCH_ASSOC);
if (!$card) die('Card not found.');

/* Pokémon attacks (if any) */
$attacks = [];
if ($card['card_type'] === 'Pokemon') {
    $atk = $pdo->prepare("
      SELECT attack_name, attack_damage, attack_description
      FROM pokemon_card_attack WHERE card_id = ?");
    $atk->execute([$card_id]);
    $attacks = $atk->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($card['card_name']) ?> – Card Details</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="card-details-container">
  <div class="card-details-header">
    <a href="javascript:history.back()" class="back-btn">Back</a>
    <h1><?= htmlspecialchars($card['card_name']) ?></h1>
  </div>

  <div class="card-details-content">
 
    <div class="card-image-section">
      <div class="card-image-large">
        <img src="<?= htmlspecialchars($card['card_image'] ?: 'placeholder.jpg') ?>" alt="Card">
        <span class="card-rarity-large <?= strtolower(str_replace(' ','-',$card['rarity'])) ?>">
          <?= $card['rarity'] ?>
        </span>
      </div>
    </div>

    <div class="card-info-section">
      <div class="info-card">
        <h2>Basic Information</h2>
        <div class="info-grid">
          <div class="info-item"><span class="info-label">Name:</span>
               <span class="info-value"><?= htmlspecialchars($card['card_name']) ?></span></div>
          <div class="info-item"><span class="info-label">Type:</span>
               <span class="info-value"><?= $card['card_type'] ?></span></div>
          <div class="info-item"><span class="info-label">Rarity:</span>
               <span class="info-value"><?= $card['rarity'] ?></span></div>
          <div class="info-item"><span class="info-label">Artist:</span>
               <span class="info-value"><?= htmlspecialchars($card['artist'] ?: '—') ?></span></div>
        </div>
        <?php if ($card['card_description']): ?>
        <div class="info-description">
          <span class="info-label">Description:</span>
          <p><?= nl2br(htmlspecialchars($card['card_description'])) ?></p>
        </div>
        <?php endif;?>
      </div>

      
      <?php if ($card['card_type']=='Pokemon'): ?>
      <div class="info-card">
        <h2>Pokémon Details</h2>
        <div class="pokemon-stats">
          <div class="stat-row">
            <div class="stat-item"><span class="stat-label">Stage:</span>
              <span class="stat-value"><?= $card['stage'] ?: '—' ?></span></div>
            <div class="stat-item"><span class="stat-label">Type:</span>
              <span class="stat-value"><?= $card['poke_type'] ?: '—' ?></span></div>
            <div class="stat-item"><span class="stat-label">HP:</span>
              <span class="stat-value hp-value"><?= $card['hp'] ?: '—' ?></span></div>
          </div>
          <div class="stat-row">
            <div class="stat-item"><span class="stat-label">Weakness:</span>
              <span class="stat-value"><?= $card['weakness'] ?: '—' ?></span></div>
            <div class="stat-item"><span class="stat-label">Retreat:</span>
              <span class="stat-value"><?= $card['retreat_cost'] ?? '—' ?></span></div>
            <div class="stat-item"><span class="stat-label">Ability:</span>
              <span class="stat-value"><?= htmlspecialchars($card['ability'] ?: '—') ?></span></div>
          </div>
        </div>
      </div>

      <?php if ($attacks): ?>
      <div class="info-card">
        <h2>Attacks</h2>
        <div class="attacks-list">
          <?php foreach ($attacks as $a): ?>
            <div class="attack-item">
              <div class="attack-header">
                <span class="attack-name"><?= htmlspecialchars($a['attack_name']) ?></span>
                <span class="attack-damage"><?= $a['attack_damage'] ?></span>
              </div>
              <p class="attack-description"><?= htmlspecialchars($a['attack_description']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($card['card_type']=='Trainer'): ?>
      <div class="info-card">
        <h2>Trainer Details</h2>
        <div class="info-item">
          <span class="info-label">Trainer Type:</span>
          <span class="info-value"><?= $card['trainer_card_type'] ?></span>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
