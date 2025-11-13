<?php
// cart.php – cart drawer partial
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/config.php';

// TODO: replace with real logged-in user
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;

$cartId = null;
$items  = [];
$subtotal = 0.0;

// Find latest cart for this user
$stmt = $mysqli->prepare("SELECT cart_id FROM carts WHERE user_id = ? ORDER BY date_created DESC LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($cartId);
$stmt->fetch();
$stmt->close();

if ($cartId) {
    $sql = "
      SELECT 
        ci.cart_item_id,
        ci.quantity,
        v.variant_id,
        v.size,
        v.color_hex,
        p.product_id,
        p.product_name,
        p.base_price,
        p.discount_flat,
        COALESCE(
          (SELECT image_url FROM product_images pi 
             WHERE pi.product_id = p.product_id 
               AND (pi.variant_id = v.variant_id OR pi.variant_id IS NULL)
               AND pi.is_primary = 1
             ORDER BY pi.sort_order ASC, pi.image_id ASC LIMIT 1),
          (SELECT image_url FROM product_images pi2 
             WHERE pi2.product_id = p.product_id
             ORDER BY pi2.is_primary DESC, pi2.sort_order ASC, pi2.image_id ASC LIMIT 1)
        ) AS image_url
      FROM cart_items ci
      JOIN variants v ON v.variant_id = ci.variant_id
      JOIN products p ON p.product_id = v.product_id
      WHERE ci.cart_id = ?
      ORDER BY ci.cart_item_id DESC
    ";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $cartId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $priceOrig = (float)$row['base_price'];
        $disc      = (float)$row['discount_flat'];
        $unitPrice = max($priceOrig - $disc, 0);
        $row['unit_price'] = $unitPrice;
        $row['line_total'] = $unitPrice * (int)$row['quantity'];
        $subtotal += $row['line_total'];
        $items[] = $row;
    }
    $stmt->close();
}

function cart_fmt_price($n) { return '$' . number_format($n, 2); }
?>

<style>
/* Cart drawer */
.cart-drawer-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.35);
  display: none;
  z-index: 900;
}

.cart-drawer {
  position: fixed;
  top: 0;
  right: 0;
  width: 420px;
  max-width: 100%;
  height: 100vh;
  background: #ffffff;
  box-shadow: -4px 0 12px rgba(0,0,0,.15);
  transform: translateX(100%);
  transition: transform .25s ease;
  display: flex;
  flex-direction: column;
  z-index: 901;
}

.cart-drawer.open {
  transform: translateX(0);
}

.cart-drawer-backdrop.open {
  display: block;
}

/* Header */
.cart-header {
  padding: 20px 20px 12px;
  border-bottom: 1px solid #eee;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.cart-header-title {
  font-size: 20px;
  font-weight: 600;
}

.cart-close-btn {
  background: none;
  border: none;
  font-size: 22px;
  cursor: pointer;
}

/* Scrollable items area */
.cart-items-wrapper {
  flex: 1;
  overflow-y: auto;
  padding: 12px 20px 20px;
}

/* Single item */
.cart-item {
  display: grid;
  grid-template-columns: 80px 1fr 70px;
  gap: 12px;
  margin-bottom: 16px;
  align-items: center;
}

.cart-item-image {
  width: 80px;
  height: 80px;
  background: #f5f5f5;
  overflow: hidden;
}

.cart-item-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cart-item-name {
  font-size: 14px;
  margin-bottom: 4px;
}

.cart-item-meta {
  font-size: 12px;
  color: #777;
}

.cart-item-price {
  font-size: 14px;
  font-weight: 600;
  margin-top: 6px;
}

.cart-item-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
}

/* Qty controls */
.cart-qty-control {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  border: 1px solid #ddd;
  overflow: hidden;
}

.cart-qty-btn {
  width: 26px;
  height: 30px;
  border: none;
  background: #fafafa;
  cursor: pointer;
  font-size: 16px;
}

.cart-qty-value {
  min-width: 24px;
  text-align: center;
  font-size: 14px;
}

.cart-item-remove-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 18px;
}

/* Footer */
.cart-footer {
  border-top: 1px solid #eee;
  padding: 14px 20px 20px;
  background: #fff;
}

.cart-subtotal-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 14px;
}

.cart-subtotal-label {
  font-size: 16px;
}

.cart-subtotal-value {
  font-size: 16px;
  font-weight: 600;
}

.cart-checkout-btn {
  width: 100%;
  padding: 12px 18px;
  border-radius: 4px;
  border: none;
  background: #16B1B9;
  color: #ffffff;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
}

.cart-checkout-btn:hover {
  background: #1298a0;
}
</style>

<div id="cartBackdrop" class="cart-drawer-backdrop"></div>

<aside id="cartDrawer" class="cart-drawer" aria-hidden="true">
  <header class="cart-header">
    <div class="cart-header-title">Your Cart</div>
    <button type="button" class="cart-close-btn" id="cartCloseBtn">&times;</button>
  </header>

  <div class="cart-items-wrapper">
    <?php if (empty($items)): ?>
      <p>Your cart is empty.</p>
    <?php else: ?>
      <?php foreach ($items as $item): ?>
        <div class="cart-item" data-item-id="<?= (int)$item['cart_item_id'] ?>">
          <a class="cart-item-image" href="<?= BASE_URL ?>/product.php?id=<?= (int)$item['product_id'] ?>">
            <img src="<?= htmlspecialchars($item['image_url'] ?: (BASE_URL . '/assets/images/tempimage.png')) ?>"
                 alt="<?= htmlspecialchars($item['product_name']) ?>">
          </a>

          <div>
            <div class="cart-item-name">
              <?= htmlspecialchars($item['product_name']) ?>
            </div>
            <div class="cart-item-meta">
              Size <?= htmlspecialchars(strtoupper($item['size'])) ?> |
              Color <?= htmlspecialchars(strtoupper($item['color_hex'])) ?>
            </div>
            <div class="cart-item-price">
              <?= cart_fmt_price($item['unit_price']) ?>
            </div>
          </div>

          <div class="cart-item-actions">
            <div class="cart-qty-control">
              <button class="cart-qty-btn" type="button"
                      data-action="minus"
                      data-item-id="<?= (int)$item['cart_item_id'] ?>">−</button>
              <div class="cart-qty-value"><?= (int)$item['quantity'] ?></div>
              <button class="cart-qty-btn" type="button"
                      data-action="plus"
                      data-item-id="<?= (int)$item['cart_item_id'] ?>">+</button>
            </div>
            <button class="cart-item-remove-btn" type="button"
                    data-action="remove"
                    data-item-id="<?= (int)$item['cart_item_id'] ?>">🗑</button>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <footer class="cart-footer">
    <div class="cart-subtotal-row">
      <span class="cart-subtotal-label">Subtotal</span>
      <span class="cart-subtotal-value"><?= cart_fmt_price($subtotal) ?></span>
    </div>
    <button type="button" class="cart-checkout-btn"
            onclick="window.location.href='<?= BASE_URL ?>/checkout.php';">
      Check Out
    </button>
  </footer>
</aside>

<script>
  // Build VARIANTS array manually without json_encode
  const VARIANTS = [
    <?php foreach ($variants as $v): ?>
      {
        variant_id: <?= (int)$v['variant_id'] ?>,
        color: "<?= strtoupper($v['color_hex']) ?>",
        size: "<?= strtoupper($v['size']) ?>",
        stock_qty: <?= (int)$v['stock_qty'] ?>
      },
    <?php endforeach; ?>
  ];

  // Default color + size from PHP
  <?php if ($defaultVariant): ?>
    let selectedColor = "<?= strtoupper($defaultVariant['color_hex']) ?>";
    let selectedSize  = "<?= strtoupper($defaultVariant['size']) ?>";
  <?php else: ?>
    let selectedColor = null;
    let selectedSize  = null;
  <?php endif; ?>

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
    const colorBtns    = document.querySelectorAll('.color-swatch');
    const sizeBtns     = document.querySelectorAll('.size-option');
    const variantInput = document.getElementById('selectedVariantId');
    const addBtn       = document.getElementById('addToCartBtn');
    const qtyInput     = document.getElementById('qtyInput');
    const qtyMinus     = document.getElementById('qtyMinus');
    const qtyPlus      = document.getElementById('qtyPlus');

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
        updateImagesForColor(selectedColor);
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

    // Quantity controls
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

    // Add to Cart → calls add_to_cart.php and opens drawer / shows confirmation
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
          console.log('add_to_cart response:', data);
          if (!data.ok) {
            alert(data.message || 'Unable to add to cart.');
            return;
          }

          // If the cart drawer JS is loaded, open it
          if (typeof window.openCartDrawer === 'function') {
            window.openCartDrawer();
          } else {
            // Fallback: at least tell the user it worked
            alert('Added to cart!');
          }
        })
        .catch(err => {
          console.error('add_to_cart error:', err);
          alert('Network error while adding to cart.');
        });
      });
    }

    // Initial images for default variant (if any)
    <?php if ($defaultVariant): ?>
      updateImagesForColor("<?= strtoupper($defaultVariant['color_hex']) ?>");
    <?php else: ?>
      updateImagesForColor(null);
    <?php endif; ?>
  });
</script>

