<?php // /partials/footer.php ?>
<?php
// Compute a base path relative to the current script (works in subfolders)
if (!isset($BASE)) {
    $BASE = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
}
?>
<footer class="footer">
    <div class="footer-top">
        <div class="footer-left">
            <h2 class="footer-logo">Daey</h2>
            <p class="footer-tagline">Climbing Apparel Store</p>
        </div>
        <nav class="footer-nav">
            <a href="<?= $BASE ?>/homepage.php" class="footer-link">Home</a>
            <a href="<?= $BASE ?>/productlist.php" class="footer-link">Shop</a>
            <a href="<?= $BASE ?>/aboutus.php" class="footer-link">About Us</a>
            <a href="<?= $BASE ?>/contactUs.php" class="footer-link">Contact Us</a>
        </nav>
    </div>
    <div class="footer-bottom">
        <p class="copyright">Copyright © <?= date('Y') ?> Daey. All rights reserved</p>
        <div class="footer-links">
            <a href="#" class="footer-link-small">Privacy Policy</a>
            <a href="#" class="footer-link-small">Terms of Use</a>
        </div>
        <div class="social-icons">
            <span class="social-icon">📷</span>
            <span class="social-icon">📘</span>
            <span class="social-icon">🐦</span>
            <span class="social-icon">▶</span>
        </div>
    </div>
</footer>

