<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Acceso PDO único. Todo el SQL vive en repositorios y procedimientos; las
 * vistas y los controladores nunca escriben consultas.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = (string) Config::get('DB_DSN');
        $user = (string) Config::get('DB_USER', 'root');
        $password = (string) Config::get('DB_PASSWORD', '');

        try {
            self::$pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('No se pudo conectar a la base de datos: ' . $e->getMessage(), 0, $e);
        }

        self::$pdo->exec("SET time_zone = '+00:00'");
        return self::$pdo;
    }

    public static function select(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function first(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false ? null : $value;
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::execute($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * Ejecuta un procedimiento con parámetros OUT mediante variables de sesión.
     *
     * @param array<int, mixed> $in
     * @param array<int, string> $out nombres de las variables OUT
     * @return array<string, mixed>
     */
    public static function call(string $procedure, array $in = [], array $out = []): array
    {
        $args = array_fill(0, count($in), '?');
        foreach ($out as $i => $name) {
            $args[] = "@out_{$i}";
        }
        $stmt = self::pdo()->prepare("CALL {$procedure}(" . implode(', ', $args) . ")");
        $stmt->execute(array_values($in));
        $stmt->closeCursor();

        if ($out === []) {
            return [];
        }

        $select = [];
        foreach ($out as $i => $name) {
            $select[] = "@out_{$i} AS `{$name}`";
        }
        return self::first('SELECT ' . implode(', ', $select)) ?? [];
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Ejecuta un fichero .sql respetando las directivas DELIMITER.
     */
    public static function runSqlFile(string $path): int
    {
        if (!is_file($path)) {
            throw new \RuntimeException("No existe el fichero SQL: {$path}");
        }
        $delimiter = ';';
        $buffer = '';
        $count = 0;

        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            $trimmed = trim($line);
            if (stripos($trimmed, 'DELIMITER ') === 0) {
                if (trim($buffer) !== '') {
                    self::pdo()->exec($buffer);
                    $count++;
                    $buffer = '';
                }
                $delimiter = trim(substr($trimmed, strlen('DELIMITER ')));
                continue;
            }

            $buffer .= $line . "\n";
            if ($delimiter !== ';' && str_ends_with(rtrim($line), $delimiter)) {
                $sql = substr(rtrim($buffer), 0, -strlen($delimiter));
                self::pdo()->exec($sql);
                $count++;
                $buffer = '';
            } elseif ($delimiter === ';' && str_ends_with($trimmed, ';')) {
                $sql = trim($buffer);
                $buffer = '';
                if ($sql !== '' && $sql !== ';') {
                    self::pdo()->exec($sql);
                    $count++;
                }
            }
        }

        if (trim($buffer) !== '') {
            self::pdo()->exec($buffer);
            $count++;
        }
        return $count;
    }
}
