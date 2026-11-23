<?php
declare(strict_types=1);

/**
 * bin/install.php — instala el esquema completo en la base de datos configurada.
 *
 *   php bin/install.php            aplica schema + views + triggers + procedures
 *   php bin/install.php --seed     instala y además carga los datos de demostración
 *
 * El splitter entiende DELIMITER, comillas y comentarios, de modo que los
 * procedimientos y disparadores se ejecutan tal cual están en database/*.sql.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script sólo se ejecuta por CLI.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/src/autoload.php';

use App\Core\Config;
use App\Core\Database;

Config::load();

$withSeed = in_array('--seed', $argv, true);

/**
 * Divide un fichero SQL en sentencias ejecutables por PDO.
 * - Respeta DELIMITER (cuerpos de procedimientos y disparadores).
 * - Ignora comentarios -- y /* *\/ y el contenido de cadenas/backticks.
 * @return array<int, string>
 */
function sql_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $delimiter = ';';
    $length = strlen($sql);
    $i = 0;
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;

    while ($i < $length) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($inLineComment) {
            if ($char === "\n") {
                $inLineComment = false;
                $buffer .= $char;
            }
            $i++;
            continue;
        }
        if ($inBlockComment) {
            if ($char === '*' && $next === '/') {
                $inBlockComment = false;
                $i += 2;
                continue;
            }
            $i++;
            continue;
        }
        if (!$inSingle && !$inDouble && !$inBacktick) {
            if ($char === '-' && $next === '-' && ($i + 2 >= $length || $sql[$i + 2] === ' ' || $sql[$i + 2] === "\t" || $sql[$i + 2] === "\n")) {
                $inLineComment = true;
                $i += 2;
                continue;
            }
            if ($char === '#') {
                $inLineComment = true;
                $i++;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $inBlockComment = true;
                $i += 2;
                continue;
            }
            // Directiva DELIMITER al principio de línea.
            if (($i === 0 || $sql[$i - 1] === "\n") && stripos(substr($sql, $i, 9), 'DELIMITER') === 0) {
                $end = strpos($sql, "\n", $i);
                $line = trim(substr($sql, $i, ($end === false ? $length : $end) - $i));
                $parts = preg_split('/\s+/', $line);
                $delimiter = $parts[1] ?? ';';
                $buffer = '';
                $i = $end === false ? $length : $end + 1;
                continue;
            }
            if (substr($sql, $i, strlen($delimiter)) === $delimiter) {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                $i += strlen($delimiter);
                continue;
            }
        }

        if ($char === "'" && !$inDouble && !$inBacktick) {
            if ($inSingle && $next === "'") {
                $buffer .= "''";
                $i += 2;
                continue;
            }
            $inSingle = !$inSingle;
        } elseif ($char === '"' && !$inSingle && !$inBacktick) {
            $inDouble = !$inDouble;
        } elseif ($char === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
        } elseif ($char === '\\' && ($inSingle || $inDouble) && $next !== '') {
            $buffer .= $char . $next;
            $i += 2;
            continue;
        }

        $buffer .= $char;
        $i++;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }
    return $statements;
}

function out(string $message): void
{
    echo $message, "\n";
}

out('Taste Alchemy · instalación');
out('DSN: ' . Config::get('DB_DSN'));

try {
    $pdo = Database::pdo();
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    fwrite(STDERR, "Comprueba que MariaDB está en marcha y que .env apunta a él.\n");
    exit(1);
}

$files = ['schema.sql', 'views.sql', 'triggers.sql', 'procedures.sql'];
$total = 0;
foreach ($files as $file) {
    $path = BASE_PATH . '/database/' . $file;
    $statements = sql_statements(file_get_contents($path));
    foreach ($statements as $statement) {
        $pdo->exec($statement);
        $total++;
    }
    out(sprintf('  %-16s %3d sentencias', $file, count($statements)));
}

$counts = [
    'tablas' => (int) Database::value("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"),
    'vistas' => (int) Database::value("SELECT COUNT(*) FROM information_schema.views WHERE table_schema = DATABASE()"),
    'disparadores' => (int) Database::value("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = DATABASE()"),
    'procedimientos' => (int) Database::value("SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema = DATABASE() AND routine_type = 'PROCEDURE'"),
];
out(sprintf('Resultado: %d tablas · %d vistas · %d disparadores · %d procedimientos (%d sentencias)',
    $counts['tablas'], $counts['vistas'], $counts['disparadores'], $counts['procedimientos'], $total));

if ($counts['tablas'] !== 26 || $counts['vistas'] !== 5 || $counts['disparadores'] !== 7 || $counts['procedimientos'] !== 6) {
    fwrite(STDERR, "Aviso: los conteos no coinciden con el manifiesto (26/5/7/6).\n");
    exit(1);
}

if ($withSeed) {
    out('');
    require BASE_PATH . '/bin/seed.php';
    exit(0);
}

out('Instalación completada. Carga datos de demo con: php bin/seed.php');