<?php
/**
 * Routes web du Frontend — format tableau compatible App.
 *
 * [METHOD, URI, [Controller::class, 'action'], middleware?]
 */

declare(strict_types=1);

use Frontend\Controllers\HomeController;
use Frontend\Controllers\AuthController;
use Frontend\Controllers\DashboardController;

return [
    ['GET',  '/',          [HomeController::class,      'index']],
    ['GET',  '/pricing',   [HomeController::class,      'pricing']],
    ['GET',  '/login',     [AuthController::class,      'showLogin']],
    ['POST', '/login',     [AuthController::class,      'login']],
    ['GET',  '/register',  [AuthController::class,      'showRegister']],
    ['POST', '/register',  [AuthController::class,      'register']],
    ['GET',  '/logout',    [AuthController::class,      'logout']],
    ['GET',  '/dashboard', [DashboardController::class, 'index']],
];
