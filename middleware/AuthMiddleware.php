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
        $decoded = $token ? Auth::verifyAccessToken($token) : null;
        if (!$decoded) {
            Response::unauthorized('Valid access token required.');
        }
        // Share decoded payload so Controller::resolveAuth() skips re-decoding
        $_REQUEST['_auth'] = $decoded;
    }
}
