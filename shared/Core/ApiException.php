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
        private array $errors = []
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
        $payload = ['error' => $this->getMessage()];
        if (!empty($this->errors)) {
            $payload['errors'] = $this->errors;
        }
        Response::json($payload, $this->statusCode)->send();
    }
}
