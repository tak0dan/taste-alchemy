<?php
declare(strict_types=1);

/**
 * Autoload PSR-4 mínimo (App\ -> src/) para poder arrancar sin Composer.
 * composer.json declara el mismo mapa, de modo que `composer dump-autoload`
 * produce un resultado equivalente.
 */
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = BASE_PATH . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/src/Support/helpers.php';
