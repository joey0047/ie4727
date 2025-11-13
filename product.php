<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/config.php';

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($productId <= 0) {
    http_response_code(404);
    echo "Product not found.";
    exit;
}

/* ---------- Fetch product ---------- */
$sql = "
  SELECT p.*, c.category_name
  FROM products p
  LEFT JOIN categories c ON c.category_id = p.category_id
  WHERE p.product_id = ?
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $productId);
$stmt->execute();
$res = $stmt->get_result();
$product = $res->fetch_assoc();
$stmt->close();

if (!$product) {
    http_response_code(404);
    echo "Product not found.";
    exit;
}

/* ---------- Fetch variants ---------- */
$sql = "
  SELECT variant_id, product_id, color_hex, size, stock_qty
  FROM variants
  WHERE product_id = ?
  ORDER BY color_hex, size
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $productId);
$stmt->execute();
$res = $stmt->get_result();
$variants = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Build helper structures */
$colors = [];         // unique colors
$sizes  = [];         // unique sizes
$variantsMap = [];    // [color][size] => variant

foreach ($variants as $v) {
    $color = strtoupper($v['color_hex']);
    $size  = strtoupper($v['size']);

    if (!in_array($color, $colors, true)) {
        $colors[] = $color;
    }
    if (!in_array($size, $sizes, true)) {
        $sizes[] = $size;
    }
    $variantsMap[$color][$size] = $v;
}

/* Sort sizes in XS → S → M → L → XL → 2XL order */
$sizeOrder = ['XS','S','M','L','XL','2XL','XXL'];
usort($sizes, function($a, $b) use ($sizeOrder) {
    $ia = array_search($a, $sizeOrder);
    $ib = array_search($b, $sizeOrder);
    $ia = $ia === false ? 999 : $ia;
    $ib = $ib === false ? 999 : $ib;
    return $ia <=> $ib;
});

/* Default selected variant: first with stock > 0, else first */
$defaultVariant = null;
foreach ($variants as $v) {
    if ($v['stock_qty'] > 0) {
        $defaultVariant = $v;
        break;
    }
}
if (!$defaultVariant && !empty($variants)) {
    $defaultVariant = $variants[0];
}

/* ---------- Fetch product images ---------- */
$sql = "
  SELECT 
    pi.image_id,
    pi.product_id,
    pi.variant_id,
    pi.image_url,
    pi.is_primary,
    pi.sort_order,
    UPPER(v.color_hex) AS variant_color
  FROM product_images pi
  LEFT JOIN variants v ON v.variant_id = pi.variant_id
  WHERE pi.product_id = ?
    AND pi.is_primary = 0          -- exclude primary images from PDP
  ORDER BY pi.sort_order ASC, pi.image_id ASC
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $productId);
$stmt->execute();
$res = $stmt->get_result();
$images = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Default main image = first image (JS will refine by color) */
$mainImageUrl = null;
if (!empty($images)) {
    $mainImageUrl = $images[0]['image_url'];
}

/* ---------- You might also like (3 random) ---------- */
$sql = "
  SELECT 
    p.product_id,
    p.product_name,
    p.base_price,
    p.discount_flat,
    COALESCE(
      (SELECT image_url FROM product_images pi 
       WHERE pi.product_id = p.product_id AND pi.is_primary = 1 
       ORDER BY pi.sort_order ASC, pi.image_id ASC LIMIT 1),
      (SELECT image_url FROM product_images pi2 
       WHERE pi2.product_id = p.product_id 
       ORDER BY pi2.is_primary DESC, pi2.sort_order ASC, pi2.image_id ASC LIMIT 1)
    ) AS image_url
  FROM products p
  WHERE p.product_id <> ?
    AND p.category_id = ?
  ORDER BY RAND()
  LIMIT 3
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('ii', $productId, $product['category_id']);
$stmt->execute();
$res = $stmt->get_result();
$recommendations = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (count($recommendations) < 3) {
    // Fallback: top up from any category
    $needed = 3 - count($recommendations);
    $sql = "
      SELECT 
        p.product_id,
        p.product_name,
        p.base_price,
        p.discount_flat,
        COALESCE(
          (SELECT image_url FROM product_images pi 
           WHERE pi.product_id = p.product_id AND pi.is_primary = 1 
           ORDER BY pi.sort_order ASC, pi.image_id ASC LIMIT 1),
          (SELECT image_url FROM product_images pi2 
           WHERE pi2.product_id = p.product_id 
           ORDER BY pi2.is_primary DESC, pi2.sort_order ASC, pi2.image_id ASC LIMIT 1)
        ) AS image_url
      FROM products p
      WHERE p.product_id <> ?
      ORDER BY RAND()
      LIMIT ?
    ";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ii', $productId, $needed);
    $stmt->execute();
    $res = $stmt->get_result();
    $more = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $recommendations = array_merge($recommendations, $more);
}

/* ---------- Helpers ---------- */
function price_fmt($n) { return '$' . number_format((float)$n, 2); }

$orig   = (float)$product['base_price'];
$disc   = (float)$product['discount_flat'];
$final  = max($orig - $disc, 0);
$hasDisc = $disc > 0;

$variantsJs = [];
foreach ($variants as $v) {
    $variantsJs[] = [
        'variant_id' => (int)$v['variant_id'],
        'color'      => strtoupper($v['color_hex']),
        'size'       => strtoupper($v['size']),
        'stock_qty'  => (int)$v['stock_qty'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($product['product_name']) ?> | SHOPNAME</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?= BASE_URL ?>/stylesheet.css">
  <style>
    /* Layout */
    .product-page {
      max-width: 1200px;
      margin: 0 auto;
      padding: 32px 20px 60px;
      font-family: "Lexend Deca", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
    }
    .product-main {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 40px;
      align-items: flex-start;
    }
    @media (max-width: 900px) {
      .product-main { grid-template-columns: 1fr; }
    }

    /* Images */
    .product-images {
      display: grid;
      grid-template-columns: 80px 1fr;
      gap: 16px;
    }
    @media (max-width: 600px) {
      .product-images { grid-template-columns: 1fr; }
    }
    .product-thumbs {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .product-thumb {
      width: 100%;
      aspect-ratio: 1 / 1;
      overflow: hidden;
      border: 1px solid #e1e1e1;
      cursor: pointer;
      opacity: 0.7;
      transition: border-color .2s ease, opacity .2s ease;
    }
    .product-thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform .3s ease;
    }
    .product-thumb.active,
    .product-thumb:hover {
      border-color: #16B1B9;
      opacity: 1;
    }

    .product-main-image-wrapper {
      width: 100%;
      aspect-ratio: 1 / 1;
      border: 1px solid #e1e1e1;
      overflow: hidden;
    }
    .product-main-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform .35s ease;
    }


    /* Info */
    .product-info h1 {
      font-size: 28px;
      margin-bottom: 8px;
    }
    .product-price {
      margin-bottom: 20px;
      font-size: 18px;
    }
    .product-price .orig {
      text-decoration: line-through;
      opacity: .5;
      margin-right: 8px;
    }
    .product-price .final {
      font-weight: 600;
    }

    /* Color swatches */
    .product-option-label {
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: .06em;
      margin-bottom: 6px;
    }
    .color-swatches {
      display: flex;
      gap: 8px;
      margin-bottom: 16px;
    }
    .color-swatch {
      width: 24px;
      height: 24px;
      border-radius: 999px;
      border: 1px solid #ccc;
      cursor: pointer;
      position: relative;
      box-shadow: 0 0 0 1px rgba(0,0,0,0.05);
    }
    .color-swatch.selected {
      box-shadow: 0 0 0 2px #16B1B9;
      border-color: #16B1B9;
    }

    /* Size buttons */
    .size-options {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 8px;
    }
    .size-option {
      min-width: 40px;
      padding: 6px 12px;
      font-size: 13px;
      border-radius: 999px;
      border: 1px solid #ccc;
      background: #fff;
      cursor: pointer;
      transition: background .2s ease, border-color .2s ease, color .2s ease, opacity .2s ease;
    }
    .size-option.selected {
      background: #16B1B9;
      border-color: #16B1B9;
      color: #fff;
    }
    .size-option.disabled {
      opacity: .4;
      cursor: not-allowed;
    }

    .size-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 6px;
      margin-top: 4px;
    }
    .size-guide-link {
      font-size: 13px;
      text-decoration: underline;
      cursor: pointer;
    }

    /* Quantity + Add to Cart */
    .add-row {
      display: flex;
      gap: 12px;
      align-items: center;
      margin: 18px 0 20px;
    }
    .qty-control {
      display: inline-flex;
      align-items: center;
      border: 1px solid #ccc;
      border-radius: 999px;
      overflow: hidden;
    }
    .qty-btn {
      width: 30px;
      height: 32px;
      border: none;
      background: #f6f6f6;
      cursor: pointer;
      font-size: 18px;
      line-height: 1;
    }
    .qty-input {
      width: 40px;
      text-align: center;
      border: none;
      outline: none;
      font-size: 14px;
    }
    .add-to-cart-btn {
      flex: 1;
      background: #16B1B9;
      border: none;
      color: white;
      font-size: 16px;
      font-weight: 600;
      padding: 10px 18px;
      border-radius: 4px;
      cursor: pointer;
      transition: background .2s ease, transform .05s ease;
    }
    .add-to-cart-btn:hover {
      background: #1298a0;
    }
    .add-to-cart-btn:active {
      transform: translateY(1px);
    }
    .add-to-cart-btn:disabled {
      background: #ccc;
      cursor: not-allowed;
    }

    .stock-message {
      font-size: 13px;
      margin-bottom: 8px;
    }
    .stock-message.out {
      color: #c0392b;
    }
    .stock-message.in {
      color: #27ae60;
    }

    /* Description / Care */
    .product-description {
      margin-top: 10px;
      font-size: 14px;
      line-height: 1.6;
    }

    /* You might also like */
    .you-might-like {
      margin-top: 60px;
    }
    .you-might-like h2 {
      font-size: 20px;
      margin-bottom: 20px;
    }
    .you-might-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0,1fr));
      gap: 24px;
    }
    @media (max-width: 900px) {
      .you-might-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
    }
    @media (max-width: 600px) {
      .you-might-grid { grid-template-columns: 1fr; }
    }
    .you-might-item {
      border: 1px solid #eee;
      padding: 10px;
    }
    .you-might-image-wrapper {
      width: 100%;
      aspect-ratio: 1 / 1;
      overflow: hidden;
      background: #f8f8f8;
    }
    .you-might-image-wrapper img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform .35s ease;
    }
    .you-might-item:hover img {
      transform: scale(1.05);
    }
    .you-might-name {
      font-size: 14px;
      margin-top: 10px;
    }
    .you-might-price {
      font-size: 13px;
      margin-top: 4px;
    }
    .you-might-price .orig {
      text-decoration: line-through;
      opacity: .5;
      margin-right: 4px;
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/header.php'; ?>

  <main class="product-page">
    <section class="product-main">
      <!-- LEFT: images -->
      <div class="product-images">
        <div class="product-thumbs">
        <?php foreach ($images as $idx => $img): ?>
            <?php
            // variant_color is NULL for product-level images
            $colorAttr = $img['variant_color'] ?? '';
            ?>
            <button
            type="button"
            class="product-thumb <?= $idx === 0 ? 'active' : '' ?>"
            data-color="<?= htmlspecialchars($colorAttr) ?>"       
            data-image-url="<?= htmlspecialchars($img['image_url']) ?>"
            >
            <img src="<?= htmlspecialchars($img['image_url']) ?>" alt="Thumbnail">
            </button>
        <?php endforeach; ?>
        </div>

        <div class="product-main-image-wrapper">
          <img
            src="<?= htmlspecialchars($mainImageUrl ?: (BASE_URL . '/assets/images/tempimage.png')) ?>"
            alt="<?= htmlspecialchars($product['product_name']) ?>"
            class="product-main-image"
            id="mainImage"
          >
        </div>
      </div>

      <!-- RIGHT: info / options -->
      <div class="product-info">
        <h1><?= htmlspecialchars($product['product_name']) ?></h1>

        <div class="product-price">
          <?php if ($hasDisc): ?>
            <span class="orig"><?= price_fmt($orig) ?></span>
            <span class="final"><?= price_fmt($final) ?></span>
          <?php else: ?>
            <span class="final"><?= price_fmt($orig) ?></span>
          <?php endif; ?>
        </div>

        <!-- Color -->
        <?php if (!empty($colors)): ?>
          <div class="product-option">
            <div class="product-option-label">Color</div>
            <div class="color-swatches" id="colorSwatches">
              <?php foreach ($colors as $colorHex): ?>
                <?php
                  $isSelected = ($defaultVariant && strtoupper($defaultVariant['color_hex']) === $colorHex);
                ?>
                <button
                  type="button"
                  class="color-swatch <?= $isSelected ? 'selected' : '' ?>"
                  data-color="<?= htmlspecialchars($colorHex) ?>"
                  style="background-color: <?= htmlspecialchars($colorHex) ?>;"
                  aria-label="Color <?= htmlspecialchars($colorHex) ?>"
                ></button>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Size -->
        <?php if (!empty($sizes)): ?>
          <div class="product-option">
            <div class="size-row">
              <div class="product-option-label">Size</div>
              <div class="size-guide-link">Size Guide</div>
            </div>
            <div class="size-options" id="sizeOptions">
              <?php foreach ($sizes as $sz): ?>
                <?php
                  $isSelected = ($defaultVariant && strtoupper($defaultVariant['size']) === $sz);
                ?>
                <button
                  type="button"
                  class="size-option <?= $isSelected ? 'selected' : '' ?>"
                  data-size="<?= htmlspecialchars($sz) ?>"
                >
                  <?= htmlspecialchars($sz) ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Stock message -->
        <div id="stockMessage" class="stock-message">
          <?php if ($defaultVariant): ?>
            <?php if ($defaultVariant['stock_qty'] > 0): ?>
              <span class="in">In stock (<?= (int)$defaultVariant['stock_qty'] ?> available)</span>
            <?php else: ?>
              <span class="out">Out of stock</span>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <!-- Quantity + Add to cart -->
        <div class="add-row">
          <div class="qty-control">
            <button class="qty-btn" type="button" id="qtyMinus">−</button>
            <input class="qty-input" type="text" id="qtyInput" value="1" />
            <button class="qty-btn" type="button" id="qtyPlus">+</button>
          </div>
          <button
            class="add-to-cart-btn"
            type="button"
            id="addToCartBtn"
          >
            Add To Cart
          </button>
        </div>

        <div class="product-description">
          <?= nl2br(htmlspecialchars($product['description'])) ?>
        </div>

        <!-- Hidden input to hold selected variant_id for later cart logic -->
        <input type="hidden" id="selectedVariantId" value="<?= $defaultVariant ? (int)$defaultVariant['variant_id'] : 0 ?>">
      </div>
    </section>

    <!-- You might also like -->
    <?php if (!empty($recommendations)): ?>
      <section class="you-might-like">
        <h2>You might also like</h2>
        <div class="you-might-grid">
          <?php foreach ($recommendations as $rec): 
            $rOrig = (float)$rec['base_price'];
            $rDisc = (float)$rec['discount_flat'];
            $rFinal = max($rOrig - $rDisc, 0);
          ?>
            <article class="you-might-item">
              <a href="<?= BASE_URL ?>/product.php?id=<?= (int)$rec['product_id'] ?>">
                <div class="you-might-image-wrapper">
                  <img src="<?= htmlspecialchars($rec['image_url'] ?: (BASE_URL . '/assets/images/tempimage.png')) ?>" alt="<?= htmlspecialchars($rec['product_name']) ?>">
                </div>
              </a>
              <div class="you-might-name">
                <a href="<?= BASE_URL ?>/product.php?id=<?= (int)$rec['product_id'] ?>" class="product-name-link">
                  <?= htmlspecialchars($rec['product_name']) ?>
                </a>
              </div>
              <div class="you-might-price">
                <?php if ($rDisc > 0): ?>
                  <span class="orig"><?= price_fmt($rOrig) ?></span>
                  <span class="final"><?= price_fmt($rFinal) ?></span>
                <?php else: ?>
                  <span class="final"><?= price_fmt($rOrig) ?></span>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </main>

<script>
  // Variant data from PHP
  const VARIANTS = <?= json_encode($variantsJs, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
  let selectedColor = <?= $defaultVariant ? json_encode(strtoupper($defaultVariant['color_hex'])) : 'null' ?>;
  let selectedSize  = <?= $defaultVariant ? json_encode(strtoupper($defaultVariant['size']))      : 'null' ?>;

  function findVariant(color, size) {
    if (!color || !size) return null;
    return VARIANTS.find(v => v.color === color && v.size === size) || null;
  }

  function updateStockMessage(variant) {
    const el = document.getElementById('stockMessage');
    if (!el) return;
    if (!variant) {
      el.textContent = 'Please select color and size.';
      el.className = 'stock-message';
      return;
    }
    if (variant.stock_qty > 0) {
      el.textContent = `In stock (${variant.stock_qty} available)`;
      el.className = 'stock-message in';
    } else {
      el.textContent = 'Out of stock';
      el.className = 'stock-message out';
    }
  }

  // === NEW: image logic – only relevant variant images ===
 // Show images by COLOR (all sizes of same color share images)
function updateImagesForColor(colorHex) {
    const allThumbs = Array.from(document.querySelectorAll('.product-thumb'));
    if (!allThumbs.length) return;

    const normColor = colorHex ? colorHex.toUpperCase() : null;
    const colorThumbs = [];
    const productThumbs = [];

    allThumbs.forEach(btn => {
        const c = (btn.dataset.color || '').toUpperCase(); // "" for product-level
        if (c) {
        if (!normColor || c === normColor) {
            colorThumbs.push(btn);
        }
        } else {
        productThumbs.push(btn);
        }
    });

    // If we have color-specific images for this color, use only those.
    // Otherwise, fallback to product-level images.
    let visibleThumbs;
    if (normColor && colorThumbs.length) {
        visibleThumbs = colorThumbs;
    } else if (productThumbs.length) {
        visibleThumbs = productThumbs;
    } else {
        visibleThumbs = allThumbs; // total fallback
    }

    allThumbs.forEach(btn => {
        const show = visibleThumbs.includes(btn);
        btn.style.display = show ? 'block' : 'none';
        btn.classList.remove('active');
    });

    // Main image = first visible thumb
    if (visibleThumbs.length) {
        const first = visibleThumbs[0];
        first.classList.add('active');
        const main = document.getElementById('mainImage');
        if (main && first.dataset.imageUrl) {
        main.src = first.dataset.imageUrl;
        }
    }
    }

  document.addEventListener('DOMContentLoaded', () => {
    const colorBtns   = document.querySelectorAll('.color-swatch');
    const sizeBtns    = document.querySelectorAll('.size-option');
    const variantInput = document.getElementById('selectedVariantId');
    const addBtn      = document.getElementById('addToCartBtn');
    const qtyInput    = document.getElementById('qtyInput');
    const qtyMinus    = document.getElementById('qtyMinus');
    const qtyPlus     = document.getElementById('qtyPlus');

    // Thumbnails: click to update main image
    document.querySelectorAll('.product-thumb').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.product-thumb').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const imgUrl = btn.dataset.imageUrl;
        const main = document.getElementById('mainImage');
        if (main && imgUrl) main.src = imgUrl;
      });
    });

    // Color selection
    colorBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        colorBtns.forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        selectedColor = btn.dataset.color.toUpperCase();

        // Update size availability for this color
        sizeBtns.forEach(sbtn => {
          const sz = sbtn.dataset.size.toUpperCase();
          const v = findVariant(selectedColor, sz);
          if (!v) {
            sbtn.classList.add('disabled');
          } else {
            sbtn.classList.remove('disabled');
          }
        });

        const current = findVariant(selectedColor, selectedSize);
        if (!current) {
          selectedSize = null;
          sizeBtns.forEach(s => s.classList.remove('selected'));
        }

        const variant = findVariant(selectedColor, selectedSize);
        if (variant) {
        variantInput.value = variant.variant_id;
        updateStockMessage(variant);
        } else {
        variantInput.value = '';
        updateStockMessage(null);
        }
        updateImagesForColor(selectedColor);   // always drive images by COLOR

      });
    });

    // Size selection
    sizeBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        if (btn.classList.contains('disabled')) return;
        sizeBtns.forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        selectedSize = btn.dataset.size.toUpperCase();

        const variant = findVariant(selectedColor, selectedSize);
        if (variant) {
        variantInput.value = variant.variant_id;
        updateStockMessage(variant);
        } else {
        variantInput.value = '';
        updateStockMessage(null);
        }
        updateImagesForColor(selectedColor);
      });
    });

    // Quantity controls (with null guards just in case)
    if (qtyMinus && qtyInput) {
      qtyMinus.addEventListener('click', () => {
        let v = parseInt(qtyInput.value || '1', 10);
        if (isNaN(v) || v <= 1) v = 1;
        else v -= 1;
        qtyInput.value = v;
      });
    }
    if (qtyPlus && qtyInput) {
      qtyPlus.addEventListener('click', () => {
        let v = parseInt(qtyInput.value || '1', 10);
        if (isNaN(v) || v < 1) v = 1;
        else v += 1;
        qtyInput.value = v;
      });
    }

    // Add to Cart stub (will hook into cart drawer later)
    if (addBtn) {
      addBtn.addEventListener('click', () => {
        const variantId = parseInt(variantInput.value || '0', 10);
        if (!variantId) {
          alert('Please select a color and size before adding to cart.');
          return;
        }
        const qty = parseInt(qtyInput.value || '1', 10);
        if (!qty || qty < 1) {
          alert('Please enter a valid quantity.');
          return;
        }

        const formData = new URLSearchParams();
        formData.append('variant_id', String(variantId));
        formData.append('quantity', String(qty));

        fetch('<?= BASE_URL ?>/add_to_cart.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          },
          body: formData.toString(),
        })
        .then(res => res.json())
        .then(data => {
          if (!data.ok) {
            alert(data.message || 'Unable to add to cart.');
            return;
          }

          // Optionally update a cart count badge here using data.total_items

          // Open the cart drawer
          if (typeof window.openCartDrawer === 'function') {
            window.openCartDrawer();
          }
        })
        .catch(() => {
          alert('Network error while adding to cart.');
        });
      });
    }

    // Initial images for default variant
    updateImagesForColor(<?= $defaultVariant ? json_encode(strtoupper($defaultVariant['color_hex'])) : 'null' ?>);

  });
</script>

</body>
</html>
