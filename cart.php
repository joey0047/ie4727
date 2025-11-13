<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/config.php';

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

$cartId = null;
$items = [];
$subtotal = 0;

if ($userId) {
    $stmt = $mysqli->prepare("SELECT cart_id FROM carts WHERE user_id=? ORDER BY date_created DESC LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($cartId);
    $stmt->fetch();
    $stmt->close();

    if ($cartId) {
        $sql = "
            SELECT 
                ci.cart_item_id,
                ci.quantity,
                v.size,
                v.color_hex,
                p.product_name,
                p.base_price,
                p.discount_flat,
                p.product_id,
                (SELECT image_url FROM product_images WHERE product_id = p.product_id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS img
            FROM cart_items ci
            JOIN variants v ON v.variant_id = ci.variant_id
            JOIN products p ON p.product_id = v.product_id
            WHERE cart_id=?
        ";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $cartId);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $price = max($row['base_price'] - $row['discount_flat'], 0);
            $row['price'] = $price;
            $row['line']  = $price * $row['quantity'];
            $subtotal += $row['line'];
            $items[] = $row;
        }
        $stmt->close();
    }
}
?>

<div id="cartBackdrop" class="cart-backdrop"></div>

<div id="cartDrawer" class="cart-drawer">
    <div class="cart-header">
        <h3>My Cart</h3>
        <button id="cartClose">&times;</button>
    </div>

    <div class="cart-body">
        <?php if (empty($items)): ?>
            <p>Your cart is empty.</p>
        <?php else: ?>
            <div class="cart-items-wrapper">
                <?php foreach ($items as $item): ?>
                    <div class="cart-item" data-item-id="<?= (int)$item['cart_item_id'] ?>">
                        <img src="<?= htmlspecialchars($item['img'] ?: (BASE_URL . '/assets/images/tempimage.png')) ?>" class="cart-img" alt="<?= htmlspecialchars($item['product_name']) ?>">
                        <div class="cart-info">
                            <strong><?= htmlspecialchars($item['product_name']) ?></strong><br>
                            <?php if (!empty($item['size'])): ?>
                                Size: <?= htmlspecialchars(strtoupper($item['size'])) ?> |
                            <?php endif; ?>
                            <?php if (!empty($item['color_hex'])): ?>
                                Color: <?= htmlspecialchars(strtoupper($item['color_hex'])) ?><br>
                            <?php endif; ?>
                            <div class="cart-item-controls">
                                <div class="cart-qty-control">
                                    <button class="cart-qty-btn" data-action="minus" data-item-id="<?= (int)$item['cart_item_id'] ?>">−</button>
                                    <span class="cart-qty-value"><?= (int)$item['quantity'] ?></span>
                                    <button class="cart-qty-btn" data-action="plus" data-item-id="<?= (int)$item['cart_item_id'] ?>">+</button>
                                </div>
                                <button class="cart-item-remove" data-item-id="<?= (int)$item['cart_item_id'] ?>" title="Remove">×</button>
                            </div>
                            <div class="cart-item-price">$<?= number_format($item['line'], 2) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="cart-footer">
        <div class="subtotal">
            <span>Subtotal:</span>
            <span>$<?= number_format($subtotal, 2) ?></span>
        </div>
        <button class="checkout-btn" onclick="location.href='<?= BASE_URL ?>/checkOut.php'">Check Out</button>
    </div>
</div>

<script>
const drawer = document.getElementById('cartDrawer');
const backdrop = document.getElementById('cartBackdrop');
const closeBtn = document.getElementById('cartClose');

// GLOBAL FUNCTION to open cart from anywhere
window.openCart = function () {
  if (drawer) drawer.classList.add("open");
  if (backdrop) backdrop.classList.add("open");
};

function closeCart() {
  if (drawer) drawer.classList.remove("open");
  if (backdrop) backdrop.classList.remove("open");
}

if (closeBtn) closeBtn.onclick = closeCart;
if (backdrop) backdrop.onclick = closeCart;

// Auto-open cart after page reload (from add to cart)
document.addEventListener('DOMContentLoaded', function() {
  if (sessionStorage.getItem('openCartAfterReload') === 'true') {
    sessionStorage.removeItem('openCartAfterReload');
    setTimeout(function() {
      window.openCart();
    }, 100);
  }
});

// Function to refresh cart drawer content via AJAX
window.refreshCartDrawer = function() {
  return fetch('<?= BASE_URL ?>/cart.php', {
    method: 'GET',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
    },
  })
  .then(response => response.text())
  .then(html => {
    // Create a temporary container to parse the HTML
    const temp = document.createElement('div');
    temp.innerHTML = html;

    // Extract cart items wrapper and footer
    const newCartBody = temp.querySelector('.cart-body');
    const newCartFooter = temp.querySelector('.cart-footer');

    if (newCartBody && newCartFooter) {
      const currentCartBody = document.querySelector('.cart-body');
      const currentCartFooter = document.querySelector('.cart-footer');

      if (currentCartBody && currentCartFooter) {
        currentCartBody.innerHTML = newCartBody.innerHTML;
        currentCartFooter.innerHTML = newCartFooter.innerHTML;
        
        // Re-attach event listeners after refresh
        attachCartItemListeners();
      }
    }
  })
  .catch(err => {
    console.error('Error refreshing cart:', err);
    // Fallback to page reload
    location.reload();
  });
};

// Function to attach event listeners to cart items
function attachCartItemListeners() {
  // Quantity controls
  document.querySelectorAll('.cart-qty-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      const itemId = parseInt(this.getAttribute('data-item-id'), 10);
      const action = this.getAttribute('data-action');
      updateCartItem(itemId, action);
    });
  });

  // Remove buttons
  document.querySelectorAll('.cart-item-remove').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      const itemId = parseInt(this.getAttribute('data-item-id'), 10);
      if (confirm('Remove this item from cart?')) {
        updateCartItem(itemId, 'remove');
      }
    });
  });
}

// Function to update cart item (quantity or remove)
function updateCartItem(itemId, action) {
  if (!itemId || itemId <= 0) {
    console.error('Invalid cart item ID:', itemId);
    return;
  }

  fetch('<?= BASE_URL ?>/update_cart_item.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
    },
    body: `cart_item_id=${itemId}&action=${action}`,
  })
  .then(res => res.json())
  .then(data => {
    if (data.ok) {
      // Refresh cart drawer
      if (typeof window.refreshCartDrawer === 'function') {
        window.refreshCartDrawer();
      } else {
        location.reload();
      }
    } else {
      alert(data.message || 'Unable to update cart.');
    }
  })
  .catch(err => {
    console.error('Update cart item error:', err);
    alert('Network error while updating cart.');
  });
}

// Attach listeners on page load
document.addEventListener('DOMContentLoaded', function() {
  attachCartItemListeners();
});
</script>
