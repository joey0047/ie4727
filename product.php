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
    $color = !empty($v['color_hex']) ? strtoupper(trim($v['color_hex'])) : '';
    $size  = !empty($v['size']) ? strtoupper(trim($v['size'])) : '';

    // Only add color if it's not empty and not already in the list
    if ($color && $color !== '' && !in_array($color, $colors, true)) {
        $colors[] = $color;
    }
    // Only add size if it's not empty and not already in the list
    if ($size && $size !== '' && !in_array($size, $sizes, true)) {
        $sizes[] = $size;
    }
    // Build variant map - handle cases where color or size might be empty
    if ($color && $size) {
        $variantsMap[$color][$size] = $v;
    } elseif ($size) {
        // Product with size but no color (like socks)
        $variantsMap[''][$size] = $v;
    } elseif ($color) {
        // Product with color but no size (unlikely but handle it)
        $variantsMap[$color][''] = $v;
    } else {
        // Product with neither color nor size (single variant product)
        $variantsMap[''][''] = $v;
    }
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
        'color'      => !empty($v['color_hex']) ? strtoupper(trim($v['color_hex'])) : '',
        'size'       => !empty($v['size']) ? strtoupper(trim($v['size'])) : '',
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

        <?php 
          // Check if chalk bag (category_id = 5) - define once for use throughout
          $isChalkBag = isset($product['category_id']) && (int)$product['category_id'] === 5;
        ?>

        <div class="product-price">
          <?php if ($hasDisc): ?>
            <span class="orig"><?= price_fmt($orig) ?></span>
            <span class="final"><?= price_fmt($final) ?></span>
          <?php else: ?>
            <span class="final"><?= price_fmt($orig) ?></span>
          <?php endif; ?>
        </div>

        <!-- Color (only show if multiple colors) -->
        <?php if (count($colors) > 1): ?>
          <div class="product-option">
            <div class="product-option-label">Color</div>
            <div class="color-swatches" id="colorSwatches">
              <?php foreach ($colors as $colorHex): ?>
                <?php
                  $isSelected = ($defaultVariant && strtoupper($defaultVariant['color_hex'] ?? '') === $colorHex);
                ?>
                <button
                  type="button"
                  class="color-swatch <?= $isSelected ? 'selected' : '' ?>"
                  data-color="<?= htmlspecialchars($colorHex) ?>"
                  data-bg-color="<?= htmlspecialchars($colorHex) ?>"
                  aria-label="Color <?= htmlspecialchars($colorHex) ?>"
                  style="background-color: <?= htmlspecialchars($colorHex) ?>;"
                ></button>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Size (only show if multiple sizes) -->
        <?php if (count($sizes) > 1): ?>
          <div class="product-option">
            <div class="size-row">
              <div class="product-option-label">Size</div>
              <div class="size-guide-link">Size Guide</div>
            </div>
            <div class="size-options" id="sizeOptions">
              <?php foreach ($sizes as $sz): ?>
                <?php
                  $isSelected = ($defaultVariant && strtoupper($defaultVariant['size'] ?? '') === $sz);
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
            <?php 
              // Check if out of stock
              $isOutOfStock = $defaultVariant && (int)$defaultVariant['stock_qty'] <= 0;
              if ($isChalkBag && $isOutOfStock): 
            ?>
              disabled
            <?php endif; ?>
          >
            <?php if ($isChalkBag && $isOutOfStock): ?>
              Sold Out
            <?php else: ?>
              Add To Cart
            <?php endif; ?>
          </button>
        </div>

        <div class="product-description">
          <?= nl2br(htmlspecialchars($product['description'])) ?>
        </div>

        <!-- Hidden input to hold selected variant_id for later cart logic -->
        <input type="hidden" id="selectedVariantId" value="<?= $defaultVariant ? (int)$defaultVariant['variant_id'] : 0 ?>">
        
        <?php 
          // Only show error if no variants AND not a chalk bag
          if (empty($variants) && !$isChalkBag): 
        ?>
          <div class="product-error">
            <strong>Product Configuration Error:</strong> This product does not have any variants configured.
          </div>
          <script>
            // Disable add to cart button if no variants
            document.addEventListener('DOMContentLoaded', () => {
              const addBtn = document.getElementById('addToCartBtn');
              if (addBtn) {
                addBtn.disabled = true;
                addBtn.style.opacity = '0.5';
                addBtn.style.cursor = 'not-allowed';
              }
            });
          </script>
        <?php endif; ?>
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
  let selectedColor = <?= $defaultVariant ? json_encode(strtoupper($defaultVariant['color_hex'] ?? '')) : 'null' ?>;
  let selectedSize  = <?= $defaultVariant ? json_encode(strtoupper($defaultVariant['size'] ?? ''))      : 'null' ?>;

  function findVariant(color, size) {
    // If no color/size needed (single variant product), return first variant
    if (VARIANTS.length === 1) {
      return VARIANTS[0];
    }
    
    // If all variants have the same color (or no color), use that color
    const uniqueColors = [...new Set(VARIANTS.map(v => v.color || ''))];
    if (uniqueColors.length === 1) {
      color = uniqueColors[0] || '';
    }
    
    // If all variants have the same size (or no size), use that size
    const uniqueSizes = [...new Set(VARIANTS.map(v => v.size || ''))];
    if (uniqueSizes.length === 1) {
      size = uniqueSizes[0] || '';
    }
    
    // Find variant matching color and size (empty strings match empty/null values)
    return VARIANTS.find(v => {
      const vColor = (v.color || '').toUpperCase();
      const vSize = (v.size || '').toUpperCase();
      const matchColor = (!color && !vColor) || (color && vColor && color.toUpperCase() === vColor);
      const matchSize = (!size && !vSize) || (size && vSize && size.toUpperCase() === vSize);
      return matchColor && matchSize;
    }) || null;
  }

  function updateStockMessage(variant) {
    const el = document.getElementById('stockMessage');
    const addBtn = document.getElementById('addToCartBtn');
    const isChalkBag = <?= isset($product['category_id']) && (int)$product['category_id'] === 5 ? 'true' : 'false' ?>;
    
    if (!el) return;
    if (!variant) {
      // Check if color/size selection is needed
      const uniqueColors = [...new Set(VARIANTS.map(v => v.color || ''))];
      const uniqueSizes = [...new Set(VARIANTS.map(v => v.size || ''))];
      if (uniqueColors.length > 1 || uniqueSizes.length > 1) {
        el.textContent = 'Please select color and size.';
      } else {
        el.textContent = 'Product not available.';
      }
      el.className = 'stock-message';
      return;
    }
    if (variant.stock_qty > 0) {
      el.textContent = `In stock (${variant.stock_qty} available)`;
      el.className = 'stock-message in';
      if (addBtn) {
        addBtn.disabled = false;
        addBtn.textContent = 'Add To Cart';
        addBtn.style.opacity = '1';
        addBtn.style.cursor = 'pointer';
      }
    } else {
      el.textContent = 'Out of stock';
      el.className = 'stock-message out';
      if (addBtn && isChalkBag) {
        addBtn.disabled = true;
        addBtn.textContent = 'Sold Out';
        addBtn.style.opacity = '0.5';
        addBtn.style.cursor = 'not-allowed';
      } else if (addBtn) {
        addBtn.disabled = true;
        addBtn.style.opacity = '0.5';
        addBtn.style.cursor = 'not-allowed';
      }
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

    // Auto-select variant if there's only one option (no color/size selection needed)
    if (VARIANTS.length > 0) {
      const uniqueColors = [...new Set(VARIANTS.map(v => v.color || ''))];
      const uniqueSizes = [...new Set(VARIANTS.map(v => v.size || ''))];
      
      // If only one color and one size (or no color/size), auto-select the variant
      if ((uniqueColors.length <= 1 && uniqueSizes.length <= 1) || VARIANTS.length === 1) {
        const autoVariant = findVariant(selectedColor || '', selectedSize || '');
        if (autoVariant && variantInput) {
          variantInput.value = autoVariant.variant_id;
          selectedColor = autoVariant.color || '';
          selectedSize = autoVariant.size || '';
          updateStockMessage(autoVariant);
        }
      }
    }
    
    // Initial check: disable button for chalk bags if out of stock on page load
    const isChalkBag = <?= $isChalkBag ? 'true' : 'false' ?>;
    if (isChalkBag && addBtn && variantInput) {
      const initialVariantId = parseInt(variantInput.value || '0', 10);
      if (initialVariantId > 0) {
        const initialVariant = VARIANTS.find(v => v.variant_id === initialVariantId);
        if (initialVariant && initialVariant.stock_qty <= 0) {
          addBtn.disabled = true;
          addBtn.textContent = 'Sold Out';
          addBtn.style.opacity = '0.5';
          addBtn.style.cursor = 'not-allowed';
        }
      }
    }

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
        // Prevent click if button is disabled (e.g., chalk bag sold out)
        if (addBtn.disabled) {
          return;
        }
        
        let variantId = parseInt(variantInput.value || '0', 10);
        
        // If no variant selected but we have variants, use the default/first one
        if (!variantId && VARIANTS.length > 0) {
          variantId = VARIANTS[0].variant_id;
          variantInput.value = variantId;
        }
        
        if (!variantId || variantId <= 0) {
          alert('This product is not available.');
          return;
        }
        
        // Verify the variant exists in our VARIANTS array
        const selectedVariant = VARIANTS.find(v => v.variant_id === variantId);
        if (!selectedVariant) {
          console.error('Variant not found:', variantId, 'Available variants:', VARIANTS);
          alert('Invalid product selection. Please refresh the page and try again.');
          return;
        }
        
        const qty = parseInt(qtyInput.value || '1', 10);
        if (!qty || qty < 1) {
          alert('Please enter a valid quantity.');
          return;
        }
        
        if (selectedVariant.stock_qty < qty) {
          alert(`Only ${selectedVariant.stock_qty} items available in stock.`);
          return;
        }

        console.log('Adding to cart - Variant ID:', variantId, 'Quantity:', qty, 'Variant:', selectedVariant);

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
          console.log('Add to cart response:', data);
          if (!data.ok) {
            alert(data.message || 'Unable to add to cart.');
            return;
          }

          // Refresh cart drawer via AJAX and open it
          if (typeof window.refreshCartDrawer === 'function') {
            window.refreshCartDrawer().then(() => {
              if (typeof window.openCart === 'function') {
                window.openCart();
              }
            });
          } else {
            // Fallback: reload page and open cart
            sessionStorage.setItem('openCartAfterReload', 'true');
            window.location.reload();
          }
        })
        .catch((err) => {
          console.error('Add to cart error:', err);
          alert('Network error while adding to cart.');
        });
      });
    }

    // Initial images for default variant
    updateImagesForColor(<?= $defaultVariant ? json_encode(strtoupper($defaultVariant['color_hex'])) : 'null' ?>);

  });
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>

<?php include __DIR__ . '/cart.php'; ?>

</body>
</html>
