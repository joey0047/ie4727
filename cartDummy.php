<?php
session_start();

// Reset cart (optional — remove this line if you want to keep items)
$_SESSION['cart'] = [];

// Dummy example items (replace with your real variant IDs later)
$_SESSION['cart'][] = [
    'variant_id' => 1,
    'product_name' => 'Slab Tee Mark',
    'size' => 'M',
    'color' => '#000000',
    'price' => 33.00 - 8.00, // base_price - discount_flat
    'qty' => 1
];

$_SESSION['cart'][] = [
    'variant_id' => 2,
    'product_name' => 'Slab Tee Untamed',
    'size' => 'L',
    'color' => '#000000',
    'price' => 30.00 - 5.00,
    'qty' => 2
];

echo "<h1>Dummy Cart Created</h1>";
echo "<p><a href='checkOut.php'>Go to Checkout</a></p>";
