<?php
declare(strict_types=1);

// ── Load .env ─────────────────────────────────────────────────────────────────
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if ($k && !isset($_ENV[$k])) { $_ENV[$k] = $v; putenv("$k=$v"); }
    }
}

function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// ── Core constants ─────────────────────────────────────────────────────────────
define('APP_ROOT',  __DIR__);
define('APP_URL',   env('APP_URL',  'http://localhost:8000'));
define('APP_ENV',   env('APP_ENV',  'production'));
define('APP_DEBUG', filter_var(env('APP_DEBUG', APP_ENV !== 'production'), FILTER_VALIDATE_BOOLEAN));

// ── Error handling ─────────────────────────────────────────────────────────────
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

set_exception_handler(function (\Throwable $e): void {
    if (APP_DEBUG) {
        http_response_code(500);
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], JSON_PRETTY_PRINT);
    } else {
        error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        http_response_code(500);
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Internal server error.']);
    }
    exit;
});

// ── Database ───────────────────────────────────────────────────────────────────
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'elite2'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

// ── JWT ────────────────────────────────────────────────────────────────────────
define('JWT_SECRET',         env('JWT_SECRET',         'changeme'));
define('JWT_EXPIRY',         (int) env('JWT_EXPIRY',         900));
define('JWT_REFRESH_EXPIRY', (int) env('JWT_REFRESH_EXPIRY', 604800));

// ── Security guard: reject insecure default JWT secret ────────────────────────
if (JWT_SECRET === 'changeme' || strlen(JWT_SECRET) < 32) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'JWT_SECRET is not set or too short. Copy .env.example to .env and set a proper secret.']);
    exit;
}

// ── Uploads ────────────────────────────────────────────────────────────────────
define('UPLOAD_DIR',      APP_ROOT . '/public/uploads');
define('UPLOAD_MAX_SIZE', (int) env('UPLOAD_MAX_SIZE', 5 * 1024 * 1024));

date_default_timezone_set('UTC');
