<?php
/**
 * Autoloader PSR-4 simplifié pour PHP natif.
 * Charge automatiquement les classes depuis shared/, backend/, frontend/ et admin/.
 */

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    // Préfixes de namespace → répertoires racine
    $prefixes = [
        'Shared\\'   => dirname(__DIR__) . '/shared/',
        'Backend\\'  => dirname(__DIR__) . '/backend/app/',
        'Frontend\\' => dirname(__DIR__) . '/frontend/app/',
        'Admin\\'    => dirname(__DIR__) . '/admin/app/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Helpers globaux
require_once __DIR__ . '/helpers/functions.php';
