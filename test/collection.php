<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: LoginPage.html');
    exit;
}

$user_id = $_SESSION['user_id'];

/* Handle POST actions (qty + / −  or remove) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $card_id = (int)($_POST['card_id'] ?? 0);
    $action  = $_POST['action'] ?? '';

    // fetch collection_id for this user
    $cidStmt = $pdo->prepare("SELECT collection_id FROM collection WHERE user_id=?");
    $cidStmt->execute([$user_id]);
    $collection_id = $cidStmt->fetchColumn();

    if ($card_id && $collection_id) {
        switch ($action) {
            case 'plus':
                $pdo->prepare("UPDATE collections_cards
                               SET quantity_in_collection = quantity_in_collection + 1
                               WHERE collection_id=? AND card_id=?")
                    ->execute([$collection_id, $card_id]);
                break;
            case 'minus':
                $pdo->prepare("UPDATE collections_cards
                               SET quantity_in_collection = GREATEST(quantity_in_collection - 1, 0)
                               WHERE collection_id=? AND card_id=?")
                    ->execute([$collection_id, $card_id]);
                // remove row if qty becomes 0
                $pdo->prepare("DELETE FROM collections_cards
                               WHERE collection_id=? AND card_id=? AND quantity_in_collection=0")
                    ->execute([$collection_id, $card_id]);
                break;
            case 'remove':
                $pdo->prepare("DELETE FROM collections_cards
                               WHERE collection_id=? AND card_id=?")
                    ->execute([$collection_id, $card_id]);
                break;
        }
    }
    header("Location: collection.php?" . ($_SERVER['QUERY_STRING'] ?? ''));
    exit;
}

/* Filters & sorting */
$f = [
    'q'         => trim($_GET['q']         ?? ''),
    'card_type' => $_GET['card_type']      ?? '',
    'rarity'    => $_GET['rarity']         ?? '',
    'sort'      => $_GET['sort']           ?? 'name',
];
$page      = max(1, (int)($_GET['page'] ?? 1));
$page_size = 12;
$offset    = ($page-1)*$page_size;

$where = ['col.user_id = ?'];
$params= [$user_id];
$join_extra='';

if ($f['q'] !== '')          { $where[]='c.card_name LIKE ?';   $params[]='%'.$f['q'].'%'; }
if ($f['card_type'] !== '')  { $where[]='c.card_type = ?';      $params[]=ucfirst($f['card_type']); }
if ($f['rarity']   !== '')   { $where[]='c.rarity = ?';         $params[]=str_replace('-',' ',ucfirst($f['rarity'])); }

$whereSQL = 'WHERE ' . implode(' AND ', $where);

/* order by */
switch ($f['sort']) {
    case 'quantity': $order='quantity_in_collection DESC'; break;
    case 'rarity':   $order='c.rarity ASC';                break;
    case 'type':     $order='c.card_type ASC';             break;
    default:         $order='c.card_name ASC';
}

/* Fetch rows */
$sql = "
  SELECT cc.card_id, cc.quantity_in_collection,
         c.card_name, c.card_type, c.rarity, c.card_image,
         pc.hp, pc.type AS poke_type
  FROM collections_cards cc
  JOIN collection col ON col.collection_id = cc.collection_id
  JOIN card c        ON c.card_id        = cc.card_id
  LEFT JOIN pokemon_card pc ON pc.card_id= c.card_id
  $whereSQL
  ORDER BY $order
  LIMIT $page_size OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* stats */
$total_qtyStmt = $pdo->prepare("
  SELECT SUM(cc.quantity_in_collection), COUNT(*) 
  FROM collections_cards cc
  JOIN collection col ON col.collection_id=cc.collection_id
  WHERE col.user_id=?");
$total_qtyStmt->execute([$user_id]);
[$totalCards, $uniqueCards] = $total_qtyStmt->fetch(PDO::FETCH_NUM);

/* pagination */
$countStmt = $pdo->prepare("
  SELECT COUNT(*) FROM collections_cards cc
  JOIN collection col ON col.collection_id=cc.collection_id
  JOIN card c ON c.card_id=cc.card_id
  $whereSQL");
$countStmt->execute($params);
$total_pages = ceil($countStmt->fetchColumn() / $page_size);

function qs(array $extra=[]) { return http_build_query(array_merge($_GET,$extra)); }
?>
<!DOCTYPE html><html><head>
<meta charset="utf-8"><link rel="stylesheet" href="styles.css"></head><body>

<div class="collection-stats" style="margin-bottom:30px;">
    <div class="stat-card"><div class="stat-content"><h3>Total Cards</h3><p class="stat-number"><?= $totalCards ?: 0 ?></p></div></div>
    <div class="stat-card"><div class="stat-content"><h3>Unique Cards</h3><p class="stat-number"><?= $uniqueCards ?: 0 ?></p></div></div>
</div>


<div class="cards-grid">
<?php if (!$rows): ?>
    <p>You have no cards matching these filters.</p>
<?php else: foreach ($rows as $r): ?>
    <div class="card-item collection-card">
        <div class="card-image">
            <img src="<?= htmlspecialchars($r['card_image'] ?: 'placeholder.jpg') ?>" alt="<?= $r['card_name'] ?>">
            <span class="card-rarity <?= strtolower(str_replace(' ','-',$r['rarity'])) ?>"><?= $r['rarity'] ?></span>
            <span class="card-quantity">x<?= $r['quantity_in_collection'] ?></span>
        </div>

        <div class="card-info">
            <h3><?= htmlspecialchars($r['card_name']) ?></h3>
            <?php if ($r['card_type']=='Pokemon'): ?>
                <p class="card-type"><?= $r['poke_type'] ?> Pokémon</p>
                <p class="card-hp">HP <?= $r['hp'] ?: '?' ?></p>
            <?php else: ?>
                <p class="card-type"><?= $r['card_type'] ?></p>
            <?php endif; ?>

            <form method="POST" class="quantity-controls" style="margin-top:10px;">
                <input type="hidden" name="card_id" value="<?= $r['card_id'] ?>">
                <button class="qty-btn" name="action" value="minus">-</button>
                <span class="qty-display"><?= $r['quantity_in_collection'] ?></span>
                <button class="qty-btn" name="action" value="plus">+</button>
            </form>

            <div class="card-actions">
                <a class="view-btn" href="cardDetails.php?card_id=<?=$r['card_id']?>">View Details</a>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="card_id" value="<?= $r['card_id'] ?>">
                    <button class="remove-btn" name="action" value="remove">Remove</button>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; endif; ?>
</div>

<?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php if ($page>1): ?>
        <a class="page-btn" href="?<?= qs(['page'=>$page-1]) ?>">Previous</a>
    <?php endif; ?>
    <span class="page-info">Page <?= $page ?> of <?= $total_pages ?></span>
    <?php if ($page<$total_pages): ?>
        <a class="page-btn" href="?<?= qs(['page'=>$page+1]) ?>">Next</a>
    <?php endif; ?>
</div>
<?php endif; ?>
</body></html>
