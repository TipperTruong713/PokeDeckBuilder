<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exit('POST request method required');
}

//DATA
if ($_FILES["card_image"]["error"] !== UPLOAD_ERR_OK) {      //Error checking img upload
    switch ($_FILES["card_image"]["error"]) {
        case UPLOAD_ERR_PARTIAL:
            exit('File only partially uploaded');
        case UPLOAD_ERR_NO_FILE:
            echo "<script>alert('No image file was uploaded.'); window.location.href='addCard.html';</script>";
    }
} else {
    $uploadDirectory = "uploads/";
    $allowedTypes = ['image/jpg', 'image/jpeg', 'image/png'];
    if ($_FILES["card_image"]["size"] > 5 * 1024 * 1024
        || !in_array($_FILES["card_image"]["type"], $allowedTypes)) {
        exit("File is invalid.");
    }

    $fileName = $_FILES["card_image"]["name"];
    $destination = $uploadDirectory . $fileName;
    if (!move_uploaded_file($_FILES["card_image"]["tmp_name"], $destination)) {
        exit("Error uploading file.");
    }
}

$name = $_POST['card_name'];
$rarity = $_POST['rarity'];
$artist = $_POST['artist'];
$type = $_POST['card_type'];
$description = $_POST['card_description'];


// SQL upload
$conn = new mysqli("localhost", "root", "", "PokeDeckBuilder");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->begin_transaction(); //preventing half formed data from getting inserted if its not fully formed
try {
    $sql = "INSERT INTO card (Card_Name, Rarity, Artist, Card_Type, Card_Image, Card_Description) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    $stmt->bind_param("ssssss", $name, $rarity, $artist, $type, $fileName, $description);
    if ($stmt->execute()) {
        $cardID = $conn->insert_id;
        $stmt->close();

        switch ($type) {
            case "Pokemon":
                $sql = "insert into pokemon_card (Card_ID, Stage, Ability, Type, Weakness, HP, Retreat_Cost) values (?, ? ,? ,? ,? ,? ,?)";
                $stmt = $conn->prepare($sql);

                //DATA
                $stage = $_POST['stage'];
                $ability = $_POST['ability'];
                $pokemonType = $_POST['pokemon_type'];
                $weakness = $_POST['weakness'];
                $hp = $_POST['hp'];
                $retreatCost = $_POST['retreat_cost'];

                $stmt->bind_param("issssss", $cardID, $stage, $ability, $pokemonType, $weakness, $hp, $retreatCost);
                if (!$stmt->execute())
                    throw new Exception($stmt->error);

                $attackNameArray = $_POST["attack_name"];
                $attackDamageArray = $_POST["attack_damage"];
                $attackDescriptionArray = $_POST["attack_description"];

                $sql = "insert into pokemon_card_attack (Card_ID, Attack_Name, Attack_Damage, Attack_Description)   values (?,?, ?, ?)";
                $stmt = $conn->prepare($sql);

                for ($i = 0; $i < count($attackNameArray); $i++) {
                    if (!empty($attackNameArray[$i])) {
                        $stmt->bind_param("isis", $cardID, $attackNameArray[$i], $attackDamageArray[$i], $attackDescriptionArray[$i]);
                        if (!$stmt->execute())
                            throw new Exception($stmt->error);
                    }
                }
                $stmt->close();
                break;
            case "Trainer":
                $TrainerType = $_POST['trainer_card_type'];

                $sql = "insert into trainer_card (Card_ID, Trainer_Card_Type) values (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("is", $cardID, $TrainerType);
                if (!$stmt->execute())
                    throw new Exception($stmt->error);
                break;
            case "Energy":
                //$EnergyType = $_POST['energy_type'];
                // TODO: Finish implementing Energy card insertion logic
                throw new Exception("ENERGY NOT FINISHED");  //REPLACE THIS
                break;
        }
    } else {
        throw new Exception($stmt->error);
    }
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage();
}
// TODO: figure out what quality is for here
$conn->close();