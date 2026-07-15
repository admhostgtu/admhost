<?php
/**
 * Routes espace client — console.admhost.fr
 */

declare(strict_types=1);

use Frontend\Controllers\AuthController;
use Frontend\Controllers\DashboardController;
use Frontend\Controllers\HomeController;
use Frontend\Controllers\AccountController;
use Frontend\Controllers\BillingController;
use Frontend\Controllers\CheckoutController;
use Frontend\Controllers\ServiceController;

return [
    ['GET',  '/',                    [HomeController::class,      'consoleHome']],
    ['GET',  '/login',               [AuthController::class,      'showLogin']],
    ['POST', '/login',               [AuthController::class,      'login']],
    ['GET',  '/register',            [AuthController::class,      'showRegister']],
    ['POST', '/register',           [AuthController::class,      'register']],
    ['GET',  '/logout',              [AuthController::class,      'logout']],

    ['GET',  '/dashboard',          [DashboardController::class, 'index']],
    ['GET',  '/services/{id}',      [ServiceController::class,   'show']],
    ['POST', '/services/{id}/config', [ServiceController::class, 'updateConfig']],

    ['GET',  '/billing',             [BillingController::class,   'index']],
    ['POST', '/billing/portal',      [BillingController::class,   'portal']],

    ['GET',  '/settings',            [AccountController::class, 'settings']],
    ['POST', '/settings/profile',   [AccountController::class, 'updateProfile']],
    ['POST', '/settings/password',  [AccountController::class, 'updatePassword']],

    ['GET',  '/pricing',             [HomeController::class,      'pricing']],
    ['POST', '/subscribe',          [CheckoutController::class,  'subscribe']],
];
