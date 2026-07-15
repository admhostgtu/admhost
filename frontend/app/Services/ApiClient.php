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

    public function get(string $endpoint): array
    {
        return $this->client->get($endpoint, $_SESSION['token'] ?? null);
    }

    public function post(string $endpoint, array $data = []): array
    {
        return $this->client->post($endpoint, $data, $_SESSION['token'] ?? null);
    }
}
