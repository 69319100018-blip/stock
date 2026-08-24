-- STREAMING_CHUNK:Creating database and setting character set...
CREATE DATABASE IF NOT EXISTS `stock_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `stock_system`;

-- STREAMING_CHUNK:Creating core user management table...
-- 1. ตารางผู้ใช้งาน (users)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `fullname` VARCHAR(100) NOT NULL,
  `bio` TEXT NULL,
  `phone` VARCHAR(30) NULL,
  `avatar_color` VARCHAR(20) NULL DEFAULT '#06b6d4',
  `avatar_path` VARCHAR(255) NULL,
  `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STREAMING_CHUNK:Creating categories and suppliers tables...
-- 2. ตารางหมวดหมู่สินค้า (categories)
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ตารางซัพพลายเออร์ / คู่ค้า (suppliers)
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `email` VARCHAR(100) NULL,
  `address` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STREAMING_CHUNK:Creating products table with location and barcode fields...
-- 4. ตารางรายการสินค้า (products)
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sku` VARCHAR(50) NOT NULL UNIQUE,
  `barcode` VARCHAR(100) NULL,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `category_id` INT DEFAULT NULL,
  `location_zone` VARCHAR(100) DEFAULT 'Zone A - Shelf 01',
  `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `sell_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `quantity` INT NOT NULL DEFAULT 0,
  `min_threshold` INT NOT NULL DEFAULT 5,
  `image` VARCHAR(255) NULL,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STREAMING_CHUNK:Creating product batches table for FEFO expiration control...
-- 5. ตารางล็อตสินค้าและวันหมดอายุ (product_batches)
CREATE TABLE IF NOT EXISTS `product_batches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `batch_number` VARCHAR(50) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 0,
  `expiry_date` DATE NOT NULL,
  `location_zone` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STREAMING_CHUNK:Creating purchase orders and PO items tables...
-- 6. ตารางใบสั่งซื้อสินค้า (purchase_orders)
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `po_number` VARCHAR(50) NOT NULL UNIQUE,
  `supplier_id` INT NOT NULL,
  `status` ENUM('draft', 'pending', 'approved', 'received', 'cancelled') NOT NULL DEFAULT 'pending',
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `order_date` DATE NULL,
  `expected_delivery` DATE NULL,
  `note` TEXT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `received_at` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. ตารางรายการสินค้าในใบสั่งซื้อ (po_items)
CREATE TABLE IF NOT EXISTS `po_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `po_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity_ordered` INT NOT NULL,
  `quantity_received` INT NOT NULL DEFAULT 0,
  `unit_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STREAMING_CHUNK:Creating stock requisitions workflow tables...
-- 8. ตารางใบขอเบิกสินค้า (requisitions)
CREATE TABLE IF NOT EXISTS `requisitions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `req_number` VARCHAR(50) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'pending',
  `note` TEXT NULL,
  `approved_by` INT NULL,
  `approved_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. ตารางรายการสินค้าใบขอเบิก (requisition_items)
CREATE TABLE IF NOT EXISTS `requisition_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `req_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  FOREIGN KEY (`req_id`) REFERENCES `requisitions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STREAMING_CHUNK:Creating audit logs and shift attendance tables...
-- 10. ตารางประวัติการเคลื่อนไหวสต็อก (stock_movements)
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `type` ENUM('IN', 'OUT', 'ADJUST', 'DAMAGED') NOT NULL,
  `quantity` INT NOT NULL,
  `note` TEXT NULL,
  `user_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. ตารางบันทึกการเข้าเวร-ออกเวรพนักงาน (duty_shifts)
CREATE TABLE IF NOT EXISTS `duty_shifts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `shift_type` VARCHAR(100) NOT NULL DEFAULT 'กะปกติ (08:00 - 17:00)',
  `clock_in` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `clock_out` TIMESTAMP NULL DEFAULT NULL,
  `note` TEXT NULL,
  `status` ENUM('active', 'completed') NOT NULL DEFAULT 'active',
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STREAMING_CHUNK:Inserting default seeds data...
-- ข้อมูลหมวดหมู่เริ่มต้น
INSERT INTO `categories` (`id`, `name`) VALUES
(1, 'อุปกรณ์ไอที'),
(2, 'เครื่องใช้ไฟฟ้า'),
(3, 'เครื่องเขียนและอุปกรณ์สำนักงาน')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- ข้อมูลคู่ค้า/ซัพพลายเออร์เริ่มต้น
INSERT INTO `suppliers` (`id`, `name`, `phone`, `email`, `address`) VALUES
(1, 'บจก. สยาม โลจิสติกส์ แอนด์ ซัพพลาย', '02-123-4567', 'contact@siamlogistics.co.th', '88/9 หมู่ 5 ถ.วิภาวดีรังสิต แขวงตลาดบางเขน เขตหลักสี่ กรุงเทพฯ 10210')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- ข้อมูล Admin เริ่มต้น (Username: admin / Password: adminpassword)
INSERT INTO `users` (`username`, `password`, `fullname`, `role`) 
VALUES ('admin', '001', 'ผู้ดูแลระบบสูงสุด', 'admin')
ON DUPLICATE KEY UPDATE `id`=`id`;