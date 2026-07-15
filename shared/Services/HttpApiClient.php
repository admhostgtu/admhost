<?php
/**
 * Client HTTP serveur → API (frontend / admin).
 * Utilise API_INTERNAL_URL en priorité pour les appels locaux sur le VPS.
 */

declare(strict_types=1);

namespace Shared\Services;

class HttpApiClient
{
    private string $baseUrl;
    private ?string $hostHeader;

    public function __construct()
    {
        $publicUrl = rtrim((string) env('API_URL', 'http://localhost:8001'), '/');
        $internal  = trim((string) env('API_INTERNAL_URL', ''));

        if ($internal !== '') {
            $this->baseUrl     = rtrim($internal, '/');
            $this->hostHeader  = parse_url($publicUrl, PHP_URL_HOST) ?: null;
        } else {
            $this->baseUrl    = $publicUrl;
            $this->hostHeader = null;
        }
    }

    public function get(string $endpoint, ?string $bearerToken = null): array
    {
        return $this->request('GET', $endpoint, [], $bearerToken);
    }

    public function post(string $endpoint, array $data = [], ?string $bearerToken = null): array
    {
        return $this->request('POST', $endpoint, $data, $bearerToken);
    }

    public function delete(string $endpoint, ?string $bearerToken = null): array
    {
        return $this->request('DELETE', $endpoint, [], $bearerToken);
    }

    private function request(string $method, string $endpoint, array $data, ?string $bearerToken): array
    {
        $url = $this->baseUrl . $endpoint;

        $headers = "Content-Type: application/json\r\nAccept: application/json\r\n";

        if ($this->hostHeader) {
            $headers .= 'Host: ' . $this->hostHeader . "\r\n";
        }

        if ($bearerToken) {
            $headers .= 'Authorization: Bearer ' . $bearerToken . "\r\n";
        }

        $options = [
            'http' => [
                'method'        => $method,
                'header'        => $headers,
                'timeout'       => 15,
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

        $decoded = json_decode($result, true);
        if (!is_array($decoded)) {
            return ['error' => 'Réponse invalide de l\'API'];
        }

        if (isset($decoded['errors']) && is_array($decoded['errors']) && !isset($decoded['error'])) {
            $decoded['error'] = implode(' ', array_values($decoded['errors']));
        }

        return $decoded;
    }
}
