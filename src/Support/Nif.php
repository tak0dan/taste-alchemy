<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Validación de NIF/NIE/CIF español. No sustituye a la comprobación documental
 * del moderador, pero evita que se registren identificadores imposibles.
 */
final class Nif
{
    private const LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    public static function normalize(?string $nif): ?string
    {
        if ($nif === null) {
            return null;
        }
        $clean = preg_replace('/[^0-9A-Z]/', '', strtoupper($nif)) ?? '';
        return $clean === '' ? null : $clean;
    }

    public static function valid(?string $nif): bool
    {
        $nif = self::normalize($nif);
        if ($nif === null) {
            return false;
        }

        if (preg_match('/^[0-9]{8}[A-Z]$/', $nif)) {
            $number = (int) substr($nif, 0, 8);
            return self::LETTERS[$number % 23] === $nif[8];
        }

        if (preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $nif)) {
            $map = ['X' => '0', 'Y' => '1', 'Z' => '2'];
            $number = (int) ($map[$nif[0]] . substr($nif, 1, 7));
            return self::LETTERS[$number % 23] === $nif[8];
        }

        if (preg_match('/^[ABCDEFGHJNPQRSUVW][0-9]{7}[0-9A-J]$/', $nif)) {
            $digits = substr($nif, 1, 7);
            $sum = 0;
            for ($i = 0; $i < 7; $i++) {
                $digit = (int) $digits[$i];
                if ($i % 2 === 0) {
                    $doubled = $digit * 2;
                    $sum += intdiv($doubled, 10) + ($doubled % 10);
                } else {
                    $sum += $digit;
                }
            }
            $control = (10 - ($sum % 10)) % 10;
            $last = $nif[8];
            $expectedLetters = 'JABCDEFGHI';
            return $last === (string) $control || $last === $expectedLetters[$control];
        }

        return false;
    }

    public static function type(?string $nif): ?string
    {
        $nif = self::normalize($nif);
        if ($nif === null) {
            return null;
        }
        return match (true) {
            (bool) preg_match('/^[0-9]{8}[A-Z]$/', $nif) => 'DNI',
            (bool) preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $nif) => 'NIE',
            (bool) preg_match('/^[ABCDEFGHJNPQRSUVW][0-9]{7}[0-9A-J]$/', $nif) => 'CIF',
            default => null,
        };
    }
}
