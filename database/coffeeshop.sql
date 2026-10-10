-- ------------------------------------------------------------
-- Coffee Shop Management System - MySQL schema + sample data
-- 1. Create an empty database (e.g. "coffeeshop", or the one your host gives you)
-- 2. Select it in phpMyAdmin -> Import -> choose this file
--    or:  mysql -u root -p coffeeshop < database/coffeeshop.sql
-- ------------------------------------------------------------

SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS stock_movements, order_items, orders, menu_item_ingredients, ingredients,
    menu_items, categories, attendance, employees, customers, users, settings;
SET FOREIGN_KEY_CHECKS = 1;

-- Login accounts. Role decides what a user can access.
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    phone         VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','staff','customer') NOT NULL DEFAULT 'customer',
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Customers (walk-in customers have no user account).
CREATE TABLE customers (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NULL UNIQUE,
    name           VARCHAR(100) NOT NULL,
    phone          VARCHAR(20),
    email          VARCHAR(150),
    loyalty_points INT NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE employees (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    phone       VARCHAR(20),
    email       VARCHAR(150),
    position    VARCHAR(50) NOT NULL,
    salary      DECIMAL(10,2) NOT NULL DEFAULT 0,
    shift_start TIME,
    shift_end   TIME,
    join_date   DATE,
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Working hours per employee per day.
CREATE TABLE attendance (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    employee_id  INT NOT NULL,
    work_date    DATE NOT NULL,
    hours_worked DECIMAL(4,2) NOT NULL,
    notes        VARCHAR(255),
    UNIQUE KEY uq_emp_date (employee_id, work_date),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE categories (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE menu_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    category_id  INT NOT NULL,
    name         VARCHAR(100) NOT NULL,
    description  VARCHAR(255),
    price        DECIMAL(10,2) NOT NULL,
    icon         VARCHAR(16) NOT NULL DEFAULT '☕',
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    is_featured  TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

-- Raw materials: coffee beans, milk, sugar, syrups, snacks...
CREATE TABLE ingredients (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL UNIQUE,
    unit          VARCHAR(20) NOT NULL,
    quantity      DECIMAL(12,3) NOT NULL DEFAULT 0,
    reorder_level DECIMAL(12,3) NOT NULL DEFAULT 0,
    cost_per_unit DECIMAL(10,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- Recipe: how much of each ingredient one menu item uses.
CREATE TABLE menu_item_ingredients (
    menu_item_id  INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity_used DECIMAL(12,3) NOT NULL,
    PRIMARY KEY (menu_item_id, ingredient_id),
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    order_no         VARCHAR(30) UNIQUE,
    customer_id      INT NULL,
    order_type       ENUM('dine_in','takeaway','online') NOT NULL DEFAULT 'dine_in',
    table_no         VARCHAR(10),
    status           ENUM('pending','preparing','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
    subtotal         DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax_rate         DECIMAL(5,2) NOT NULL DEFAULT 0,
    tax_amount       DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    discount_amount  DECIMAL(10,2) NOT NULL DEFAULT 0,
    extra_charges    DECIMAL(10,2) NOT NULL DEFAULT 0,
    total            DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_method   ENUM('cash','card','upi') NULL,
    payment_status   ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
    notes            VARCHAR(255),
    rating           TINYINT NULL,
    created_by       INT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_created (created_at),
    INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    order_id     INT NOT NULL,
    menu_item_id INT NULL,
    item_name    VARCHAR(100) NOT NULL,
    unit_price   DECIMAL(10,2) NOT NULL,
    quantity     INT NOT NULL,
    line_total   DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Every change to stock (sale, restock, cancel, adjustment).
CREATE TABLE stock_movements (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    ingredient_id INT NOT NULL,
    change_qty    DECIMAL(12,3) NOT NULL,
    reason        VARCHAR(100) NOT NULL,
    created_by    INT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE settings (
    name  VARCHAR(50) PRIMARY KEY,
    value VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Sample data
-- Logins:  admin@coffeeshop.com / admin123
--          staff@coffeeshop.com / staff123
--          raj123@gmail.com     / customer123
-- ------------------------------------------------------------

INSERT INTO settings (name, value) VALUES
('shop_name', 'Coffee Shop'),
('tagline', 'One sip changes everything.'),
('tax_rate', '5'),
('currency', '₹'),
('address', 'Navi Peth, Jalgaon, Maharashtra'),
('phone', '+91 98765 43210');

INSERT INTO users (id, name, email, phone, password_hash, role) VALUES
(1, 'Shop Admin',      'admin@coffeeshop.com', '9876543210', '$2y$10$0KuqpBpG24z5cURB6.sEs.rQWJqGOLAqO8hp6NtFUXNwSgtL3/HyS', 'admin'),
(2, 'Dhanashri Patil', 'staff@coffeeshop.com', '9876500001', '$2y$10$fbBtRC3VJd0Vv.1bZfR6WOM/zPlHxq0m5fLKIQc3cuHN13.4nbWnq', 'staff'),
(3, 'Raj Sharma',      'raj123@gmail.com',     '9876500002', '$2y$10$5489Y0VgC0HWrJXZ4DTf9ecMsKzNRHRMFaP/KeUOtSaeaCju0cW26', 'customer');

INSERT INTO customers (id, user_id, name, phone, email, loyalty_points) VALUES
(1, 3,    'Raj Sharma',   '9876500002', 'raj123@gmail.com', 12),
(2, NULL, 'Priya Joshi',  '9822000011', NULL, 5),
(3, NULL, 'Amit Chaudhari', '9822000012', NULL, 0);

INSERT INTO employees (id, user_id, name, phone, email, position, salary, shift_start, shift_end, join_date) VALUES
(1, 1,    'Shop Admin',      '9876543210', 'admin@coffeeshop.com', 'Manager', 35000, '09:00', '18:00', '2024-01-10'),
(2, 2,    'Dhanashri Patil', '9876500001', 'staff@coffeeshop.com', 'Cashier', 18000, '08:00', '16:00', '2024-06-01'),
(3, NULL, 'Rohit Mahajan',   '9876500003', NULL,                   'Barista', 16000, '12:00', '21:00', '2025-02-15');

INSERT INTO categories (id, name) VALUES
(1, 'Hot Coffee'), (2, 'Cold Coffee'), (3, 'Tea & Others'), (4, 'Snacks'), (5, 'Coffee Beans');

INSERT INTO menu_items (id, category_id, name, description, price, icon, is_featured) VALUES
(1,  1, 'Espresso',          'Rich, concentrated single shot.',              90,  '☕', 0),
(2,  1, 'Cappuccino',        'Espresso with steamed milk and thick foam.',   140, '☕', 1),
(3,  1, 'Cafe Latte',        'Smooth espresso with lots of steamed milk.',   150, '🥛', 1),
(4,  1, 'Caramel Macchiato', 'Vanilla, milk, espresso and caramel drizzle.', 180, '🍮', 0),
(5,  2, 'Cold Coffee',       'Chilled blended coffee with milk and sugar.',  130, '🧋', 1),
(6,  2, 'Iced Americano',    'Espresso over ice and cold water.',            120, '🧊', 0),
(7,  2, 'Hazelnut Frappe',   'Blended ice coffee with hazelnut syrup.',      190, '🥤', 0),
(8,  3, 'Masala Chai',       'Spiced Indian milk tea.',                      60,  '🍵', 0),
(9,  3, 'Hot Chocolate',     'Creamy cocoa with steamed milk.',              150, '🍫', 0),
(10, 4, 'Chocolate Muffin',  'Freshly baked, soft and moist.',               80,  '🧁', 0),
(11, 4, 'Veg Sandwich',      'Grilled sandwich with fresh vegetables.',      110, '🥪', 0),
(12, 4, 'Butter Croissant',  'Flaky, buttery French pastry.',                90,  '🥐', 0),
(13, 5, 'Light Roast',       'Bright citrus notes with floral aromas. Perfect for pour-over.', 299, '☕', 0),
(14, 5, 'Medium Roast',      'Balanced chocolate & caramel. Our bestseller.',                  349, '☕', 0),
(15, 5, 'Dark Roast',        'Bold, smoky with deep chocolate. Perfect for espresso lovers.',  399, '☕', 0);

INSERT INTO ingredients (id, name, unit, quantity, reorder_level, cost_per_unit) VALUES
(1, 'Coffee Beans',      'g',   5000, 1000, 1.20),
(2, 'Milk',              'ml', 20000, 5000, 0.06),
(3, 'Sugar',             'g',   3000,  500, 0.05),
(4, 'Caramel Syrup',     'ml',   800,  300, 0.40),
(5, 'Hazelnut Syrup',    'ml',   250,  300, 0.45),
(6, 'Tea Leaves',        'g',   1000,  200, 0.50),
(7, 'Cocoa Powder',      'g',    900,  200, 0.80),
(8, 'Chocolate Muffin',  'pcs',   20,    5, 30.00),
(9, 'Bread Slices',      'pcs',   40,   10, 4.00),
(10,'Butter Croissant',  'pcs',    4,    5, 35.00),
(11,'Packed Beans 1kg - Light',  'pcs', 10, 2, 180.00),
(12,'Packed Beans 1kg - Medium', 'pcs', 10, 2, 210.00),
(13,'Packed Beans 1kg - Dark',   'pcs',  8, 2, 240.00);

INSERT INTO menu_item_ingredients (menu_item_id, ingredient_id, quantity_used) VALUES
(1, 1, 18),
(2, 1, 18), (2, 2, 150), (2, 3, 10),
(3, 1, 18), (3, 2, 200), (3, 3, 10),
(4, 1, 18), (4, 2, 180), (4, 4, 20),
(5, 1, 18), (5, 2, 200), (5, 3, 20),
(6, 1, 18),
(7, 1, 18), (7, 2, 150), (7, 5, 25), (7, 3, 10),
(8, 6, 5),  (8, 2, 100), (8, 3, 10),
(9, 7, 25), (9, 2, 200), (9, 3, 10),
(10, 8, 1),
(11, 9, 2),
(12, 10, 1),
(13, 11, 1), (14, 12, 1), (15, 13, 1);

INSERT INTO orders (id, order_no, customer_id, order_type, table_no, status, subtotal, tax_rate, tax_amount,
                    discount_percent, discount_amount, extra_charges, total, payment_method, payment_status, rating, created_by, created_at) VALUES
(1, 'ORD-0001', 1,    'dine_in',  'T2', 'completed', 290, 5, 14.50, 0,  0,     0,  304.50, 'upi',  'paid', 5, 2, NOW() - INTERVAL 6 DAY),
(2, 'ORD-0002', 2,    'takeaway', NULL, 'completed', 260, 5, 13.00, 10, 26.00, 0,  247.00, 'cash', 'paid', NULL, 2, NOW() - INTERVAL 3 DAY),
(3, 'ORD-0003', NULL, 'dine_in',  'T5', 'completed', 310, 5, 15.50, 0,  0,     0,  325.50, 'card', 'paid', NULL, 2, NOW() - INTERVAL 1 DAY),
(4, 'ORD-0004', 1,    'online',   NULL, 'preparing', 150, 5,  7.50, 0,  0,    20,  177.50, 'upi',  'unpaid', NULL, 3, NOW());

INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity, line_total) VALUES
(1, 2,  'Cappuccino',       140, 1, 140), (1, 3, 'Cafe Latte', 150, 1, 150),
(2, 5,  'Cold Coffee',      130, 2, 260),
(3, 8,  'Masala Chai',       60, 2, 120), (3, 11, 'Veg Sandwich', 110, 1, 110), (3, 10, 'Chocolate Muffin', 80, 1, 80),
(4, 3,  'Cafe Latte',       150, 1, 150);

INSERT INTO attendance (employee_id, work_date, hours_worked, notes) VALUES
(2, CURDATE() - INTERVAL 1 DAY, 8, NULL),
(3, CURDATE() - INTERVAL 1 DAY, 9, 'Evening rush'),
(2, CURDATE(), 4, 'Shift in progress');
