<?php
/**
 * Routes site vitrine — admhost.fr (pages publiques marketing).
 */

declare(strict_types=1);

use Frontend\Controllers\HomeController;

return [
    ['GET', '/',        [HomeController::class, 'index']],
    ['GET', '/pricing', [HomeController::class, 'pricing']],
];
