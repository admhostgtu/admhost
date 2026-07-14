<?php
/**
 * Routeur HTTP — enregistrement par tableau ou méthodes fluides.
 */

declare(strict_types=1);

namespace Shared\Core;

class Router
{
    /** @var array<string, array<string, array{controller: string, action: string, middleware: string[]}>> */
    private array $routes = [];

    private string $responseType;

    public function __construct(string $responseType = 'json')
    {
        $this->responseType = $responseType;
    }

    /**
     * Enregistre des routes depuis un tableau déclaratif.
     *
     * @param array<int, array{0: string, 1: string, 2: array{0: class-string, 1: string}, 3?: array<class-string>}> $routes
     */
    public function registerFromArray(array $routes): void
    {
        foreach ($routes as $index => $route) {
            if (!is_array($route) || count($route) < 3) {
                throw new \InvalidArgumentException("Route invalide à l'index $index");
            }

            [$method, $uri, $handler] = $route;
            $middleware = $route[3] ?? [];

            if (!is_array($handler) || count($handler) !== 2) {
                throw new \InvalidArgumentException("Handler invalide à l'index $index");
            }

            [$controller, $action] = $handler;
            $this->addRoute(strtoupper($method), $uri, $controller, $action, $middleware);
        }
    }

    public function get(string $uri, string $controller, string $action, array $middleware = []): self
    {
        return $this->addRoute('GET', $uri, $controller, $action, $middleware);
    }

    public function post(string $uri, string $controller, string $action, array $middleware = []): self
    {
        return $this->addRoute('POST', $uri, $controller, $action, $middleware);
    }

    public function put(string $uri, string $controller, string $action, array $middleware = []): self
    {
        return $this->addRoute('PUT', $uri, $controller, $action, $middleware);
    }

    public function delete(string $uri, string $controller, string $action, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $uri, $controller, $action, $middleware);
    }

    private function addRoute(string $method, string $uri, string $controller, string $action, array $middleware): self
    {
        $this->routes[$method][$this->normalizeUri($uri)] = [
            'controller' => $controller,
            'action'     => $action,
            'middleware' => $middleware,
        ];
        return $this;
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $uri    = $this->normalizeUri($request->uri());

        foreach ($this->routes[$method] ?? [] as $routeUri => $route) {
            $params = $this->matchRoute($routeUri, $uri);
            if ($params === null) {
                continue;
            }

            foreach ($route['middleware'] as $middlewareClass) {
                if (!class_exists($middlewareClass)) {
                    $this->notFoundOrError('Middleware introuvable', 500);
                    return;
                }
                $middleware = new $middlewareClass();
                if (!$middleware->handle($request)) {
                    return;
                }
            }

            $controllerClass = $route['controller'];
            $action          = $route['action'];

            if (!class_exists($controllerClass)) {
                $this->notFoundOrError('Contrôleur introuvable', 500);
                return;
            }

            $controller = new $controllerClass();
            if (!method_exists($controller, $action)) {
                $this->notFoundOrError('Action introuvable', 500);
                return;
            }

            $controller->{$action}($request, ...array_values($params));
            return;
        }

        $this->notFound($uri);
    }

    private function notFound(string $uri): never
    {
        if ($this->responseType === 'html') {
            Response::html(
                '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 — Page introuvable</h1><p>' . htmlspecialchars($uri) . '</p></body></html>',
                404
            )->send();
        }

        Response::json(['error' => 'Route non trouvée', 'path' => $uri], 404)->send();
    }

    private function notFoundOrError(string $message, int $status): void
    {
        if ($this->responseType === 'html') {
            Response::html("<h1>Erreur</h1><p>" . htmlspecialchars($message) . "</p>", $status)->send();
            return;
        }
        Response::json(['error' => $message], $status)->send();
    }

    private function normalizeUri(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /** @return array<string, string>|null */
    private function matchRoute(string $routeUri, string $requestUri): ?array
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $routeUri);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $requestUri, $matches)) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }
        return $params;
    }
}
