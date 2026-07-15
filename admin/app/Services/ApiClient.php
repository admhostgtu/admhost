<?php
/**
 * Client HTTP admin — communique avec l'API REST backend.
 */

declare(strict_types=1);

namespace Admin\Services;

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
        return $this->client->get($endpoint, $_SESSION['admin_token'] ?? null);
    }

    public function post(string $endpoint, array $data = []): array
    {
        return $this->client->post($endpoint, $data, $_SESSION['admin_token'] ?? null);
    }

    public function delete(string $endpoint): array
    {
        return $this->client->delete($endpoint, $_SESSION['admin_token'] ?? null);
    }
}
