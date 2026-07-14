<?php
/**
 * Gestion des réponses HTTP (JSON pour l'API, HTML pour le frontend/admin).
 */

declare(strict_types=1);

namespace Shared\Core;

class Response
{
    public function __construct(
        private mixed $content,
        private int $status = 200,
        private array $headers = []
    ) {}

    /**
     * Crée une réponse JSON (utilisée par l'API REST).
     */
    public static function json(mixed $data, int $status = 200): self
    {
        return new self($data, $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    /**
     * Crée une réponse HTML (utilisée par frontend/admin).
     */
    public static function html(string $content, int $status = 200): self
    {
        return new self($content, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Envoie la réponse au client.
     */
    public function send(): never
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        if (is_array($this->content)) {
            echo json_encode($this->content, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            echo $this->content;
        }

        exit;
    }
}
