<?php
// Loaded at the top of every page: config, DB, session, helpers.
require_once __DIR__ . '/../config/config.php';

ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

/** Shared PDO connection. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Keep MySQL's NOW()/CURDATE() in the same timezone as PHP (hosts often run on UTC).
            $pdo->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $e) {
            http_response_code(500);
            exit('<h2>Database connection failed</h2><p>Check config/config.php and make sure the '
                . 'database/coffeeshop.sql file has been imported.</p>');
        }
    }
    return $pdo;
}

/** URL of the project root, e.g. "/coffeeshop", so links work from sub-folders. */
function base_url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
        $doc  = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
        $base = ($doc !== '' && str_starts_with($root, $doc)) ? substr($root, strlen($doc)) : '';
        $base = rtrim($base, '/');
    }
    return $base . '/' . ltrim($path, '/');
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function setting(string $name, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = db()->query('SELECT name, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $cache[$name] ?? $default;
}

function money($amount): string
{
    return setting('currency', '₹') . number_format((float) $amount, 2);
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : base_url($path)));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function render_flash(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $html .= '<div class="alert alert-' . e($f['type']) . '">' . e($f['message']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

// ---------- CSRF protection ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Call at the start of every POST handler. */
function verify_csrf(): void
{
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Invalid form submission. Please go back and try again.');
    }
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function post(string $key, $default = '')
{
    return isset($_POST[$key]) ? (is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key]) : $default;
}

// ---------- Authentication & roles ----------
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $stmt = db()->prepare('SELECT id, name, email, phone, role FROM users WHERE id = ? AND is_active = 1');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
        }
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
    return $user;
}

/** Stops the request unless the logged-in user has one of the given roles. */
function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('<h2>Access denied</h2><p>You do not have permission to view this page.</p>'
            . '<p><a href="' . e(home_for($user)) . '">Go back</a></p>');
    }
    return $user;
}

function is_role(string ...$roles): bool
{
    $user = current_user();
    return $user && in_array($user['role'], $roles, true);
}

function home_for(array $user): string
{
    return base_url($user['role'] === 'customer' ? 'dashboard.php' : 'admin/index.php');
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $out = mb_substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : mb_substr($parts[0] ?? '', 1, 1));
    return mb_strtoupper($out);
}

function status_badge(string $status): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
}

/** Customer row linked to a user account (created on demand). */
function customer_for_user(array $user): array
{
    $stmt = db()->prepare('SELECT * FROM customers WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $customer = $stmt->fetch();
    if (!$customer) {
        db()->prepare('INSERT INTO customers (user_id, name, phone, email) VALUES (?, ?, ?, ?)')
            ->execute([$user['id'], $user['name'], $user['phone'], $user['email']]);
        return customer_for_user($user);
    }
    return $customer;
}
