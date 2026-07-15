<?php
/**
 * Routes admin — manage.console.admhost.fr (sans préfixe /admin).
 */

declare(strict_types=1);

use Admin\Controllers\AuthController;
use Admin\Controllers\DashboardController;
use Admin\Controllers\UserController;
use Admin\Controllers\SubscriptionController;
use Admin\Controllers\SettingsController;
use Admin\Controllers\PlanController;
use Admin\Controllers\ServiceAdminController;

return [
    ['GET',  '/login',              [AuthController::class,         'showLogin']],
    ['POST', '/login',              [AuthController::class,         'login']],
    ['GET',  '/logout',             [AuthController::class,         'logout']],

    ['GET',  '/',                    [DashboardController::class,    'index']],
    ['GET',  '/dashboard',          [DashboardController::class,    'index']],

    ['GET',  '/users',              [UserController::class,         'index']],
    ['GET',  '/users/create',       [UserController::class,         'create']],
    ['POST', '/users/create',       [UserController::class,         'create']],
    ['GET',  '/users/{id}/assign',  [UserController::class,         'assignService']],
    ['POST', '/users/{id}/assign',  [UserController::class,         'assignService']],

    ['GET',  '/services',           [ServiceAdminController::class, 'index']],

    ['GET',  '/plans',              [PlanController::class,         'index']],
    ['GET',  '/plans/create',       [PlanController::class,         'create']],
    ['POST', '/plans/create',       [PlanController::class,         'create']],
    ['GET',  '/plans/{id}/edit',    [PlanController::class,         'edit']],
    ['POST', '/plans/{id}/edit',    [PlanController::class,         'edit']],

    ['GET',  '/subscriptions',      [SubscriptionController::class, 'index']],

    ['GET',  '/settings',           [SettingsController::class,     'index']],
    ['POST', '/settings',           [SettingsController::class,     'update']],
];
