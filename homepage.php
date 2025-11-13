<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/config.php';

/* ----------------- Newsletter form handler ----------------- */
$newsletterMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newsletter_email'])) {
    $email = trim($_POST['newsletter_email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $newsletterMessage = 'Please enter a valid email address.';
    } else {
        // 1) Insert email into users table (newsletter-only user)
        $stmt = $mysqli->prepare("
            INSERT IGNORE INTO users
                (first_name, last_name, email, password_hash, delivery_address, date_created)
            VALUES
                ('', '', ?, '', '', NOW())
        ");
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->close();
        }

        // 2) Send welcome email using PHP mail()
        $shopName   = 'SHOPNAME';           // change this
        $fromEmail  = 'no-reply@localhost'; // must match Mercury account
        $replyEmail = 'support@localhost';

        $subject = "Welcome to $shopName";

        $body  = "Hey climber,\r\n\r\n";
        $body .= "Thanks for signing up to the $shopName newsletter.\r\n\r\n";
        $body .= "You’ll be the first to hear about new drops, limited collections,\r\n";
        $body .= "and route-tested gear we’re building for the wall and beyond.\r\n\r\n";
        $body .= "You can start exploring here:\r\n";
        $body .= BASE_URL . "/productlist.php\r\n\r\n";
        $body .= "Climb safe,\r\n";
        $body .= "The $shopName Crew\r\n";

        $headers  = "From: $shopName <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$replyEmail}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        if (mail($email, $subject, $body, $headers)) {
            $newsletterMessage = 'Thanks for signing up! Check your inbox for a welcome email.';
        } else {
            $newsletterMessage = 'Thanks for signing up! (Could not send email from server.)';
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
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
  <style>
    body {
      font-family: "Lexend Deca", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
    }
    .landing-page {
      max-width: 1200px;
      margin: 0 auto;
    }

    /* Hero */
    .herohm {
      position: relative;
      height: 520px;
      margin-bottom: 0px;
      background-image: url("<?= BASE_URL ?>/assets/images/tempbanner.png"); /* change to your hero img */
      background-size: cover;
      background-position: center;
      color: #fff;
    }
    .hero-overlayhm {
      position: absolute;
      display:flex;
      justify-content:center:
      inset: 0;
      background: linear-gradient(to right, rgba(0,0,0,.55), rgba(0,0,0,.15));
    }
    .hero-contents {
      position: relative;
      z-index: 1;
      padding: 120px 40px;
      max-width: 560px;
      display: grid;
      align-items:center;
      justify-items: left;
      text-align:left;
    }
    .hero-title {
      font-size: 40px;
      line-height: 1.05;
      margin-bottom: 16px;
    }
    .hero-text {
      font-size: 16px;
      line-height: 1.6;
      margin-bottom: 24px;
      max-width: 420px;
      width: 100%;
      display: inline-block;
    }
    .hero-btn {
      display: inline-block;
      padding: 10px 26px;
      border-radius: 4px;
      border: none;
      background: #16B1B9;
      color: #fff;
      font-weight: 600;
      font-size: 14px;
      text-decoration: none;
      cursor: pointer;
      transition: background .2s ease, transform .05s ease;
      width: fit-content;
    }
    .hero-btn:hover { background:#1298a0; }
    .hero-btn:active { transform: translateY(1px); }

    /* Section headers */
    .section {
      padding: 80px 72px 40px 72px;
    }
    .section-header {
      margin-bottom: 24px;
    }
    .section-title {
      font-size: 22px;
      margin-bottom: 4px;
    }
    .section-subtitle {
      font-size: 14px;
      color: #555;
    }

    .section-abt{
      padding: 80px 0;
    }

    /* New Arrivals */
    .new-arrivals-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0,1fr));
      gap: 24px;
    }
    @media (max-width: 900px) {
      .new-arrivals-grid {
        grid-template-columns: repeat(2, minmax(0,1fr));
      }
    }
    @media (max-width: 600px) {
      .new-arrivals-grid {
        grid-template-columns: 1fr;
      }
    }
    .product-card {
      border: 1px solid #eee;
      padding: 14px;
      background: #fff;
    }
    .product-card-image {
      width: 100%;
      aspect-ratio: 1 / 1;
      background: #f5f5f5;
      overflow: hidden;
    }
    .product-card-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .product-card-name {
      margin-top: 12px;
      font-size: 14px;
    }
    .product-card-name a {
      color: inherit;
      text-decoration: none;
    }
    .product-card-name a:hover {
      text-decoration: underline;
    }
    .product-card-price {
      margin-top: 4px;
      font-size: 13px;
    }
    .product-card-price .orig {
      text-decoration: line-through;
      opacity: .5;
      margin-right: 6px;
    }
    .product-card-price .final {
      font-weight: 600;
    }

    /* About / company section */
    .about-section-inner {
      display: grid;
      width: 100%;
      padding: 24px 72px;
      background: var(--color-alt-bg-grey);
      grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
      gap: 32px;
      align-items: center;
    }
    @media (max-width: 900px) {
      .about-section-inner {
        grid-template-columns: 1fr;
      }
    }
    .about-image {
      width: 100%;
      height: 320px;
      background-image: url("<?= BASE_URL ?>/assets/images/about-climbers.png");
      background-size: cover;
      background-position: center;
    }
    .about-copy-title {
      font-size: 24px;
      margin-bottom: 10px;
    }
    .about-copy-highlight {
      font-size: 15px;
      color: #16B1B9;
      margin-bottom: 8px;
    }
    .about-copy-body {
      font-size: 14px;
      line-height: 1.7;
      margin-bottom: 20px;
    }
    .about-btn {
      display:inline-block;
      padding: 8px 20px;
      border-radius:4px;
      border:none;
      background:#16B1B9;
      color:#fff;
      font-size:14px;
      font-weight:600;
      text-decoration:none;
      cursor:pointer;
      margin-top: 16px;
    }
    .about-btn:hover { background:#1298a0; }

/* Shop Collection */
.shop-collection-section {
  color: #1a0c06;
  padding-top: 40px;
  padding-bottom: 60px;
}

.shop-collection-section .section-title {
  color: #f5f5f5;
}

.shop-collection-section .section-subtitle {
  color: #e0e0e0;
}

.shop-collection-grid {
  display: grid;
  grid-template-columns: 2fr 1.4fr;
  grid-template-rows: repeat(2, minmax(0, 1fr));
  gap: 24px;
}

/* Large left card spanning both rows */
.shop-collection-card--large {
  grid-row: 1 / 3;
}

  /* Card styling */
  .shop-collection-card {
    display: flex;
    gap: 18px;
    padding: 18px;
    border: 1px solid #222;
    background: #f5f5f5;
    align-items: center;
  }

  .shop-collection-image {
    flex: 0 0 40%;
    aspect-ratio: 1 / 1;
    background: #eaeaea;
    overflow: hidden;
  }

  .shop-collection-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .shop-collection-text-title {
    font-size: 16px;
    margin-bottom: 4px;
    color: #2b1710;
  }

  .shop-collection-copy {
    font-size: 13px;
    margin-bottom: 6px;
    color: #555;
  }

  .shop-collection-link {
    font-size: 13px;
    color: #4d2513;
    text-decoration: none;
  }

  .shop-collection-link span {
    margin-left: 4px;
  }

  /* Responsive tweaks */
  @media (max-width: 900px) {
    .shop-collection-grid {
      grid-template-columns: 1fr;
      grid-template-rows: auto;
    }
    .shop-collection-card--large {
      grid-row: auto;
    }
  }

  /* New Arrivals hover effect */
  .product-card-image {
    width: 100%;
    aspect-ratio: 1 / 1;
    background: #f5f5f5;
    overflow: hidden;
  }

  .product-card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .3s ease;
  }

  .product-card:hover .product-card-image img {
    transform: scale(1.06);
  }

 /* Newsletter – full-width image strip */
  .newsletter-section {
    background-image: url("<?= BASE_URL ?>/assets/images/newsletter.png");
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    padding: 70px 20px;
    color: #ffffff;
    height: fit-content;
      display: flex;
  justify-content: center;
  
  }

  .newsletter-overlay {
    max-width: 100%;
    text-align: center;
    height: fit-content;
    display: grid;
    padding: 24px 0px;
  }

  .newsletter-title {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 8px;
  }

  .newsletter-subtitles {
    font-size: 16px;
    font-weight: 400;
    margin-bottom: 16px;
  }

  .newsletter-form {
    display: inline-flex;
    align-items: stretch;
    max-width: 520px;
    width: 100%;
  }

  .newsletter-input {
    flex: 1;
    padding: 10px 16px;
    border-radius: 6px 0 0 6px;
    border: none;
    font-size: 14px;
    outline: none;
  }

  .newsletter-button {
    padding: 0 22px;
    border-radius: 0 6px 6px 0;
    border: none;
    background: #4b260f;      /* dark brown */
    color: #ffffff;
    font-weight: 600;
    font-size: 18px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .newsletter-button:hover {
    background: #371b0a;
  }

  .newsletter-message {
    margin-top: 10px;
    font-size: 13px;
  }
    /* Simple footer */
    .footer {
      padding:20px;
      border-top:1px solid #eee;
      font-size:12px;
      color:#777;
      text-align:center;
      margin-top:20px;
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/header.php'; ?>

  <main class="landing-page">

    <!-- Hero / Banner -->
    <section class="herohm">
      <div class="hero-overlay"></div>
      <div class="hero-contents">
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
        <h2 class="section-title">New Arrival</h2>
      </div>

      <div class="new-arrivals-grid">
        <?php foreach ($newArrivals as $p): ?>
          <?php
            $orig  = (float)$p['base_price'];
            $disc  = (float)$p['discount_flat'];
            $final = max($orig - $disc, 0);
          ?>
          <article class="product-card">
            <a href="<?= BASE_URL ?>/product.php?id=<?= (int)$p['product_id'] ?>">
              <div class="product-card-image">
                <img src="<?= htmlspecialchars($p['image_url'] ?: (BASE_URL . '/assets/images/tempimage.png')) ?>"
                     alt="<?= htmlspecialchars($p['product_name']) ?>">
              </div>
            </a>
            <div class="product-card-name">
              <a href="<?= BASE_URL ?>/product.php?id=<?= (int)$p['product_id'] ?>">
                <body><?= htmlspecialchars($p['product_name']) ?></body>
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
    <section class="section-abt">
      <div class="about-section-inner">
        <div class="about-image"></div>
        <div>
          <div class="about-copy-highlight"><body>About SHOPNAME</body></div>
          <h3 class="about-copy-title">Climbing-first apparel with everyday comfort.</h3>
          <p><body class="about-copy-body">
            We started Daey after too many sessions spent in gear that felt
            like a compromise—heavy at the gym and out of place everywhere else.
            Today, every piece we make is built around three principles:
            unrestricted movement, durable construction, and clean, low-key design.
              </body></p>
          <a href="<?= BASE_URL ?>/about.php" class="about-btn">Learn More</a>
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
              Chalk buckets and socks to round out your kit and
              make every session smoother.
            </p>
            <a href="<?= BASE_URL ?>/productlist.php?category=4,5" class="shop-collection-link">
              View collection <span>→</span>
            </a>
          </div>
        </article>
      </div>
    </section>

    <!-- Newsletter -->
  <section class="newsletter-section">
    <div class="newsletter-overlay">
      <h2 class="newsletter-title">Join Our Newsletter</h2>
      <p class="newsletter-subtitles">
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

    <!-- (Testimonials section skipped as requested) -->


    <!-- Simple Footer -->
    <footer class="footer">
      © <?= date('Y') ?> SHOPNAME. All rights reserved.
    </footer>
  </main>
</body>
</html>
