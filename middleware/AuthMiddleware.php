<?php
declare(strict_types=1);

namespace Middleware;

use Core\Auth;
use Core\Response;

final class AuthMiddleware
{
    public function handle(): void
    {
        $token = Auth::bearerToken();
        if (!$token || !Auth::verifyAccessToken($token)) {
            Response::unauthorized('Valid access token required.');
        }
    }
}
