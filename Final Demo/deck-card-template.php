<?php
// This is included by deck-editor.php to display cards in the deck
// It expects $card array to be defined with card data

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
<div class="deck-card-item" id="deck-card-<?php echo $card['Card_ID']; ?>">
    <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($card['Card_Name']); ?>">
    <div class="card-details">
        <h4><?php echo htmlspecialchars($card['Card_Name']); ?></h4>
        <?php if (strtolower($card['Card_Type']) == 'pokemon'): ?>
            <p><?php echo htmlspecialchars($card['Pokemon_Type'] ?: 'Unknown'); ?> - 
               <?php echo htmlspecialchars($card['Stage'] ?: 'Basic'); ?></p>
        <?php elseif (strtolower($card['Card_Type']) == 'trainer'): ?>
            <p>Trainer - <?php echo htmlspecialchars($card['Trainer_Card_Type'] ?: 'Unknown'); ?></p>
        <?php else: ?>
            <p><?php echo htmlspecialchars($card['Card_Type']); ?></p>
        <?php endif; ?>
    </div>
    <div class="quantity-control">
        <button type="button" class="qty-btn" onclick="updateQuantity(<?php echo $card['Card_ID']; ?>, -1)">-</button>
        <input type="number" name="card_quantity[<?php echo $card['Card_ID']; ?>]" 
               value="<?php echo $card['quantity']; ?>" 
               min="0" 
               max="<?php echo $card['Quantity_In_Collection']; ?>" 
               data-card-type="<?php echo strtolower($card['Card_Type']); ?>"
               onchange="updateQuantity(<?php echo $card['Card_ID']; ?>, this.value)" readonly>
        <button type="button" class="qty-btn" onclick="updateQuantity(<?php echo $card['Card_ID']; ?>, 1)">+</button>
    </div>
</div>