<?php
/**
 * Point d'entrée Admin — admin/public/index.php
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));

require_once APP_ROOT . '/shared/autoload.php';

use Shared\Core\App;
use Shared\Core\View;
use Shared\Core\ErrorHandler;
use Shared\Core\SecurityHeaders;

ErrorHandler::register();
SecurityHeaders::send();

View::setBasePath(dirname(__DIR__) . '/app/Views');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    session_start();
}

$config = require dirname(__DIR__) . '/config/app.php';
$routes = require dirname(__DIR__) . '/routes/admin.php';

$app = new App($config, $routes);
$app->run();
