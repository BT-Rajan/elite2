<?php
declare(strict_types=1);

namespace Core;

final class Router
{
    private array $routes = [];

    public function get(string $p, callable|array $h, array $mw = []): void    { $this->add('GET',    $p, $h, $mw); }
    public function post(string $p, callable|array $h, array $mw = []): void   { $this->add('POST',   $p, $h, $mw); }
    public function put(string $p, callable|array $h, array $mw = []): void    { $this->add('PUT',    $p, $h, $mw); }
    public function patch(string $p, callable|array $h, array $mw = []): void  { $this->add('PATCH',  $p, $h, $mw); }
    public function delete(string $p, callable|array $h, array $mw = []): void { $this->add('DELETE', $p, $h, $mw); }

    private function add(string $method, string $pattern, callable|array $handler, array $mw): void
    {
        $this->routes[] = [
            'method'  => $method,
            'regex'   => $this->compile($pattern),
            'handler' => $handler,
            'mw'      => $mw,
        ];
    }

    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $uri    = strtok($_SERVER['REQUEST_URI'], '?');

        // Strip sub-path up to and including /api
        if (($pos = strpos($uri, '/api/')) !== false) {
            $uri = substr($uri, $pos + 4);
        } elseif (str_ends_with($uri, '/api')) {
            $uri = '/';
        }
        $uri = '/' . trim($uri, '/');

        $this->cors();

        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;
            if (!preg_match($route['regex'], $uri, $m)) continue;

            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);

            foreach ($route['mw'] as $class) {
                (new $class())->handle();
            }

            $this->invoke($route['handler'], $params);
            return;
        }

        Response::notFound('Route not found.');
    }

    private function invoke(callable|array $handler, array $params): void
    {
        if (is_callable($handler)) { $handler($params); return; }
        [$class, $method] = $handler;
        (new $class())->$method($params);
    }

    private function compile(string $pattern): string
    {
        $r = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', rtrim($pattern, '/'));
        return '#^' . $r . '/?$#';
    }

    private function cors(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        header('Content-Type: application/json; charset=utf-8');
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }
}
