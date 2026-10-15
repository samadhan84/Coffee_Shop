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

## Run it on GitHub (Codespaces) — no installation needed

1. On the repository page, click **Code → Codespaces → Create codespace on this branch**.
2. Wait 2–3 minutes while it builds. PHP and MySQL are installed and the sample database is imported automatically.
3. When the app is ready, a preview of it opens. If it doesn't, go to the **Ports** tab and open port **8080**.

To share the running app with someone, right-click port 8080 in the **Ports** tab and choose
**Port Visibility → Public**. The link only works while the codespace is running.

Every push also runs the **Test** workflow (the **Actions** tab). It starts the app on GitHub's servers,
opens every page and places a test order.

## Put it online (free InfinityFree hosting)

1. **Create the hosting account.** Sign up at [infinityfree.com](https://www.infinityfree.com), click
   **Create Account**, and pick a free subdomain (e.g. `mycoffeeshop.infinityfreeapp.com`). Note the
   account **password** shown in the account details.
2. **Create the database.** In the control panel open **MySQL Databases**, create a database
   (e.g. `coffeeshop`; its full name becomes `if0_XXXXXXXX_coffeeshop`). Note the **MySQL Hostname**
   (`sqlXXX.infinityfree.com`), **Database Name**, **Username** and **Password** listed there.
3. **Import the data.** Click **phpMyAdmin** next to the database → **Import** → choose
   `database/coffeeshop.sql` → **Go**. (Don't create the database in SQL; InfinityFree doesn't allow it,
   and this file doesn't try.)
4. **Add your database details.** Copy `config/config.local.example.php` to `config/config.local.php`
   and fill in the four values from step 2. Never commit this file; it's in `.gitignore`.
5. **Upload the files.** Open the **File Manager** (or connect with FileZilla using the FTP details in the
   control panel), go into **`htdocs`**, delete the default `index2.html`, and upload the project files so that
   `index.php` sits directly inside `htdocs`. You don't need `.git`, `.github`, `.devcontainer` or `README.md`.
   Remember to upload `config/config.local.php` too.
6. **Open your site** at your subdomain. New accounts can take a few minutes (sometimes up to a day for DNS)
   before the address works. Log in as admin and **change the demo passwords** under *User Accounts*.

The `config/`, `includes/` and `database/` folders include `.htaccess` files that block direct web access,
so your database details and SQL file can't be downloaded from the live site.

## Android app

The `android/` folder is a small Android app that opens the live site
(**https://gauravcoffeeshop.site.je/**) full screen, so the shop can be used from a phone like a normal app.

**Download:** [CoffeeShop.apk](https://github.com/samadhan84/Coffee_Shop/releases/download/android-v1.0/CoffeeShop.apk)
(from the [android-v1.0 release](https://github.com/samadhan84/Coffee_Shop/releases/tag/android-v1.0)).
Open it on the phone and allow **Install unknown apps** when Android asks. Needs Android 5.0 or newer.

- Logins stay signed in; the Back button goes to the previous page.
- Links to other sites open in the phone's browser; there's an offline screen with Retry.
- The receipt's Print button and CSV report downloads work inside the app.

The APK is built by the **Android APK** workflow on every change under `android/`. To change the website
address, edit `app_url` / `app_host` in `android/app/build.gradle` (and the host in `AndroidManifest.xml`).
The app is signed with a demo key kept in the repo — fine for sharing the APK, but use your own private key
for a Google Play release.

## Setup (XAMPP / WAMP)

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Copy this folder into `C:\xampp\htdocs\` (e.g. `C:\xampp\htdocs\coffeeshop`).
3. Open **phpMyAdmin** (`http://localhost/phpmyadmin`), click **New**, create a database named `coffeeshop`,
   then with it selected go to **Import** → choose `database/coffeeshop.sql` → **Go**. This loads the tables and sample data.
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
mysql -u root -p -e "CREATE DATABASE coffeeshop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p coffeeshop < database/coffeeshop.sql
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

## Mini Arduino IDE (Windows desktop app)

The `arduino-ide/` folder contains a separate project: a small Arduino IDE style Windows app for writing,
compiling and uploading code to Arduino Uno / Nano / Mega and ESP32 boards, with a serial monitor.
See [`arduino-ide/README.md`](arduino-ide/README.md). The Windows installer is built by the
**Arduino IDE (Windows)** GitHub Actions workflow.
