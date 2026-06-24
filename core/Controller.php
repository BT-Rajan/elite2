<?php
declare(strict_types=1);

namespace Core;

use PDO;

abstract class Controller
{
    protected PDO $db;
    protected ?object $auth = null; // decoded JWT payload

    public function __construct()
    {
        $this->db   = Database::getInstance();
        $this->auth = $this->resolveAuth();
    }

    // ── Request helpers ────────────────────────────────────────────────────────

    protected function body(): array
    {
        $raw = file_get_contents('php://input');
        return $raw ? (json_decode($raw, true) ?? []) : [];
    }

    protected function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    protected function require(array $body, array $fields): void
    {
        foreach ($fields as $f) {
            if (!isset($body[$f]) || (is_string($body[$f]) && trim($body[$f]) === '')) {
                Response::unprocessable("Field '$f' is required.");
            }
        }
    }

    // ── Response helpers ───────────────────────────────────────────────────────

    protected function ok(mixed $data = null): never      { Response::ok($data); }
    protected function created(mixed $data = null): never { Response::created($data); }
    protected function error(string $m, int $s): never { Response::error($m, $s); }
    protected function notFound(string $m = 'Not found.'): never { Response::notFound($m); }
    protected function forbidden(): never             { Response::forbidden(); }
    protected function unprocessable(string $m): never { Response::unprocessable($m); }

    // ── Auth helpers ───────────────────────────────────────────────────────────

    protected function userId(): int
    {
        return (int) ($this->auth->sub ?? 0);
    }

    protected function userRole(): string
    {
        return $this->auth->role ?? '';
    }

    protected function requireRole(string ...$roles): void
    {
        if (!in_array($this->userRole(), $roles, true)) {
            Response::forbidden('Insufficient role.');
        }
    }

    // ── DB helpers ─────────────────────────────────────────────────────────────

    protected function findById(string $table, int $id): array|false
    {
        $s = $this->db->prepare("SELECT * FROM $table WHERE id = ? LIMIT 1");
        $s->execute([$id]);
        return $s->fetch();
    }

    // ── Private ────────────────────────────────────────────────────────────────

    private function resolveAuth(): ?object
    {
        // Use token already decoded by AuthMiddleware to avoid double JWT decode
        if (isset($_REQUEST['_auth'])) return $_REQUEST['_auth'];
        $token = Auth::bearerToken();
        return $token ? Auth::verifyAccessToken($token) : null;
    }
}
