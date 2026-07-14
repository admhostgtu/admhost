<?php
/**
 * Gestion centralisée des erreurs — log + affichage conditionnel.
 * En production : jamais d'affichage debug, toujours log dans storage/logs.
 */

declare(strict_types=1);

namespace Shared\Core;

class ErrorHandler
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        $isProduction = env('APP_ENV', 'production') === 'production';
        $debug        = !$isProduction && filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);

        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('display_startup_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);

        self::$registered = true;
    }

    public static function handleException(\Throwable $e): void
    {
        self::log($e);

        if ($e instanceof ApiException) {
            $e->render();
        }

        self::render($e);
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        self::log(new \ErrorException($message, 0, $severity, $file, $line));
        return true;
    }

    public static function render(\Throwable $e, string $type = 'auto'): never
    {
        $isProduction = env('APP_ENV', 'production') === 'production';
        $debug        = !$isProduction && filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
        $message      = $debug ? $e->getMessage() : 'Une erreur est survenue.';

        if ($type === 'auto') {
            $type = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/') ? 'json' : 'html';
        }

        if ($type === 'json') {
            Response::json(['error' => $message], 500)->send();
        }

        Response::html(
            '<!DOCTYPE html><html><head><title>Erreur</title></head><body><h1>Erreur</h1><p>'
            . htmlspecialchars($message) . '</p></body></html>',
            500
        )->send();
    }

    public static function log(\Throwable $e): void
    {
        $logDir = app_root() . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $line = sprintf(
            "[%s] %s: %s in %s:%d\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        @file_put_contents($logDir . '/app.log', $line, FILE_APPEND | LOCK_EX);
    }
}
