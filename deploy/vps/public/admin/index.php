<?php
/**
 * Bootstrap admin — manage.console.admhost.fr (VPS Scaleway)
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));
define('ADMIN_DIR', APP_ROOT . '/admin');
define('APP_SITE', 'admin');

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

View::setBasePath(ADMIN_DIR . '/app/Views');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    session_start();
}

$config = require ADMIN_DIR . '/config/app.php';
$routes = require ADMIN_DIR . '/routes/subdomain.php';

$app = new App($config, $routes);
$app->run();
