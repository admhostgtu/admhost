<?php
/**
 * Encapsule la requête HTTP entrante (méthode, URI, headers, body).
 */

declare(strict_types=1);

namespace Shared\Core;

class Request
{
    public function __construct(
        private string $method,
        private string $uri,
        private array $query,
        private array $body,
        private array $headers
    ) {}

    /**
     * Crée une instance à partir des superglobales PHP.
     */
    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';

        // Lecture du body JSON pour les requêtes API
        $rawBody = file_get_contents('php://input') ?: '';
        $jsonBody = json_decode($rawBody, true);
        $body = is_array($jsonBody) ? $jsonBody : $_POST;

        // Support method override (PUT/DELETE via _method)
        if ($method === 'POST' && isset($body['_method'])) {
            $method = strtoupper($body['_method']);
        }

        $headers = function_exists('getallheaders') ? getallheaders() : [];

        return new self($method, $uri, $_GET, $body, $headers);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->body;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[$key] ?? $this->headers[strtolower($key)] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization', '');
        if (preg_match('/Bearer\s+(\S+)/', $auth, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
