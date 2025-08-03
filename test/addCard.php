<?php

require_once 'db.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect core card data
    $cardName = $_POST['card_name'];
    $rarity = $_POST['rarity'];
    $artist = $_POST['artist'] ?? null;
    $cardType = $_POST['card_type'];
    $cardDesc = $_POST['card_description'] ?? null;
    $quantity = (int)$_POST['quantity'];

    // Handle image upload
    $cardImage = null;
    if (isset($_FILES['card_image']) && $_FILES['card_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filename = basename($_FILES['card_image']['name']);
        $targetPath = $uploadDir . time() . '_' . $filename;
        if (move_uploaded_file($_FILES['card_image']['tmp_name'], $targetPath)) {
            $cardImage = $targetPath;
        }
    }

    try {
        $pdo->beginTransaction();

        // Insert into card table
        $stmt = $pdo->prepare("INSERT INTO card (card_name, rarity, artist, card_type, card_image, card_description)
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$cardName, $rarity, $artist, $cardType, $cardImage, $cardDesc]);
        $cardId = $pdo->lastInsertId();

        // Insert subtype info
        if ($cardType === 'Pokemon') {
            $stmt = $pdo->prepare("INSERT INTO pokemon_card (card_id, stage, ability, type, weakness, hp, retreat_cost)
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $cardId,
                $_POST['stage'] ?? null,
                $_POST['ability'] ?? null,
                $_POST['pokemon_type'],
                $_POST['weakness'] ?? null,
                $_POST['hp'] ?: null,
                $_POST['retreat_cost'] ?? 0
            ]);

            // Handle attacks
            if (!empty($_POST['attack_name'])) {
                $attackNames = $_POST['attack_name'];
                $attackDamages = $_POST['attack_damage'];
                $attackDescs = $_POST['attack_description'];

                for ($i = 0; $i < count($attackNames); $i++) {
                    if (trim($attackNames[$i]) !== '') {
                        $stmt = $pdo->prepare("INSERT INTO pokemon_card_attack (card_id, attack_name, attack_damage, attack_description)
                                               VALUES (?, ?, ?, ?)");
                        $stmt->execute([
                            $cardId,
                            $attackNames[$i],
                            $attackDamages[$i] ?: 0,
                            $attackDescs[$i] ?? null
                        ]);
                    }
                }
            }
        } elseif ($cardType === 'Trainer') {
            $stmt = $pdo->prepare("INSERT INTO trainer_card (card_id, trainer_card_type)
                                   VALUES (?, ?)");
            $stmt->execute([
                $cardId,
                $_POST['trainer_card_type']
            ]);
        }

        // Add to user's collection (assumes session has user_id set)
        $userId = $_SESSION['user_id'] ?? 1; // Fallback to user_id = 1
        $stmt = $pdo->prepare("SELECT collection_id FROM collection WHERE user_id = ?");
        $stmt->execute([$userId]);
        $collection = $stmt->fetch();

        if ($collection) {
            $collectionId = $collection['collection_id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO collection (user_id) VALUES (?)");
            $stmt->execute([$userId]);
            $collectionId = $pdo->lastInsertId();
        }

        // Insert into collections_cards
        $stmt = $pdo->prepare("INSERT INTO collections_cards (collection_id, card_id, quantity_in_collection)
                               VALUES (?, ?, ?)");
        $stmt->execute([$collectionId, $cardId, $quantity]);

        $pdo->commit();
        header('Location: collection.html');
    } catch (Exception $e) {
        $pdo->rollBack();
        header('Location: collection.html?error=db');
	exit;
    }

}
?>
