<?php
/**
 * Routes admin — format tableau compatible App.
 */

declare(strict_types=1);

use Admin\Controllers\AuthController;
use Admin\Controllers\DashboardController;
use Admin\Controllers\UserController;
use Admin\Controllers\SubscriptionController;
use Admin\Controllers\SettingsController;

return [
    ['GET',  '/admin/login',              [AuthController::class,         'showLogin']],
    ['POST', '/admin/login',              [AuthController::class,         'login']],
    ['GET',  '/admin/logout',             [AuthController::class,         'logout']],

    ['GET',  '/admin',                    [DashboardController::class,    'index']],
    ['GET',  '/admin/dashboard',          [DashboardController::class,    'index']],

    ['GET',  '/admin/users',              [UserController::class,         'index']],
    ['GET',  '/admin/users/create',       [UserController::class,         'create']],
    ['POST', '/admin/users/create',       [UserController::class,         'create']],
    ['GET',  '/admin/users/{id}/assign',  [UserController::class,         'assignService']],
    ['POST', '/admin/users/{id}/assign',  [UserController::class,         'assignService']],

    ['GET',  '/admin/subscriptions',      [SubscriptionController::class, 'index']],

    ['GET',  '/admin/settings',           [SettingsController::class,     'index']],
    ['POST', '/admin/settings',           [SettingsController::class,     'update']],
];
