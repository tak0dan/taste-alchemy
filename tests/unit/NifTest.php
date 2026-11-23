<?php
declare(strict_types=1);

use App\Support\Nif;

it('NIF: DNI válido con dígito de control correcto', function () {
    ok(Nif::valid('12345678Z'));
    ok(Nif::valid('00000000T')); // 0 % 23 = 0 → T
});

it('NIF: letra de control incorrecta se rechaza', function () {
    ok(!Nif::valid('12345678A'));
    ok(!Nif::valid('12345678'));
});

it('NIF: normaliza separadores y minúsculas', function () {
    eq('12345678Z', Nif::normalize('12.345.678-z'));
    eq('12345678Z', Nif::normalize(' 12345678Z '));
    ok(Nif::valid('12.345.678-z'));
});

it('NIF: NIE con prefijo X/Y/Z', function () {
    ok(Nif::valid('X1234567L')); // X→0, 1234567 % 23 = 19 → L
    eq('NIE', Nif::type('X1234567L'));
});

it('NIF: CIF con control numérico', function () {
    ok(Nif::valid('A00000000')); // suma 0 → control 0
    eq('CIF', Nif::type('A00000000'));
});

it('NIF: entradas vacías o imposibles', function () {
    ok(!Nif::valid(null));
    ok(!Nif::valid(''));
    ok(!Nif::valid('NO-VALIDO'));
    eq(null, Nif::type('basura'));
});

it('NIF: el dígito de control sigue la tabla oficial', function () {
    $letters = 'TRWAGMYFPDXBNJZSQVHLCKE';
    for ($n = 0; $n < 200; $n++) {
        $nif = sprintf('%08d%s', $n, $letters[$n % 23]);
        ok(Nif::valid($nif), "debería validar {$nif}");
    }
});