<?php
/**
 * Gestionnaire d'exceptions API — réponses JSON standardisées.
 */

declare(strict_types=1);

namespace Shared\Core;

class ApiException extends \Exception
{
    public function __construct(
        string $message,
        private int $statusCode = 400,
        private array $errors = [],
        private bool $exposeMessage = true
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function render(): never
    {
        $payload = ['error' => $this->clientMessage()];
        if (!empty($this->errors)) {
            $payload['errors'] = $this->errors;
        }
        Response::json($payload, $this->statusCode)->send();
    }

    private function clientMessage(): string
    {
        if ($this->exposeMessage && (env('APP_DEBUG', false) || $this->statusCode < 500)) {
            return $this->getMessage();
        }

        return match ($this->statusCode) {
            401 => 'Non autorisé.',
            403 => 'Accès refusé.',
            404 => 'Ressource introuvable.',
            409 => 'Conflit — ressource déjà existante.',
            422 => 'Données invalides.',
            429 => 'Trop de requêtes. Réessayez plus tard.',
            default => 'Une erreur interne est survenue.',
        };
    }
}
