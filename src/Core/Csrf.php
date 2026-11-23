<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (!Session::has('_csrf')) {
            Session::set('_csrf', bin2hex(random_bytes(32)));
        }
        return (string) Session::get('_csrf');
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    public static function verify(?string $token): void
    {
        $expected = (string) Session::get('_csrf', '');
        if ($token === null || $expected === '' || !hash_equals($expected, $token)) {
            throw new HttpException(419, 'El formulario ha caducado. Recarga la página e inténtalo de nuevo.');
        }
    }
}
