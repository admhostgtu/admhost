<?php
/**
 * Bootstrap — Site vitrine admhost.fr
 * ~/admhost.fr/public_html/index.php
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2) . '/admhost');
define('FRONTEND_DIR', APP_ROOT . '/frontend');
define('APP_SITE', 'vitrine');

if (!is_dir(APP_ROOT . '/shared')) {
    http_response_code(500);
    exit('Configuration serveur incorrecte.');
}

require_once APP_ROOT . '/shared/autoload.php';

use Shared\Core\App;
use Shared\Core\View;
use Shared\Core\ErrorHandler;
use Shared\Core\SecurityHeaders;

ErrorHandler::register();
SecurityHeaders::send();

View::setBasePath(FRONTEND_DIR . '/templates');

$config = require FRONTEND_DIR . '/config/app.php';
$routes = require FRONTEND_DIR . '/routes/web.vitrine.php';

$app = new App($config, $routes);
$app->run();
