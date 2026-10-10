# ☕ Coffee Shop Management System

A web-based system that automates the day-to-day work of a coffee shop: order taking, billing,
inventory, customer and employee records, menu management and sales reports.

Built with **HTML, CSS, JavaScript, PHP and MySQL**, as described in the T.Y.BCA internship report
*"Coffee Shop Management System"* (K.C.E. Society's Moolji Jaitha College, Jalgaon, 2026–27).

## Modules

| Report module | Where it lives | What it does |
|---|---|---|
| Order Management | `admin/pos.php`, `admin/orders.php`, `admin/order_view.php` | Dine-in / takeaway / online orders, table numbers, status tracking (pending → preparing → ready → completed / cancelled), editing open orders |
| Billing & Invoice | `includes/orders.php`, `receipt.php` | Automatic subtotal, discount %, GST/tax, extra charges; payment method & status; printable tax invoice |
| Inventory Management | `admin/inventory.php` | Ingredient stock (beans, milk, sugar, syrups, snacks), automatic deduction from recipes on every sale, low-stock alerts, restocking, stock-count adjustments, movement log |
| Customer Management | `admin/customers.php` | Customer details, order history, usual order, loyalty points (1 point per ₹100 paid) |
| Employee Management | `admin/employees.php` | Staff details, position, salary, shift times, daily working hours, orders handled, optional staff login |
| Menu Management | `admin/menu.php` | Add / edit / hide / delete items and prices, categories, recipes |
| Administration & Reports | `admin/index.php`, `admin/reports.php` | Dashboard, daily / weekly / monthly / custom sales reports, top-selling items, payment & order-type breakdown, ingredient usage, CSV export, print |
| Security | `includes/bootstrap.php`, `admin/users.php`, `admin/settings.php` | Login with hashed passwords, role-based access (admin / staff / customer), CSRF protection, prepared SQL statements, one-click SQL data backup |

Customers can also sign up on the website, browse the menu, add items to a cart, place online
orders, see their order history and rate completed orders.

### Roles

| Role | Access |
|---|---|
| **Admin** | Everything |
| **Staff** | Dashboard, POS, orders, inventory (restock), customers |
| **Customer** | Website menu, cart, own orders, profile |

## Setup (XAMPP / WAMP)

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Copy this folder into `C:\xampp\htdocs\` (e.g. `C:\xampp\htdocs\coffeeshop`).
3. Open **phpMyAdmin** (`http://localhost/phpmyadmin`) → **Import** → choose `database/coffeeshop.sql` → **Go**.
   This creates the `coffeeshop` database with sample data.
4. If your MySQL user/password is not `root` with an empty password, edit `config/config.php`.
5. Open `http://localhost/coffeeshop/`.

### Demo logins

| Role | Email | Password |
|---|---|---|
| Admin | `admin@coffeeshop.com` | `admin123` |
| Staff | `staff@coffeeshop.com` | `staff123` |
| Customer | `raj123@gmail.com` | `customer123` |

Change these passwords (Admin → User Accounts) before using the system with real data.

### Running without XAMPP

```bash
mysql -u root -p < database/coffeeshop.sql
DB_USER=root DB_PASS=yourpassword php -S localhost:8000
# open http://localhost:8000
```

Requires PHP 8.1+ with the `pdo_mysql` extension and MySQL 5.7+ / MariaDB 10.3+.

## Project structure

```
├── index.php            Home page: hero, signature roasts, favourites, about, footer
├── login.php / signup.php / logout.php
├── dashboard.php        Customer dashboard + menu
├── cart.php             Cart & checkout (online orders)
├── my_orders.php        Customer order history & ratings
├── profile.php          Customer profile & password
├── receipt.php          Printable invoice (staff: any order, customer: own orders)
├── admin/               Staff & admin panel
│   ├── index.php        Dashboard (today's sales, active orders, low stock, popular items)
│   ├── pos.php          Point of sale
│   ├── orders.php       Order list, filters, quick status change
│   ├── order_view.php   Order details, modify, payment, cancel
│   ├── menu.php         Menu, categories, recipes        (admin)
│   ├── inventory.php    Stock levels, restock, movements
│   ├── customers.php    Customer records & history
│   ├── employees.php    Employees, shifts, working hours  (admin)
│   ├── reports.php      Sales & inventory reports         (admin)
│   ├── users.php        Login accounts & roles            (admin)
│   └── settings.php     Shop settings, tax rate, backup   (admin)
├── includes/
│   ├── bootstrap.php    DB connection, sessions, auth, CSRF, helpers
│   ├── orders.php       Billing formula, order creation, stock deduction
│   ├── cart.php         Session cart
│   └── layout.php       Shared page layout
├── config/config.php    Database settings
├── database/coffeeshop.sql   Schema + sample data
└── assets/              CSS, JavaScript, images
```

## How billing is calculated

```
discount   = subtotal × discount% / 100
tax        = (subtotal − discount) × tax% / 100      (tax rate set in Settings, default 5%)
total      = subtotal − discount + tax + extra charges
```

Prices are always taken from the database on the server, so the bill cannot be altered from the browser.
