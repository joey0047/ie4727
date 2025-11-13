<?php // /partials/header.php ?>
<?php
// Compute a base path relative to the current script (works in subfolders)
$BASE = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
?>
<header class="header">
  <div class="header-top">
    <nav class="nav-left">
      <!-- Shop (click to open; stays open until click-away or Esc) -->
        <div class="nav-item nav-shop" id="navShop">
        <!-- Clicking 'Shop' goes to ALL products (no filter) -->
        <a href="<?= $BASE ?>/productlist.php" class="nav-link">Shop</a>

        <!-- Caret toggles dropdown -->
        <button class="shop-toggle" id="shopToggle" aria-haspopup="true" aria-expanded="false" aria-controls="shopMenu">
            <span class="dropdown-arrow">▼</span>
        </button>

        <div class="shop-dropdown" id="shopMenu" role="menu">
            <a href="<?= $BASE ?>/productlist.php"              class="shop-link" role="menuitem">Shop All</a>
            <a href="<?= $BASE ?>/productlist.php?category=1"   class="shop-link" role="menuitem">Tops</a>         <!-- Shirts -->
            <a href="<?= $BASE ?>/productlist.php?category=2,3" class="shop-link" role="menuitem">Bottoms</a>      <!-- Shorts + Long Pants -->
            <a href="<?= $BASE ?>/productlist.php?category=4,5" class="shop-link" role="menuitem">Accessories</a>  <!-- Socks + Chalk Bags -->
        </div>
        </div>  

      <a href="<?= $BASE ?>/aboutus.php" class="nav-link">About Us</a>
      <a href="<?= $BASE ?>/contactUs.php" class="nav-link">Contact Us</a>
    </nav>

    <div class="logo">
      <a href="<?= $BASE ?>/homepage.php"><h1>DAEY</h1></a>
    </div>

    <div class="header-icons">
      <a href="<?= $BASE ?>/profileMain.php" aria-label="Account" >
        <img src="assets\icon\user.png" alt="User">
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
