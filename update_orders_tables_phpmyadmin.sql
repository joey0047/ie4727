-- ============================================
-- UPDATE SCRIPT FOR ORDERS AND ORDER_ITEMS TABLES
-- For phpMyAdmin - Based on your existing table structure
-- ============================================
-- Instructions:
-- 1. Open phpMyAdmin
-- 2. Select your database (sports_apparel)
-- 3. Click on the "SQL" tab
-- 4. Copy and paste the sections below
-- 5. If you get an error about a column already existing, just skip that line
-- ============================================

-- ============================================
-- ORDERS TABLE - Add missing columns
-- ============================================
-- Your existing columns: order_id, user_id, order_date, status, voucher_id, total_price, shipping_address
-- Adding: subtotal, shipping, first_name, last_name, email, country, postal_code, phone

ALTER TABLE `orders` ADD COLUMN `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `orders` ADD COLUMN `shipping` DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `orders` ADD COLUMN `first_name` VARCHAR(100);
ALTER TABLE `orders` ADD COLUMN `last_name` VARCHAR(100);
ALTER TABLE `orders` ADD COLUMN `email` VARCHAR(255);
ALTER TABLE `orders` ADD COLUMN `country` VARCHAR(100);
ALTER TABLE `orders` ADD COLUMN `postal_code` VARCHAR(20);
ALTER TABLE `orders` ADD COLUMN `phone` VARCHAR(20);

-- ============================================
-- ORDER_ITEMS TABLE - Add missing columns
-- ============================================
-- Your existing columns: order_item_id, order_id, variant_id, quantity, price_each
-- Adding: product_id, product_name, line_total, size, color_hex

ALTER TABLE `order_items` ADD COLUMN `product_id` INT NOT NULL;
ALTER TABLE `order_items` ADD COLUMN `product_name` VARCHAR(255) NOT NULL;
ALTER TABLE `order_items` ADD COLUMN `line_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `order_items` ADD COLUMN `size` VARCHAR(10);
ALTER TABLE `order_items` ADD COLUMN `color_hex` VARCHAR(20);
