<?php
session_start();
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

/* ----------------- Newsletter form handler ----------------- */
$newsletterMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newsletter_email'])) {
    $email = trim($_POST['newsletter_email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $newsletterMessage = 'Please enter a valid email address.';
    } else {
        // 1) Insert email into users table (newsletter-only user)
        //    - empty first_name, last_name, password, delivery_address
        //    - IGNORE in case the email already exists
        $stmt = $mysqli->prepare("
            INSERT IGNORE INTO users
                (FIRST_name, last_name, email, password_hash, delivery_address, date_created)
            VALUES
                ('',        '',        ?,    '',            '',               NOW())
        ");
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->close();
        }

        // 2) Compose welcome email
        $subject = 'Welcome to SHOPNAME';
        $body = "Hey climber,\n\n"
              . "Thanks for signing up to the SHOPNAME newsletter.\n\n"
              . "You’ll be the first to hear about new drops, limited collections,\n"
              . "and route-tested gear we’re building for the wall and beyond.\n\n"
              . "In the meantime, you can start exploring the latest collection here:\n"
              . BASE_URL . "/productlist.php\n\n"
              . "Climb safe,\n"
              . "The SHOPNAME Crew\n";

        $headers = "From: SHOPNAME <no-reply@example.com>\r\n"
                 . "Reply-To: support@example.com\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n";

        if (@mail($email, $subject, $body, $headers)) {
            $newsletterMessage = 'Thanks for signing up! Check your inbox for a welcome email.';
        } else {
            $newsletterMessage = 'Thanks for signing up! (Email sending is disabled on this server demo.)';
        }
    }
}

/* ----------------- New Arrivals (3 random products) ----------------- */
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
  ORDER BY RAND()
  LIMIT 3
";
$res = $mysqli->query($sql);
$newArrivals = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

/* ----------------- Shop Collection images (one per group) ----------------- */
// Tops = Shirts (category_id = 1)
$tops = null;
$res = $mysqli->query("
  SELECT 
    p.product_id,
    p.product_name,
    COALESCE(
      (SELECT image_url FROM product_images pi 
       WHERE pi.product_id = p.product_id AND pi.is_primary = 1
       ORDER BY pi.sort_order ASC, pi.image_id ASC LIMIT 1),
      (SELECT image_url FROM product_images pi2 
       WHERE pi2.product_id = p.product_id
       ORDER BY pi2.is_primary DESC, pi2.sort_order ASC, pi2.image_id ASC LIMIT 1)
    ) AS image_url
  FROM products p
  WHERE p.category_id = 1
  ORDER BY p.product_id ASC
  LIMIT 1
");
if ($res && $res->num_rows) $tops = $res->fetch_assoc();

// Bottoms = Shorts + Long Pants (2,3)
$bottoms = null;
$res = $mysqli->query("
  SELECT 
    p.product_id,
    p.product_name,
    COALESCE(
      (SELECT image_url FROM product_images pi 
       WHERE pi.product_id = p.product_id AND pi.is_primary = 1
       ORDER BY pi.sort_order ASC, pi.image_id ASC LIMIT 1),
      (SELECT image_url FROM product_images pi2 
       WHERE pi2.product_id = p.product_id
       ORDER BY pi2.is_primary DESC, pi2.sort_order ASC, pi2.image_id ASC LIMIT 1)
    ) AS image_url
  FROM products p
  WHERE p.category_id IN (2,3)
  ORDER BY p.product_id ASC
  LIMIT 1
");
if ($res && $res->num_rows) $bottoms = $res->fetch_assoc();

// Accessories = Socks + Chalk Bags (4,5)
$accessories = null;
$res = $mysqli->query("
  SELECT 
    p.product_id,
    p.product_name,
    COALESCE(
      (SELECT image_url FROM product_images pi 
       WHERE pi.product_id = p.product_id AND pi.is_primary = 1
       ORDER BY pi.sort_order ASC, pi.image_id ASC LIMIT 1),
      (SELECT image_url FROM product_images pi2 
       WHERE pi2.product_id = p.product_id
       ORDER BY pi2.is_primary DESC, pi2.sort_order ASC, pi2.image_id ASC LIMIT 1)
    ) AS image_url
  FROM products p
  WHERE p.category_id IN (4,5)
  ORDER BY p.product_id ASC
  LIMIT 1
");
if ($res && $res->num_rows) $accessories = $res->fetch_assoc();

/* ----------------- Helpers ----------------- */
function price_fmt($n) { return '$' . number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>SHOPNAME | Climbing Apparel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?= BASE_URL ?>/stylesheet.css">
</head>
<body>
  <?php include __DIR__ . '/partials/header.php'; ?>

  <main class="landing-page">

    <!-- Hero / Banner -->
    <section class="hero">
      <div class="hero-overlay"></div>
      <div class="hero-content">
        <h1 class="hero-title">Gear up for your next send.</h1>
        <p class="hero-text">
          Route-tested climbing apparel designed to move with you—on the wall, at the gym,
          and everywhere in between. Built from durable fabrics with clean, everyday styling.
        </p>
        <a href="<?= BASE_URL ?>/productlist.php" class="hero-btn">Shop Collection</a>
      </div>
    </section>

    <!-- New Arrival / Best Seller / Sale -->
    <section class="section">
      <div class="section-header">
        <h2 class="section-title">New Arrival / Best Seller / Sale</h2>
      </div>

      <div class="new-arrivals-grid">
        <?php foreach ($newArrivals as $p): ?>
          <?php
            $orig  = (float)$p['base_price'];
            $disc  = (float)$p['discount_flat'];
            $final = max($orig - $disc, 0);
          ?>
          <article class="product-card">
            <a href="<?= $isLoggedIn ? BASE_URL . '/product.php?id=' . (int)$p['product_id'] : BASE_URL . '/logInPage.php' ?>">
              <div class="product-card-image">
                <img src="<?= htmlspecialchars($p['image_url'] ?: (BASE_URL . '/assets/images/tempimage.png')) ?>"
                     alt="<?= htmlspecialchars($p['product_name']) ?>">
              </div>
            </a>
            <div class="product-card-name">
              <a href="<?= $isLoggedIn ? BASE_URL . '/product.php?id=' . (int)$p['product_id'] : BASE_URL . '/logInPage.php' ?>">
                <?= htmlspecialchars($p['product_name']) ?>
              </a>
            </div>
            <div class="product-card-price">
              <?php if ($disc > 0): ?>
                <span class="orig"><?= price_fmt($orig) ?></span>
                <span class="final"><?= price_fmt($final) ?></span>
              <?php else: ?>
                <span class="final"><?= price_fmt($orig) ?></span>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Company Related / About -->
    <section class="section">
      <div class="about-section-inner">
        <div class="about-image"></div>
        <div>
          <div class="about-copy-highlight">About Daey</div>
          <h3 class="about-copy-title">Climbing-first apparel with everyday comfort.</h3>
          <p class="about-copy-body">
            We started Daey after too many sessions spent in gear that felt
            like a compromise—heavy at the gym and out of place everywhere else.
            Today, every piece we make is built around three principles:
            unrestricted movement, durable construction, and clean, low-key design.
          </p>
          <p class="about-copy-body">
            From overbuilt heavyweight tees that hold their shape to lightweight
            shorts that dry fast on hot approaches, our apparel is tested by real climbers
            and refined with every runout. No gimmicks, just gear that works as hard as you do.
          </p>
          <a href="<?= BASE_URL ?>/aboutus.php" class="about-btn">Learn More</a>
        </div>
      </div>
    </section>

    <!-- Shop Collection -->
    <section class="section shop-collection-section">
      <div class="shop-collection-grid">
        <!-- Tops (large card spanning two rows) -->
        <article class="shop-collection-card shop-collection-card--large">
          <div class="shop-collection-image">
            <a href="<?= BASE_URL ?>/productlist.php?category=1">
              <img src="<?= htmlspecialchars($tops['image_url'] ?? (BASE_URL . '/assets/images/tempimage.png')) ?>"
                  alt="Tops collection">
            </a>
          </div>
          <div>
            <div class="shop-collection-text-title">Tops</div>
            <p class="shop-collection-copy">
              Oversized tees and breathable layers designed for long sessions,
              big moves, and everything after.
            </p>
            <a href="<?= BASE_URL ?>/productlist.php?category=1" class="shop-collection-link">
              View collection <span>→</span>
            </a>
          </div>
        </article>

        <!-- Bottoms -->
        <article class="shop-collection-card">
          <div class="shop-collection-image">
            <a href="<?= BASE_URL ?>/productlist.php?category=2,3">
              <img src="<?= htmlspecialchars($bottoms['image_url'] ?? (BASE_URL . '/assets/images/tempimage.png')) ?>"
                  alt="Bottoms collection">
            </a>
          </div>
          <div>
            <div class="shop-collection-text-title">Bottoms</div>
            <p class="shop-collection-copy">
              Lightweight harness-friendly shorts and hard-wearing pants that keep
              you covered from boulders to big walls.
            </p>
            <a href="<?= BASE_URL ?>/productlist.php?category=2,3" class="shop-collection-link">
              View collection <span>→</span>
            </a>
          </div>
        </article>

        <!-- Accessories -->
        <article class="shop-collection-card">
          <div class="shop-collection-image">
            <a href="<?= BASE_URL ?>/productlist.php?category=4,5">
              <img src="<?= htmlspecialchars($accessories['image_url'] ?? (BASE_URL . '/assets/images/tempimage.png')) ?>"
                  alt="Accessories collection">
            </a>
          </div>
          <div>
            <div class="shop-collection-text-title">Accessories</div>
            <p class="shop-collection-copy">
              Chalk buckets, socks, and small essentials to round out your kit and
              make every session smoother.
            </p>
            <a href="<?= BASE_URL ?>/productlist.php?category=4,5" class="shop-collection-link">
              View collection <span>→</span>
            </a>
          </div>
        </article>
      </div>
    </section>

    <!-- (Testimonials section skipped as requested) -->

  </main>

  <!-- Newsletter -->
  <section class="newsletter-section">
    <div class="newsletter-overlay"></div>
    <div class="newsletter-content">
      <h2 class="newsletter-title">Join Our Newsletter</h2>
      <p class="newsletter-subtitle">
        Sign up for deals, new products and promotions.
      </p>
      <form class="newsletter-form" method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
        <input
          type="email"
          name="newsletter_email"
          class="newsletter-input"
          placeholder="Enter Email"
          required
        >
        <button type="submit" class="newsletter-button">
          &rarr;
        </button>
      </form>
      <?php if ($newsletterMessage): ?>
        <div class="newsletter-message"><?= htmlspecialchars($newsletterMessage) ?></div>
      <?php endif; ?>
    </div>
  </section>

<?php include __DIR__ . '/partials/footer.php'; ?>

<?php include __DIR__ . '/cart.php'; ?>

</body>
</html>
