<?php
/**
 * Moteur de templates PHP natif (include avec extraction de variables).
 */

declare(strict_types=1);

namespace Shared\Core;

class View
{
    /** @var string Répertoire racine des templates */
    private static string $basePath = '';

    /**
     * Définit le répertoire de base des templates.
     */
    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, '/');
    }

    /**
     * Rend un template avec les données fournies.
     */
    public static function render(string $template, array $data = []): string
    {
        $file = self::$basePath . '/' . str_replace('.', '/', $template) . '.php';

        if (!file_exists($file)) {
            throw new \RuntimeException("Template introuvable : $file");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return ob_get_clean() ?: '';
    }

    /**
     * Rend un partial (fragment réutilisable).
     */
    public static function partial(string $partial, array $data = []): string
    {
        return self::render('partials/' . $partial, $data);
    }
}
