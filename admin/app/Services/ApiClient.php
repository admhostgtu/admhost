<?php
/**
 * Client HTTP admin — communique avec l'API REST backend.
 */

declare(strict_types=1);

namespace Admin\Services;

class ApiClient
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('API_URL', 'http://localhost/backend/public'), '/');
    }

    public function get(string $endpoint): array
    {
        return $this->request('GET', $endpoint);
    }

    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, $data);
    }

    public function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }

    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $headers = "Content-Type: application/json\r\n";

        if (!empty($_SESSION['admin_token'])) {
            $headers .= 'Authorization: Bearer ' . $_SESSION['admin_token'] . "\r\n";
        }

        $options = [
            'http' => [
                'method'  => $method,
                'header'  => $headers,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ];

        if (in_array($method, ['POST', 'PUT'], true) && !empty($data)) {
            $options['http']['content'] = json_encode($data);
        }

        $context = stream_context_create($options);
        $result  = @file_get_contents($url, false, $context);

        if ($result === false) {
            return ['error' => 'Impossible de contacter l\'API'];
        }

        return json_decode($result, true) ?? ['error' => 'Réponse invalide'];
    }
}
