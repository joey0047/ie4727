<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/config.php';

/* --------------- READ FILTERS FROM QUERY STRING --------------- */

// category: support ?category=1,2 AND ?category[]=1&category[]=2
$catIds = [];
if (isset($_GET['category'])) {
    if (is_array($_GET['category'])) {
        foreach ($_GET['category'] as $c) {
            $n = (int)trim($c);
            if ($n > 0) $catIds[] = $n;
        }
    } else {
        $catParam = trim($_GET['category']);
        if ($catParam !== '') {
            foreach (explode(',', $catParam) as $c) {
                $n = (int)trim($c);
                if ($n > 0) $catIds[] = $n;
            }
        }
    }
}
$catIds = array_values(array_unique($catIds));

// availability: in-stock / out-of-stock / (empty = no filter)
$availability = isset($_GET['availability']) ? strtolower(trim($_GET['availability'])) : '';
if (!in_array($availability, ['in-stock', 'out-of-stock'], true)) {
    $availability = '';
}

// size filter: ?size=M or ?size[]=M&size[]=L etc
$sizes = [];
if (isset($_GET['size'])) {
    if (is_array($_GET['size'])) {
        foreach ($_GET['size'] as $s) {
            $sizes[] = strtoupper(trim($s));
        }
    } else {
        foreach (explode(',', $_GET['size']) as $s) {
            $sizes[] = strtoupper(trim($s));
        }
    }
}
$sizes = array_values(array_filter(array_unique($sizes)));

/* --------------- SORT + PAGINATION --------------- */

$sort = $_GET['sort'] ?? 'date-old';
$SORT_SQL = [
    'a-z'        => 'p.product_name ASC',
    'z-a'        => 'p.product_name DESC',
    'price-low'  => '(p.base_price - p.discount_flat) ASC, p.product_id ASC',
    'price-high' => '(p.base_price - p.discount_flat) DESC, p.product_id ASC',
    'date-new'   => 'p.date_created DESC',
    'date-old'   => 'p.date_created ASC',
];
$orderBy = $SORT_SQL[$sort] ?? $SORT_SQL['date-old'];

$perPage = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

/* --------------- BUILD SHARED WHERE CLAUSE --------------- */

$conditions = [];
$params = [];
$types = '';

// Category condition
if (!empty($catIds)) {
    $in = implode(',', array_fill(0, count($catIds), '?'));
    $conditions[] = "p.category_id IN ($in)";
    $types .= str_repeat('i', count($catIds));
    $params = array_merge($params, $catIds);
}

// Availability condition (using variants stock)
if ($availability === 'in-stock') {
    $conditions[] = "EXISTS (
        SELECT 1 FROM variants v 
        WHERE v.product_id = p.product_id AND v.stock_qty > 0
    )";
} elseif ($availability === 'out-of-stock') {
    $conditions[] = "NOT EXISTS (
        SELECT 1 FROM variants v 
        WHERE v.product_id = p.product_id AND v.stock_qty > 0
    )";
}

// Size condition (product must have at least one variant with that size)
if (!empty($sizes)) {
    $in = implode(',', array_fill(0, count($sizes), '?'));
    $conditions[] = "EXISTS (
        SELECT 1 FROM variants v2
        WHERE v2.product_id = p.product_id
          AND v2.size IN ($in)
    )";
    $types .= str_repeat('s', count($sizes));
    $params = array_merge($params, $sizes);
}

$whereSql = '';
if (!empty($conditions)) {
    $whereSql = ' WHERE ' . implode(' AND ', $conditions);
}

/* --------------- COUNT QUERY (FOR PAGINATION) --------------- */

$countSql = "SELECT COUNT(*) AS cnt FROM products p" . $whereSql;
$stmt = $mysqli->prepare($countSql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));

/* --------------- MAIN PRODUCT QUERY --------------- */

$listSql = "
  SELECT 
    p.product_id, p.product_name, p.description, p.base_price, p.discount_flat,
    COALESCE(
      (SELECT image_url FROM product_images pi 
       WHERE pi.product_id = p.product_id AND pi.is_primary = 1 
       ORDER BY pi.sort_order ASC, pi.image_id ASC LIMIT 1),
      (SELECT image_url FROM product_images pi2 
       WHERE pi2.product_id = p.product_id 
       ORDER BY pi2.is_primary DESC, pi2.sort_order ASC, pi2.image_id ASC LIMIT 1)
    ) AS image_url
  FROM products p
  $whereSql
  ORDER BY $orderBy
  LIMIT ? OFFSET ?
";

$listParams = $params;
$listTypes  = $types . 'ii';
$listParams[] = $perPage;
$listParams[] = $offset;

$stmt = $mysqli->prepare($listSql);
$stmt->bind_param($listTypes, ...$listParams);
$stmt->execute();
$res = $stmt->get_result();
$products = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* --------------- HELPERS --------------- */

function price_fmt($n) { return '$' . number_format((float)$n, 2); }

function build_url(array $overrides = []) {
    $q = array_merge($_GET, $overrides);
    // if we set category[] or size[] in overrides, unset simple forms
    if (isset($overrides['category']) && is_array($overrides['category'])) {
        unset($q['category']);
        $q['category'] = $overrides['category'];
    }
    if (isset($overrides['size']) && is_array($overrides['size'])) {
        unset($q['size']);
        $q['size'] = $overrides['size'];
    }
    return 'productlist.php?' . http_build_query($q);
}

// Title helper based on category IDs (optional)
function title_from_filters($catIds) {
    sort($catIds);
    $map = [
        1 => 'Shirts',
        2 => 'Shorts',
        3 => 'Long Pants',
        4 => 'Socks',
        5 => 'Chalk Bags',
    ];
    if (empty($catIds)) return 'All Products';
    $labels = array_map(fn($id) => $map[$id] ?? "Category $id", $catIds);
    return implode(' + ', $labels);
}

$title = title_from_filters($catIds);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($title) ?></title>
  <link rel="stylesheet" href="stylesheet.css">
  <style>.product-item-price .orig{ text-decoration:line-through; opacity:.5; margin-right:6px; }
    .product-image-wrapper{height:fit-content;}
    .product-name-link {
    color: inherit;
    text-decoration: none;
    text-decoration-color: transparent;   /* hides underline initially */
    text-decoration-thickness: 1px;
    text-underline-offset: 2px;
    transition: text-decoration-color .25s ease;
    }

    .product-name-link:hover {
    text-decoration: underline;
    text-decoration-color: currentColor;  /* fade underline in */
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/header.php'; ?>

  <section class="product-list-section">
    <div class="product-list-header">
      <h1 class="product-list-title"><?= htmlspecialchars($title) ?></h1>
      <p class="product-list-subtitle"><?= (int)$total ?> item(s) found</p>
    </div>

    <!-- Controls: Filter + Sort -->
    <div class="product-list-controls">
      <!-- FILTER BUTTON -->
      <button class="filter-btn" id="filterBtn">
        <span>Filter</span>
      </button>

      <!-- SORT DROPDOWN -->
      <div class="sort-dropdown">
        <label for="sortSel">Sort</label>
        <select id="sortSel" onchange="location.href=this.value">
          <?php foreach ($SORT_SQL as $key => $sqlPart): ?>
            <?php
              $label = match($key) {
                'a-z'        => 'Alphabetically, A–Z',
                'z-a'        => 'Alphabetically, Z–A',
                'price-low'  => 'Price, low to high',
                'price-high' => 'Price, high to low',
                'date-new'   => 'Date, new to old',
                'date-old'   => 'Date, old to new',
                default      => ucfirst($key),
              };
            ?>
            <option value="<?= htmlspecialchars(build_url(['sort' => $key, 'page' => 1])) ?>" <?= $sort === $key ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- PRODUCT GRID -->
    <div class="product-grid" id="productGrid">
      <?php if (empty($products)): ?>
        <p>No products found.</p>
      <?php else: ?>
        <?php foreach ($products as $p): 
          $img = $p['image_url'] ?: BASE_URL . '/assets/images/tempimage.png';
          $orig = (float)$p['base_price'];
          $disc = (float)$p['discount_flat'];
          $final = max($orig - $disc, 0);
        ?>
          <div class="product-item">
            <a href="<?= BASE_URL ?>/product.php?id=<?= (int)$p['product_id'] ?>" class="product-item-link">
                <div class="product-image-wrapper">
                    <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($p['product_name']) ?>" class="product-item-image">
                </div>
            </a>
            <h3 class="product-item-name">
            <a href="<?= BASE_URL ?>/product.php?id=<?= (int)$p['product_id'] ?>" class="product-name-link">
                <span class="text-wrap"><?= htmlspecialchars($p['product_name']) ?></span>
            </a>
            </h3>
            <p class="product-item-price">
              <?php if ($disc > 0): ?>
                <span class="orig"><?= price_fmt($orig) ?></span>
                <span class="final"><?= price_fmt($final) ?></span>
              <?php else: ?>
                <span class="final"><?= price_fmt($orig) ?></span>
              <?php endif; ?>
            </p>
            <p class="product-item-sizes">See product page for sizes</p>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- PAGINATION -->
    <div class="pagination" style="display:flex;gap:8px;margin-top:20px;">
      <?php if ($page > 1): ?>
        <a class="pagination-btn" href="<?= htmlspecialchars(build_url(['page' => $page - 1])) ?>">Prev</a>
      <?php endif; ?>
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a class="pagination-btn <?= $i === $page ? 'is-active' : '' ?>" href="<?= htmlspecialchars(build_url(['page' => $i])) ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
        <a class="pagination-btn" href="<?= htmlspecialchars(build_url(['page' => $page + 1])) ?>">Next</a>
      <?php endif; ?>
    </div>
  </section>

  <!-- FILTER MODAL (SERVER-SIDE FORM) -->
  <div class="filter-modal" id="filterModal" style="display:none;">
    <div class="filter-modal-content">
      <div class="filter-header">
        <h2 class="filter-title">Filter</h2>
        <button class="filter-close" id="filterClose">×</button>
      </div>

      <form method="get" action="productlist.php">
        <!-- Preserve sort, reset to page 1 when filtering -->
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
        <input type="hidden" name="page" value="1">

        <!-- AVAILABILITY -->
        <div class="filter-section">
          <h3 class="filter-section-title">Availability</h3>
          <label class="filter-option">
            <input type="radio" name="availability" value="" <?= $availability === '' ? 'checked' : '' ?>>
            <span>All</span>
          </label>
          <label class="filter-option">
            <input type="radio" name="availability" value="in-stock" <?= $availability === 'in-stock' ? 'checked' : '' ?>>
            <span>In stock</span>
          </label>
          <label class="filter-option">
            <input type="radio" name="availability" value="out-of-stock" <?= $availability === 'out-of-stock' ? 'checked' : '' ?>>
            <span>Out of stock</span>
          </label>
        </div>

        <!-- CATEGORY (BASED ON CATEGORY TABLE) -->
        <div class="filter-section">
          <h3 class="filter-section-title">Category</h3>
          <?php
            $CATS = [
              1 => 'Shirts',
              2 => 'Shorts',
              3 => 'Long Pants',
              4 => 'Socks',
              5 => 'Chalk Bags',
            ];
            foreach ($CATS as $cid => $label):
          ?>
            <label class="filter-option">
              <input type="checkbox" name="category[]" value="<?= $cid ?>" <?= in_array($cid, $catIds, true) ? 'checked' : '' ?>>
              <span><?= htmlspecialchars($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <!-- SIZING -->
        <div class="filter-section">
          <h3 class="filter-section-title">Sizing</h3>
          <?php
            $SIZE_OPTIONS = ['XS','S','M','L','XL','2XL'];
            foreach ($SIZE_OPTIONS as $sz):
          ?>
            <label class="filter-option">
              <input type="checkbox" name="size[]" value="<?= $sz ?>" <?= in_array($sz, $sizes, true) ? 'checked' : '' ?>>
              <span><?= $sz ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="filter-actions">
          <a href="productlist.php" class="filter-reset-btn">Reset</a>
          <button type="submit" class="filter-apply-btn">Apply</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Simple JS to open/close filter modal
    document.addEventListener('DOMContentLoaded', function () {
      const modal = document.getElementById('filterModal');
      const openBtn = document.getElementById('filterBtn');
      const closeBtn = document.getElementById('filterClose');

      if (!modal || !openBtn || !closeBtn) return;

      function openModal() { modal.style.display = 'block'; }
      function closeModal() { modal.style.display = 'none'; }

      openBtn.addEventListener('click', openModal);
      closeBtn.addEventListener('click', closeModal);
      modal.addEventListener('click', function(e){
        if (e.target === modal) closeModal();
      });
      document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') closeModal();
      });
    });
  </script>
</body>
</html>
