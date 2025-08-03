<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: LoginPage.html');
    exit;
}

$user_id = $_SESSION['user_id'];

/*  Add to collection */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['card_id'])) {
    $card_id = (int)$_POST['card_id'];
    $qty     = max(1, (int)($_POST['qty'] ?? 1));

    $cid = $pdo->prepare("SELECT collection_id FROM collection WHERE user_id=?");
    $cid->execute([$user_id]);
    $collection_id = $cid->fetchColumn();
    if (!$collection_id) {
        $pdo->prepare("INSERT INTO collection (user_id) VALUES (?)")->execute([$user_id]);
        $collection_id = $pdo->lastInsertId();
    }

    $pdo->prepare("
        INSERT INTO collections_cards (collection_id, card_id, quantity_in_collection)
        VALUES (:c,:id,:q)
        ON DUPLICATE KEY UPDATE
        quantity_in_collection = quantity_in_collection + VALUES(quantity_in_collection)
    ")->execute([':c'=>$collection_id,':id'=>$card_id,':q'=>$qty]);

    header("Location: browseCard.php?".($_SERVER['QUERY_STRING'] ?? ''));
    exit;
}

/* Filters */
$f = [
    'q'            => trim($_GET['q']            ?? ''),
    'card_type'    => $_GET['card_type']         ?? '',
    'pokemon_type' => $_GET['pokemon_type']      ?? '',
    'rarity'       => $_GET['rarity']            ?? ''
];
$page      = max(1, (int)($_GET['page'] ?? 1));
$page_size = 12;
$offset    = ($page-1)*$page_size;

$where=[]; $params=[]; $join='';
if ($f['q'] !== '')              { $where[]='c.card_name LIKE ?'; $params[]='%'.$f['q'].'%'; }
if ($f['card_type'] !== '')      { $where[]='c.card_type = ?';    $params[]=ucfirst($f['card_type']); }
if ($f['rarity']    !== '')      { $where[]='c.rarity = ?';       $params[]=str_replace('-',' ',ucfirst($f['rarity'])); }
if ($f['pokemon_type'] !== '') {
    $join='INNER JOIN pokemon_card pc2 ON pc2.card_id=c.card_id';
    $where[]='pc2.type = ?';
    $params[]=ucfirst($f['pokemon_type']);
}
$whereSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';

/* fetch cards */
$sql = "
  SELECT c.card_id,c.card_name,c.card_type,c.rarity,c.card_image,
         pc.hp,pc.type AS poke_type
  FROM card c
  LEFT JOIN pokemon_card pc ON pc.card_id=c.card_id
  $join
  $whereSQL
  ORDER BY c.card_name ASC
  LIMIT $page_size OFFSET $offset";
$stmt=$pdo->prepare($sql); $stmt->execute($params);
$cards=$stmt->fetchAll();

/* pagination */
$cnt=$pdo->prepare("SELECT COUNT(*) FROM card c $join $whereSQL");
$cnt->execute($params);
$total_pages = ceil($cnt->fetchColumn() / $page_size);

function qs(array $extra=[]){ return http_build_query(array_merge($_GET,$extra)); }
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8"><link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="cards-grid">
<?php if(!$cards):?>
    <p>No cards found.</p>
<?php else: foreach($cards as $c):?>
    <div class="card-item">
        <div class="card-image">
            <img src="<?= htmlspecialchars($c['card_image'] ?: 'placeholder.jpg') ?>" alt="<?= $c['card_name'] ?>">
            <span class="card-rarity <?= strtolower(str_replace(' ','-',$c['rarity'])) ?>"><?= $c['rarity'] ?></span>
        </div>
        <div class="card-info">
            <h3><?= htmlspecialchars($c['card_name']) ?></h3>
            <?php if($c['card_type']=='Pokemon'):?>
                <p class="card-type"><?= $c['poke_type'] ?> Pokémon</p>
                <p class="card-hp">HP <?= $c['hp'] ?: '?' ?></p>
            <?php else:?>
                <p class="card-type"><?= $c['card_type'] ?></p>
            <?php endif;?>
            <div class="card-actions">
                <form method="POST">
                    <input type="hidden" name="card_id" value="<?= $c['card_id'] ?>">
                    <input type="hidden" name="qty" value="1">
                    <button class="add-btn">Add to Collection</button>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; endif;?>
</div>

<?php if($total_pages>1):?>
<div class="pagination">
    <?php if($page>1):?>
        <a class="page-btn" href="?<?= qs(['page'=>$page-1])?>">Previous</a>
    <?php endif;?>
    <span class="page-info">Page <?= $page ?> of <?= $total_pages ?></span>
    <?php if($page<$total_pages):?>
        <a class="page-btn" href="?<?= qs(['page'=>$page+1])?>">Next</a>
    <?php endif;?>
</div>
<?php endif;?>
</body>
</html>
