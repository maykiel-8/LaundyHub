-- LaundryHub Database Setup v2
-- Run this in phpMyAdmin or MySQL CLI

CREATE DATABASE IF NOT EXISTS laundry_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE laundry_system;

-- ─── Users ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    fullname    VARCHAR(100) NOT NULL,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('admin','cashier','operator','delivery') DEFAULT 'cashier',
    contact     VARCHAR(20),
    is_active   TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Default admin (password: admin123)
INSERT IGNORE INTO users (fullname, username, password, role)
VALUES ('Administrator', 'admin', MD5('admin123'), 'admin');

-- Sample delivery staff
INSERT IGNORE INTO users (fullname, username, password, role, contact)
VALUES
('Juan Rider',  'rider1', MD5('rider123'), 'delivery', '09111111111'),
('Pedro Rider', 'rider2', MD5('rider123'), 'delivery', '09222222222');

-- ─── Customers ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS customers (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    fullname       VARCHAR(100) NOT NULL,
    contact_number VARCHAR(20)  NOT NULL,
    email          VARCHAR(100),
    address        TEXT,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ─── Services ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS services (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL,
    description  TEXT,
    pricing_type ENUM('per_kg','per_item','flat') DEFAULT 'per_kg',
    price        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO services (service_name, description, pricing_type, price) VALUES
('Regular Wash',     'Standard washing and spin dry',       'per_kg',   35.00),
('Wash & Dry',       'Washing with machine drying',         'per_kg',   55.00),
('Wash, Dry & Fold', 'Full service laundry',                'per_kg',   75.00),
('Dry Clean',        'Dry cleaning for delicate items',     'per_item', 150.00),
('Bedsheet/Blanket', 'Bulky item flat rate',                'flat',     120.00);

-- ─── Orders (updated: service_type + delivery fields) ────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    reference_no    VARCHAR(20)  NOT NULL UNIQUE,
    customer_id     INT          NOT NULL,
    service_id      INT          NOT NULL,
    quantity        DECIMAL(8,2) DEFAULT 1,
    surcharge       DECIMAL(10,2) DEFAULT 0.00,
    total_amount    DECIMAL(10,2) DEFAULT 0.00,
    pickup_date     DATE,
    notes           TEXT,
    -- Service type: walk_in | pickup | delivery
    service_type    ENUM('walk_in','pickup','delivery') DEFAULT 'walk_in',
    delivery_fee    DECIMAL(10,2) DEFAULT 0.00,
    order_status    ENUM('Received','Processing','Finishing','Ready','Released') DEFAULT 'Received',
    payment_status  ENUM('Unpaid','Paid') DEFAULT 'Unpaid',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (service_id)  REFERENCES services(id)
);

-- ─── Payments (Cash only) ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS payments (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    order_id       INT NOT NULL,
    amount_paid    DECIMAL(10,2) NOT NULL,
    change_amount  DECIMAL(10,2) DEFAULT 0.00,
    paid_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    received_by    INT,   -- FK to users (cashier)
    FOREIGN KEY (order_id)    REFERENCES orders(id),
    FOREIGN KEY (received_by) REFERENCES users(id)
);

-- ─── Delivery / Pickup Requests ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS deliveries (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    order_id        INT NOT NULL UNIQUE,
    service_type    ENUM('pickup','delivery') NOT NULL,
    -- Address for pickup origin or delivery destination
    delivery_address TEXT NOT NULL,
    -- Assigned delivery staff
    assigned_to     INT,   -- FK to users (role=delivery)
    -- Pickup schedule
    scheduled_at    DATETIME,
    -- Delivery status tracking
    delivery_status ENUM(
        'Pending',
        'Assigned',
        'Out for Pickup',
        'Picked Up',
        'Out for Delivery',
        'Delivered',
        'Failed'
    ) DEFAULT 'Pending',
    delivery_fee    DECIMAL(10,2) DEFAULT 50.00,
    notes           TEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)   REFERENCES orders(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- ─── Delivery Status Log ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS delivery_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    delivery_id  INT NOT NULL,
    status       VARCHAR(50),
    remarks      TEXT,
    updated_by   INT,
    logged_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id),
    FOREIGN KEY (updated_by)  REFERENCES users(id)
);

-- ─── Inventory ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inventory (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    item_name     VARCHAR(100) NOT NULL,
    unit          VARCHAR(20)  DEFAULT 'kg',
    quantity      DECIMAL(10,2) DEFAULT 0.00,
    reorder_level DECIMAL(10,2) DEFAULT 5.00,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO inventory (item_name, unit, quantity, reorder_level) VALUES
('Ariel Detergent',          'kg',     25.00, 5.00),
('Downy Fabric Conditioner', 'liters', 10.00, 2.00),
('Zonrox Bleach',            'liters',  5.00, 1.00),
('Plastic Bags (small)',     'pcs',   200.00, 50.00),
('Plastic Bags (large)',     'pcs',   100.00, 30.00);


-- ─── Customer Portal Accounts (DISABLED - Customer access removed) ──────────────────────────

-- Create customer_accounts table with is_active defaulting to 1
-- CREATE TABLE IF NOT EXISTS customer_accounts (
--     id             INT AUTO_INCREMENT PRIMARY KEY,
--     customer_id    INT NOT NULL UNIQUE,
--     username       VARCHAR(50) NOT NULL UNIQUE,
--     password       VARCHAR(255) NOT NULL,
--     is_active      TINYINT(1) NOT NULL DEFAULT 1,
--     last_login     DATETIME DEFAULT NULL,
--     created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
-- );

-- Fix any existing rows where is_active is NULL (old bad inserts)
-- UPDATE customer_accounts SET is_active = 1 WHERE is_active IS NULL;

-- Confirm: should show all accounts with is_active = 1
-- SELECT id, customer_id, username, is_active, created_at FROM customer_accounts;

-- Add columns to orders table if not already present
ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS is_preorder  TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS service_type ENUM('walk_in','pickup','delivery') NOT NULL DEFAULT 'walk_in',
    ADD COLUMN IF NOT EXISTS delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00;