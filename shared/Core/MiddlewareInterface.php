<?php
/**
 * Interface pour les middlewares HTTP.
 */

declare(strict_types=1);

namespace Shared\Core;

interface MiddlewareInterface
{
    /**
     * Traite la requête. Retourne true pour continuer, false pour stopper.
     */
    public function handle(Request $request): bool;
}
