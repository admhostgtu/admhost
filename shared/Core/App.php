<?php
/**
 * Classe principale de l'application.
 * Accepte les routes sous forme de tableau ou Closure (legacy).
 *
 * Format tableau :
 * [
 *     ['GET', '/path', [Controller::class, 'method'], ['Middleware']], // middleware optionnel
 * ]
 */

declare(strict_types=1);

namespace Shared\Core;

class App
{
    private Router $router;
    private array $config;

    /**
     * @param array<string, mixed> $config
     * @param array<int, array>|(\Closure(): array)|(\Closure(Router): void) $routes
     */
    public function __construct(array $config, array|\Closure $routes)
    {
        $this->config = $config;
        $responseType = ($config['type'] ?? 'api') === 'web' ? 'html' : 'json';
        $this->router = new Router($responseType);

        $this->registerRoutes($routes);
    }

    /**
     * @param array<int, array>|(\Closure(): array)|(\Closure(Router): void) $routes
     */
    private function registerRoutes(array|\Closure $routes): void
    {
        if ($routes instanceof \Closure) {
            $ref = new \ReflectionFunction($routes);

            // Legacy : Closure(Router $router): void
            if ($ref->getNumberOfParameters() > 0) {
                $routes($this->router);
                return;
            }

            // Closure(): array
            $routes = $routes();
        }

        if (!is_array($routes)) {
            throw new \InvalidArgumentException('Routes must be an array');
        }

        $this->router->registerFromArray($routes);
    }

    public function run(): void
    {
        $request = Request::capture();
        $this->router->dispatch($request);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}
