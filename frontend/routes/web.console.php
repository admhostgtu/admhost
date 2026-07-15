<?php
/**
 * Routes espace client — console.admhost.fr
 */

declare(strict_types=1);

use Frontend\Controllers\AuthController;
use Frontend\Controllers\DashboardController;
use Frontend\Controllers\HomeController;

return [
    ['GET',  '/',          [HomeController::class,      'consoleHome']],
    ['GET',  '/login',     [AuthController::class,      'showLogin']],
    ['POST', '/login',     [AuthController::class,      'login']],
    ['GET',  '/register',  [AuthController::class,      'showRegister']],
    ['POST', '/register',  [AuthController::class,      'register']],
    ['GET',  '/logout',    [AuthController::class,      'logout']],
    ['GET',  '/dashboard', [DashboardController::class, 'index']],
];
