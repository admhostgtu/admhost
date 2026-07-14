<?php
/**
 * Routes API REST — format tableau compatible App.
 */

declare(strict_types=1);

use Backend\Middleware\AuthMiddleware;
use Backend\Middleware\AdminMiddleware;
use Backend\Controllers\HealthController;
use Backend\Controllers\AuthController;
use Backend\Controllers\ServiceController;
use Backend\Controllers\StripeController;
use Backend\Controllers\AdminController;

$auth  = [AuthMiddleware::class];
$admin = [AuthMiddleware::class, AdminMiddleware::class];

return [
    ['GET',  '/',                              [HealthController::class,  'index']],
    ['GET',  '/api/health',                    [HealthController::class,  'index']],

    ['POST', '/api/login',                     [AuthController::class,    'login']],
    ['POST', '/api/register',                  [AuthController::class,    'register']],
    ['POST', '/api/auth/login',                [AuthController::class,    'login']],
    ['POST', '/api/auth/register',             [AuthController::class,    'register']],

    ['POST', '/api/logout',                    [AuthController::class,    'logout'],       $auth],
    ['POST', '/api/auth/logout',               [AuthController::class,    'logout'],       $auth],
    ['POST', '/api/auth/refresh',              [AuthController::class,    'refresh']],
    ['GET',  '/api/me',                        [AuthController::class,    'me'],           $auth],

    ['GET',  '/api/services',                  [ServiceController::class, 'index'],        $auth],
    ['GET',  '/api/services/{id}',             [ServiceController::class, 'show'],         $auth],
    ['GET',  '/api/subscription',              [ServiceController::class, 'subscription'], $auth],

    ['GET',  '/api/stripe/plans',              [StripeController::class,  'plans']],
    ['POST', '/api/stripe/checkout',           [StripeController::class,  'checkout'],     $auth],
    ['POST', '/api/stripe/webhook',             [StripeController::class,  'webhook']],

    ['GET',  '/api/admin/users',                [AdminController::class,   'users'],        $admin],
    ['POST', '/api/admin/users',               [AdminController::class,   'createUser'],   $admin],
    ['POST', '/api/admin/users/{id}/services', [AdminController::class,   'assignService'], $admin],
    ['GET',  '/api/admin/subscriptions',       [AdminController::class,   'subscriptions'], $admin],
    ['GET',  '/api/admin/services',            [AdminController::class,   'services'],     $admin],
];
