<?php
define('DIR', __DIR__);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Daey</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="stylesheet.css">
</head>
<body>

<?php include DIR . '/partials/header.php'; ?>

<!-- Hero Section -->
<section class="about-hero">
    <div class="about-hero-overlay"></div>
    <div class="about-hero-content">
        <h1 class="about-hero-title">About Daey</h1>
        <p class="about-hero-caption">Climbing-first apparel with everyday comfort</p>
    </div>
</section>

<!-- Intro Section -->
<section class="about-intro-section">
    <p class="about-intro-text">
        We started Daey after too many sessions spent in gear that felt like a compromise—heavy at the gym and out of place everywhere else. Today, every piece we make is built around three principles: unrestricted movement, durable construction, and clean, low-key design.
    </p>
    <p class="about-intro-text">
        From overbuilt heavyweight tees that hold their shape to lightweight shorts that dry fast on hot approaches, our apparel is tested by real climbers and refined with every runout. No gimmicks, just gear that works as hard as you do.
    </p>
</section>

<!-- Premium Quality Materials Section -->
<section class="about-premium-section">
    <div class="about-premium-wrapper">
        <div class="about-premium-content">
            <h2 class="about-feature-title">Premium Quality Materials</h2>
            <p class="about-feature-text">
                We source only the finest materials for our climbing apparel. Every fabric is carefully selected for its breathability, moisture-wicking properties, and durability. Our commitment to quality ensures that your gear will withstand the toughest climbs while keeping you comfortable.
            </p>
            <p class="about-feature-text">
                Our technical fabrics are designed to move with you, providing the flexibility and freedom you need for dynamic movements on the wall. Whether you're bouldering indoors or tackling multi-pitch routes outdoors, our apparel adapts to your needs.
            </p>
        </div>
        <div class="about-premium-image">
            <img src="assets/images/woman1.jpg" alt="Quality Materials">
        </div>
    </div>
</section>

<!-- Quotation Section -->
<section class="about-feature-section-quote">
    <div class="about-feature-wrapper">
        <div class="about-feature-image">
            <img src="assets/images/teamppl.png" alt="Our Team">
        </div>
        <div class="about-feature-content">
            <p class="about-quote">
                "Climbing isn't just a sport—it's a way of life. At Daey, we understand that every route, every hold, and every move matters. That's why we craft apparel that doesn't just meet the demands of climbing, but elevates your entire experience."
            </p>
            <p class="about-quote-author">— The Daey Team</p>
        </div>
    </div>
</section>

<!-- Video Section -->
<section class="about-video-section">
    <div class="about-video-wrapper">
        <iframe 
            src="https://www.youtube.com/embed/Ky2ZdAd0qzY" 
            frameborder="0" 
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
            allowfullscreen
            class="about-video-iframe">
        </iframe>
    </div>
</section>

<!-- Built for Adventure Section -->
<section class="about-adventure-section">
    <div class="about-adventure-wrapper">
        <div class="about-adventure-image">
            <img src="assets/images/climb1.png" alt="Built for Adventure">
        </div>
        <div class="about-adventure-content">
            <h2 class="about-adventure-title">Built for Adventure</h2>
            <p class="about-adventure-text">
                Every piece in our collection is designed with the climber in mind. We test our products in real-world conditions, from indoor gyms to outdoor crags, ensuring they perform when it matters most.
            </p>
            <p class="about-adventure-text">
                Our commitment to quality and innovation drives us to continuously improve our designs, incorporating feedback from the climbing community to create apparel that truly serves your needs.
            </p>
            <a href="homepage.html" class="btn btn-brown">Explore Our Products</a>
        </div>
    </div>
</section>

<?php include DIR . '/partials/footer.php'; ?>

<?php include DIR . '/cart.php'; ?>

</body>
</html>

