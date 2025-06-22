-- File: schema_migrations.sql
-- Description: Final SQL schema definitions for the ERP project.
-- This script can be run on an empty database to create all tables in their current state.

-- Create `clients` table
CREATE TABLE IF NOT EXISTS `clients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `contact_person` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(255) UNIQUE DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `vat_number` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create `purchase_orders` table
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `performa_invoice_id` INT NOT NULL,
  `exporter_id` INT NOT NULL,
  `manufacturer_id` INT NOT NULL,
  `po_number` VARCHAR(100) NOT NULL,
  `po_date` DATE NOT NULL,
  `number_of_containers` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY `unique_po_number` (`po_number`),
  UNIQUE KEY `unique_performa_invoice_id_for_po` (`performa_invoice_id`),

  CONSTRAINT `fk_po_performa_invoice`
    FOREIGN KEY (`performa_invoice_id`)
    REFERENCES `performa_invoices` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,

  CONSTRAINT `fk_po_exporter`
    FOREIGN KEY (`exporter_id`)
    REFERENCES `exporters` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,

  CONSTRAINT `fk_po_manufacturer`
    FOREIGN KEY (`manufacturer_id`)
    REFERENCES `manufacturers` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create `purchase_order_items` table
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_order_id` INT NOT NULL,
  `size_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `description` TEXT DEFAULT NULL,
  `weight_per_box` DECIMAL(10,2) DEFAULT NULL,
  `boxes` DECIMAL(10,2) NOT NULL,
  `thickness` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT `fk_po_item_purchase_order`
    FOREIGN KEY (`purchase_order_id`)
    REFERENCES `purchase_orders` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  CONSTRAINT `fk_po_item_size`
    FOREIGN KEY (`size_id`)
    REFERENCES `sizes` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,

  CONSTRAINT `fk_po_item_product`
    FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create `exporters` table
CREATE TABLE IF NOT EXISTS `exporters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_name` VARCHAR(255) NOT NULL,
  `person_name` VARCHAR(255) DEFAULT NULL,
  `contact_number` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `website` VARCHAR(255) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `gst_number` VARCHAR(15) DEFAULT NULL,
  `iec_code` VARCHAR(20) DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `state` VARCHAR(100) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT NULL,
  `pincode` VARCHAR(10) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_email` (`email`),
  UNIQUE KEY `unique_gst_number` (`gst_number`),
  UNIQUE KEY `unique_iec_code` (`iec_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create `performa_invoices` table
CREATE TABLE IF NOT EXISTS `performa_invoices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exporter_id` INT NOT NULL,
  `invoice_number` VARCHAR(100) NOT NULL,
  `invoice_date` DATE NOT NULL,
  `consignee_id` INT NOT NULL COMMENT 'References clients.id',
  `final_destination` VARCHAR(255) DEFAULT NULL,
  `total_container` VARCHAR(50) DEFAULT NULL,
  `container_size` VARCHAR(10) DEFAULT '20' COMMENT 'e.g., 20, 40',
  `currency_type` VARCHAR(5) DEFAULT 'USD' COMMENT 'e.g., USD, EUR, INR',
  `total_gross_weight_kg` DECIMAL(10,2) DEFAULT NULL,
  `bank_id` INT DEFAULT NULL,
  `freight_amount` DECIMAL(12,2) DEFAULT NULL,
  `discount_amount` DECIMAL(12,2) DEFAULT NULL,
  `notify_party_line1` TEXT DEFAULT NULL,
  `notify_party_line2` TEXT DEFAULT NULL,
  `terms_delivery_payment` TEXT DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY `unique_invoice_number` (`invoice_number`),

  CONSTRAINT `fk_pi_exporter`
    FOREIGN KEY (`exporter_id`)
    REFERENCES `exporters` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,

  CONSTRAINT `fk_pi_consignee`
    FOREIGN KEY (`consignee_id`)
    REFERENCES `clients` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,

  CONSTRAINT `fk_pi_bank`
    FOREIGN KEY (`bank_id`)
    REFERENCES `banks` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create `performa_invoice_items` table
CREATE TABLE IF NOT EXISTS `performa_invoice_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `performa_invoice_id` INT NOT NULL COMMENT 'FK to performa_invoices.id',
  `size_id` INT NOT NULL COMMENT 'FK to sizes.id',
  `product_id` INT NOT NULL COMMENT 'FK to products.id',
  `boxes` DECIMAL(10,2) DEFAULT NULL COMMENT 'Number of boxes',
  `rate_per_sqm` DECIMAL(10,2) NOT NULL COMMENT 'Agreed rate per SQM for this item',
  `commission_percentage` DECIMAL(5,2) DEFAULT NULL COMMENT 'Commission percentage, e.g., 5.00 for 5%',
  `quantity_sqm` DECIMAL(10,4) NOT NULL COMMENT 'Calculated: boxes * size.sqm_per_box',
  `amount` DECIMAL(12,2) NOT NULL COMMENT 'Calculated: quantity_sqm * rate_per_sqm',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT `fk_pi_item_invoice`
    FOREIGN KEY (`performa_invoice_id`)
    REFERENCES `performa_invoices` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  CONSTRAINT `fk_pi_item_size`
    FOREIGN KEY (`size_id`)
    REFERENCES `sizes` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,

  CONSTRAINT `fk_pi_item_product`
    FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create `manufacturers` table (Final Schema)
CREATE TABLE IF NOT EXISTS `manufacturers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `contact_person` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(255) UNIQUE DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `gst_number` VARCHAR(15) DEFAULT NULL,
  `stuffing_number` VARCHAR(255) DEFAULT NULL,
  `examination_date` DATE DEFAULT NULL,
  `pincode` VARCHAR(10) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create `suppliers` table (Final Schema)
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `contact_person` VARCHAR(255) DEFAULT NULL,
  `gst_number` VARCHAR(15) DEFAULT NULL, -- email was removed
  `phone` VARCHAR(50) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `product_category` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create `sizes` table (Final Schema)
CREATE TABLE IF NOT EXISTS `sizes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `size_text` VARCHAR(100) NOT NULL COMMENT 'User input part of the size, e.g., 60x60 CM',
  `size_prefix` VARCHAR(255) NOT NULL DEFAULT 'Porcelain Glazed Vitrified Tiles ( PGVT )' COMMENT 'Fixed prefix for the size description',
  `sqm_per_box` DECIMAL(10, 4) DEFAULT NULL COMMENT 'Square Meters per Box, e.g., 1.4400',
  `box_weight` DECIMAL(10, 2) DEFAULT NULL COMMENT 'Weight per Box in KG, e.g., 25.50',
  `purchase_price` DECIMAL(10, 2) DEFAULT NULL COMMENT 'Purchase price for one box or unit related to this size',
  `price_per_sqm` DECIMAL(10, 2) DEFAULT NULL COMMENT 'Selling Price per SQM',
  `hsn_code` VARCHAR(20) DEFAULT '69072100',
  `pallet_details` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create `products` table (Final Schema)
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `size_id` INT DEFAULT NULL,
  `design_name` VARCHAR(255) NOT NULL,
  `product_type` VARCHAR(255) NULL DEFAULT NULL,
  `box_weight_override` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Override for box weight from selected size',
  `purchase_price_override` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Override for purchase price from selected size',
  `price_per_sqm_override` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Override for price per SQM from selected size',
  `product_code` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Optional unique code for the product design variant',
  `description` TEXT NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_products_size_id` (`size_id`), -- Index for the foreign key
  CONSTRAINT `fk_products_sizes` -- Naming the constraint
    FOREIGN KEY (`size_id`)
    REFERENCES `sizes` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create `banks` table
CREATE TABLE IF NOT EXISTS `banks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bank_name` VARCHAR(255) NOT NULL,
  `account_number` VARCHAR(100) UNIQUE NOT NULL,
  `swift_code` VARCHAR(20) NULL DEFAULT NULL,
  `ifsc_code` VARCHAR(20) NULL DEFAULT NULL,
  `current_balance` DECIMAL(15,2) NULL DEFAULT 0.00 COMMENT 'Current balance, user input for now',
  `bank_address` TEXT NULL DEFAULT NULL COMMENT 'Full address of the bank branch',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
