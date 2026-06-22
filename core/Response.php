<?php
declare(strict_types=1);

namespace Core;

final class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function ok(mixed $data = null): never
    {
        self::json($data ?? ['success' => true], 200);
    }

    public static function created(mixed $data = null): never
    {
        self::json($data ?? ['success' => true], 201);
    }

    public static function error(string $message, int $status = 400): never
    {
        self::json(['error' => $message], $status);
    }

    public static function notFound(string $message = 'Not found.'): never
    {
        self::json(['error' => $message], 404);
    }

    public static function unauthorized(string $message = 'Unauthorized.'): never
    {
        self::json(['error' => $message], 401);
    }

    public static function forbidden(string $message = 'Forbidden.'): never
    {
        self::json(['error' => $message], 403);
    }

    public static function unprocessable(string $message): never
    {
        self::json(['error' => $message], 422);
    }
}
