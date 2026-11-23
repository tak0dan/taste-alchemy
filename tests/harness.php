<?php
declare(strict_types=1);

/**
 * Arnés de pruebas mínimo, sin dependencias. Cada prueba se registra con
 * it('nombre', fn() => ...) y se ejecuta con run_tests(). Las aserciones
 * lanzan una excepción con un mensaje legible si fallan.
 */

$GLOBALS['__tests'] = [];
$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;
$GLOBALS['__failures'] = [];

function it(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = [$name, $fn];
}

function ok(bool $cond, string $msg = 'la condición no se cumple'): void
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

function eq(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected != $actual) {
        throw new RuntimeException(trim($msg . ' · esperado ' . var_export($expected, true) . ', obtenido ' . var_export($actual, true), ' ·'));
    }
}

function near(float $expected, float $actual, float $tolerance, string $msg = ''): void
{
    if (abs($expected - $actual) > $tolerance) {
        throw new RuntimeException(trim($msg . " · esperado ~{$expected} (±{$tolerance}), obtenido {$actual}", ' ·'));
    }
}

function run_tests(string $suite): int
{
    echo "\n{$suite}\n";
    foreach ($GLOBALS['__tests'] as [$name, $fn]) {
        try {
            $fn();
            $GLOBALS['__pass']++;
            echo "  ✓ {$name}\n";
        } catch (\Throwable $e) {
            $GLOBALS['__fail']++;
            $GLOBALS['__failures'][] = [$suite, $name, $e->getMessage()];
            echo "  ✗ {$name} — {$e->getMessage()}\n";
        }
    }
    $GLOBALS['__tests'] = [];
    return $GLOBALS['__fail'];
}

function test_summary(): int
{
    echo "\n";
    echo str_repeat('─', 60) . "\n";
    echo $GLOBALS['__pass'] . ' superadas · ' . $GLOBALS['__fail'] . " fallidas\n";
    if ($GLOBALS['__fail'] > 0) {
        echo "\nFallos:\n";
        foreach ($GLOBALS['__failures'] as [$suite, $name, $msg]) {
            echo "  [{$suite}] {$name}: {$msg}\n";
        }
        return 1;
    }
    return 0;
}