<?php
declare(strict_types=1);

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => $isHttps,
    'httponly' => true, 'samesite' => 'Lax'
]);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

function load_env(string $path): void {
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key); $value = trim($value);
        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value,-1) === '"') || ($value[0] === "'" && substr($value,-1) === "'"))) $value = substr($value,1,-1);
        $_ENV[$key] = $value;
    }
}
load_env(dirname(__DIR__) . '/.env');

function envv(string $key, string $default = ''): string { return (string)($_ENV[$key] ?? $default); }

$base = rtrim(envv('APP_URL', 'http://localhost/course-enrollment-system'), '/');
define('BASE_URL', $base);
define('APP_NAME', envv('APP_NAME', 'Course Enrollment System'));
define('ENROLLMENT_FEE', max(1, (int)envv('ENROLLMENT_FEE_KES', '1')));

try {
    $dsn = 'mysql:host=' . envv('DB_HOST','127.0.0.1') . ';port=' . envv('DB_PORT','3306') . ';dbname=' . envv('DB_NAME','course_enrollment') . ';charset=utf8mb4';
    $pdo = new PDO($dsn, envv('DB_USER','root'), envv('DB_PASS',''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Check your .env settings and confirm MySQL is running.');
}

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function redirect(string $path): never { header('Location: ' . BASE_URL . '/' . ltrim($path, '/')); exit; }
function flash(string $message, string $type = 'info'): void { $_SESSION['flash'] = ['message'=>$message,'type'=>$type]; }
function get_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_check(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Invalid form token. Refresh and try again.'); } }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): void { if (!current_user()) redirect('login.php'); }
function require_role(string $role): void { require_login(); if ((current_user()['role'] ?? '') !== $role) { http_response_code(403); exit('Access denied.'); } }
function is_placeholder(string $value): bool { return $value === '' || str_contains($value, 'REPLACE_WITH'); }
function money(int $amount): string { return 'KSh ' . number_format($amount, 2); }
