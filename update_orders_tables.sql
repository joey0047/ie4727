-- ============================================
-- UPDATE SCRIPT FOR ORDERS AND ORDER_ITEMS TABLES
-- For phpMyAdmin - Run this in the SQL tab
-- ============================================
-- Note: If you get errors about columns already existing, 
-- just skip those lines or comment them out

-- ============================================
-- ORDERS TABLE
-- ============================================
-- Add missing columns to orders table
-- (Skip any that already exist)

ALTER TABLE `orders` 
ADD COLUMN `user_id` INT NOT NULL AFTER `order_id`,
ADD COLUMN `order_date` DATETIME NOT NULL AFTER `user_id`,
ADD COLUMN `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `order_date`,
ADD COLUMN `shipping` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `subtotal`,
ADD COLUMN `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `shipping`,
ADD COLUMN `first_name` VARCHAR(100) AFTER `total`,
ADD COLUMN `last_name` VARCHAR(100) AFTER `first_name`,
ADD COLUMN `email` VARCHAR(255) AFTER `last_name`,
ADD COLUMN `delivery_address` TEXT AFTER `email`,
ADD COLUMN `country` VARCHAR(100) AFTER `delivery_address`,
ADD COLUMN `postal_code` VARCHAR(20) AFTER `country`,
ADD COLUMN `phone` VARCHAR(20) AFTER `postal_code`;

-- ============================================
-- ORDER_ITEMS TABLE
-- ============================================
-- Add missing columns to order_items table
-- (Skip any that already exist)

ALTER TABLE `order_items`
ADD COLUMN `order_id` INT NOT NULL AFTER `order_item_id`,
ADD COLUMN `variant_id` INT AFTER `order_id`,
ADD COLUMN `product_id` INT NOT NULL AFTER `variant_id`,
ADD COLUMN `product_name` VARCHAR(255) NOT NULL AFTER `product_id`,
ADD COLUMN `quantity` INT NOT NULL DEFAULT 1 AFTER `product_name`,
ADD COLUMN `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `quantity`,
ADD COLUMN `line_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `unit_price`,
ADD COLUMN `size` VARCHAR(10) AFTER `line_total`,
ADD COLUMN `color_hex` VARCHAR(20) AFTER `size`;

-- ============================================
-- FOREIGN KEYS (Optional - uncomment if needed)
-- ============================================
-- ALTER TABLE `orders` 
-- ADD CONSTRAINT `fk_orders_user` 
-- FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`);

-- ALTER TABLE `order_items` 
-- ADD CONSTRAINT `fk_order_items_order` 
-- FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`);

