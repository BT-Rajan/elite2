<?php
declare(strict_types=1);

namespace Middleware;

use Core\Auth;
use Core\Response;

/**
 * Usage: new RoleMiddleware('admin', 'head_coach')
 * Or via static factory: RoleMiddleware::only('admin')
 */
final class RoleMiddleware
{
    private array $roles;

    public function __construct(string ...$roles)
    {
        $this->roles = $roles;
    }

    public function handle(): void
    {
        $token = Auth::bearerToken();
        if (!$token) Response::unauthorized();

        $payload = Auth::verifyAccessToken($token);
        if (!$payload) Response::unauthorized();

        if (!in_array($payload->role, $this->roles, true)) {
            Response::forbidden('Insufficient permissions.');
        }
    }
}
