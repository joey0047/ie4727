<?php // /partials/header.php ?>
<?php
// Compute a base path relative to the current script (works in subfolders)
$BASE = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
?>
<header class="header">
    <link rel="stylesheet" href="stylesheet.css">
  <style>
    .header {padding:0;}
    .header-top{display:grid; grid-template-columns: repeat(3, 1fr);align-items:center;justify-content:space-between;padding:16px 72px; max-width: 1440px; height: fit-content}
    .nav-left{display:flex;gap:24px;align-items:center;}
    .nav-link, .shop-toggle, .shop-link{
      text-decoration:none;color:inherit;font:inherit;background:none;border:0;padding:0;cursor:pointer;
    }
    .nav-item.nav-shop{position:relative;}
    .shop-dropdown{
      position:absolute; left:0; top:120%;
      min-width:240px; background:#fff; border:1px solid #ddd;
      box-shadow:0 4px 16px rgba(0,0,0,.08); border-radius:4px; padding:8px 0; z-index:50;
      display:none;
    }
    .nav-item.nav-shop.open .shop-dropdown{display:block;}
    .shop-link{display:block;padding:10px 16px;color:#222;}
    .shop-link:hover{background:#f5f5f5;}
    .dropdown-arrow{margin-left:6px; font-size:.9em;}
  </style>

  <div class="header-top">
    <nav class="nav-left">
      <!-- Shop (click to open; stays open until click-away or Esc) -->
        <div class="nav-item nav-shop" id="navShop">
        <!-- Clicking 'Shop' goes to ALL products (no filter) -->
        <a href="<?= $BASE ?>/productlist.php" class="nav-link" style="text-decoration:none;color:inherit;"><body>Shop</body></a>

        <!-- Caret toggles dropdown -->
        <button class="shop-toggle" id="shopToggle" aria-haspopup="true" aria-expanded="false" aria-controls="shopMenu">
            <span class="dropdown-arrow">▼</span>
        </button>

        <div class="shop-dropdown" id="shopMenu" role="menu" style="
            display:none; position:absolute; left:0; top:120%;
            min-width:240px; background:#fff; border:1px solid #ddd;
            box-shadow:0 4px 16px rgba(0,0,0,.08); border-radius:4px; padding:8px 0; z-index:50;">
            <a href="<?= $BASE ?>/productlist.php"              class="shop-link" role="menuitem"><body>Shop All</body></a>
            <a href="<?= $BASE ?>/productlist.php?category=1"   class="shop-link" role="menuitem"><body>Tops</body></a>         <!-- Shirts -->
            <a href="<?= $BASE ?>/productlist.php?category=2,3" class="shop-link" role="menuitem"><body>Bottoms</body></a>      <!-- Shorts + Long Pants -->
            <a href="<?= $BASE ?>/productlist.php?category=4,5" class="shop-link" role="menuitem"><body>Accessories</body></a>  <!-- Socks + Chalk Bags -->
        </div>
        </div>  

      <a href="<?= $BASE ?>/aboutus.php" class="nav-link"><body>About</body></a>
    </nav>

    <div class="logo">
      <a href="<?= $BASE ?>/homepage.php" style="text-decoration:none; color:#16B1B9;"><h1 style="margin:0;">Daey</h1></a>
    </div>

    <div class="header-icons" style="align-item:center">
      <a href="<?= $BASE ?>/profileMain.php" aria-label="Account" style="height:100%">
        <img src="assets\icon\user.png" alt="User"style="height:100%">
      </a>
      <img src="assets\icon\shopping-bag.png" id="cartIcon" class="icon" alt="Cart">
    </div>
  </div>
</header>

<script>
(function(){
  const holder = document.getElementById('navShop');
  const toggle = document.getElementById('shopToggle');
  const menu   = document.getElementById('shopMenu');
  if (!holder || !toggle || !menu) return;

  function openMenu(){ holder.classList.add('open'); menu.style.display='block'; toggle.setAttribute('aria-expanded','true'); }
  function closeMenu(){ holder.classList.remove('open'); menu.style.display='none'; toggle.setAttribute('aria-expanded','false'); }
  toggle.addEventListener('click', (e)=>{ e.preventDefault(); holder.classList.contains('open') ? closeMenu() : openMenu(); });
  document.addEventListener('click', (e)=>{ if (!holder.contains(e.target)) closeMenu(); });
  document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') closeMenu(); });
  menu.addEventListener('click', (e)=>{ const a=e.target.closest('a'); if (a) closeMenu(); });
})();

// Cart icon click handler
document.addEventListener('DOMContentLoaded', function() {
  const cartIcon = document.getElementById('cartIcon');
  if (cartIcon) {
    cartIcon.addEventListener('click', function() {
      if (typeof window.openCart === 'function') {
        window.openCart();
      }
    });
  }
});
</script>
