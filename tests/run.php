<?php
declare(strict_types=1);

/**
 * tests/run.php — suite de pruebas unitarias y vectores dorados (sin BD).
 *
 *   php tests/run.php
 *
 * No necesita servidor ni base de datos: prueba la biblioteca de cálculo pura
 * y la validación de NIF. La integración con BD y API vive en
 * tests/integration/api_flow.py y la concurrencia en tests/java/.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script sólo se ejecuta por CLI.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/src/autoload.php';
require BASE_PATH . '/tests/harness.php';

date_default_timezone_set('UTC');

echo "Taste Alchemy · pruebas unitarias y vectores dorados\n";

$files = glob(BASE_PATH . '/tests/unit/*Test.php');
sort($files);
foreach ($files as $file) {
    require $file;
    run_tests(basename($file, '.php'));
}

exit(test_summary());