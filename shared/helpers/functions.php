<?php
/**
 * Fonctions utilitaires globales partagées entre frontend, backend et admin.
 */

declare(strict_types=1);

/**
 * Racine du projet (compatible O2Switch : APP_ROOT défini dans le bootstrap).
 */
function app_root(): string
{
    return defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
}

/**
 * Charge une variable d'environnement depuis le fichier .env à la racine.
 */
function env(string $key, mixed $default = null): mixed
{
    static $loaded = false;
    static $vars = [];

    if (!$loaded) {
        $envFile = app_root() . '/.env';
        if (file_exists($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $vars[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
            }
        }
        $loaded = true;
    }

    return $vars[$key] ?? $default;
}

/**
 * Mot de passe BDD — supporte DB_PASS et DB_PASSWORD.
 */
function db_password(): string
{
    return (string) (env('DB_PASSWORD') ?? env('DB_PASS') ?? '');
}

/**
 * Utilisateur BDD avec fallback sécurisé (jamais root en production).
 */
function db_user(): string
{
    $user = env('DB_USER', 'admhost_user');
    $env  = env('APP_ENV', 'production');

    if ($env === 'production' && $user === 'root') {
        return 'admhost_user';
    }

    return (string) $user;
}

/**
 * Échappe une chaîne pour affichage HTML sécurisé.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirige vers une URL et termine l'exécution.
 */
function redirect(string $url, int $status = 302): never
{
    header('Location: ' . $url, true, $status);
    exit;
}

/**
 * Retourne l'URL de base de l'application.
 */
function base_url(string $path = ''): string
{
    $base = rtrim(env('APP_URL', 'http://localhost'), '/');
    return $base . '/' . ltrim($path, '/');
}
