<?php
/**
 * Client HTTP frontend — communique avec l'API REST backend.
 */

declare(strict_types=1);

namespace Frontend\Services;

use Shared\Services\HttpApiClient;

class ApiClient
{
    private HttpApiClient $client;

    public function __construct()
    {
        $this->client = new HttpApiClient();
    }

    private function token(): ?string
    {
        return $_SESSION['token'] ?? null;
    }

    public function get(string $endpoint): array
    {
        return $this->client->get($endpoint, $this->token());
    }

    public function post(string $endpoint, array $data = []): array
    {
        return $this->client->post($endpoint, $data, $this->token());
    }

    public function put(string $endpoint, array $data = []): array
    {
        return $this->client->put($endpoint, $data, $this->token());
    }
}
