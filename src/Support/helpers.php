<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Service\AuthService;

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return BASE_PATH . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim((string) config('APP_URL', ''), '/');
        if ($base === '') {
            $base = '';
        }
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        return View::render($template, $data, $layout);
    }
}

if (!function_exists('partial')) {
    function partial(string $template, array $data = []): string
    {
        return View::capture('partials/' . $template, $data);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }
}

if (!function_exists('flashes')) {
    function flashes(): array
    {
        return Session::flashes();
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return Session::old($key, $default);
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return AuthService::user();
    }
}

if (!function_exists('has_role')) {
    function has_role(string $role): bool
    {
        return AuthService::hasRole($role);
    }
}

if (!function_exists('is_verified_venue')) {
    function is_verified_venue(): bool
    {
        return AuthService::isVerifiedVenue();
    }
}

if (!function_exists('money')) {
    function money(mixed $amount, string $currency = '€'): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }
        return number_format((float) $amount, 2, ',', '.') . ' ' . $currency;
    }
}

if (!function_exists('num')) {
    function num(mixed $value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        return number_format((float) $value, $decimals, ',', '.');
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date, string $format = 'd/m/Y'): string
    {
        if (!$date) {
            return '—';
        }
        $ts = strtotime($date);
        return $ts === false ? '—' : date($format, $ts);
    }
}

if (!function_exists('datetime_local')) {
    function datetime_local(?string $date): string
    {
        if (!$date) {
            return '';
        }
        $ts = strtotime($date);
        return $ts === false ? '' : date('Y-m-d\TH:i', $ts);
    }
}

if (!function_exists('mask_nif')) {
    function mask_nif(?string $nif, bool $full = false): string
    {
        if (!$nif) {
            return '—';
        }
        if ($full) {
            return $nif;
        }
        $length = strlen($nif);
        if ($length <= 3) {
            return str_repeat('•', $length);
        }
        return str_repeat('•', $length - 3) . substr($nif, -3);
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        $text = trim(mb_strtolower($text));
        $map = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c'];
        $text = strtr($text, $map);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-');
    }
}

if (!function_exists('initials')) {
    function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        return $letters !== '' ? $letters : 'TA';
    }
}

if (!function_exists('current_path')) {
    function current_path(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return '/' . trim($path, '/');
    }
}

if (!function_exists('nav_active')) {
    function nav_active(string $prefix): string
    {
        $path = current_path();
        if ($prefix === '/') {
            return $path === '/' ? 'active' : '';
        }
        return str_starts_with($path, $prefix) ? 'active' : '';
    }
}

if (!function_exists('status_label')) {
    function status_label(string $status): string
    {
        return match ($status) {
            'en_maceracion' => 'En maceración',
            'en_reposo' => 'En reposo',
            'embotellado' => 'Embotellado',
            'conservado' => 'Conservado',
            'descartado' => 'Descartado',
            'bloqueado' => 'Bloqueado',
            'abierta' => 'Abierta',
            'en_comision' => 'En comisión',
            'cerrada' => 'Cerrada',
            'expirada' => 'Expirada',
            'cancelada' => 'Cancelada',
            'borrador' => 'Borrador',
            'publicado' => 'Publicado',
            'reservado' => 'Reservado',
            'vendido' => 'Vendido',
            'agotado' => 'Agotado',
            'retirado' => 'Retirado',
            'oculto' => 'Oculto',
            'pendiente' => 'Pendiente',
            'verificada' => 'Verificada',
            'rechazada' => 'Rechazada',
            'suspendida' => 'Suspendida',
            'aprobada' => 'Aprobada',
            'abierto' => 'Abierto',
            'en_revision' => 'En revisión',
            'resuelto' => 'Resuelto',
            'descartado_reporte' => 'Descartado',
            'activa' => 'Activa',
            'expirada_excl' => 'Expirada',
            'anulada' => 'Anulada',
            'en_ejecucion' => 'En ejecución',
            'completada' => 'Completada',
            'venta' => 'Venta',
            'consignacion' => 'Consignación',
            'ambas' => 'Venta o consignación',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}

if (!function_exists('status_class')) {
    function status_class(string $status): string
    {
        return match ($status) {
            'verificada', 'publicado', 'completada', 'conservado', 'abierta', 'activa', 'aprobada', 'vendida' => 'ok',
            'pendiente', 'en_maceracion', 'en_reposo', 'en_comision', 'en_ejecucion', 'reservado', 'dudoso', 'en_revision' => 'warn',
            'rechazada', 'descartado', 'bloqueado', 'anulada', 'expirada', 'suspendida', 'incoherente' => 'bad',
            default => 'neutral',
        };
    }
}

if (!function_exists('json_data')) {
    function json_data(?string $json, array $default = []): array
    {
        if (!$json) {
            return $default;
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : $default;
    }
}

if (!function_exists('verdict_class')) {
    function verdict_class(int|string|null $score): string
    {
        $score = (int) $score;
        if ($score >= 85) {
            return 'ok';
        }
        if ($score >= 70) {
            return 'warn';
        }
        return 'bad';
    }
}

if (!function_exists('verdict_word')) {
    function verdict_word(int|string|null $score): string
    {
        $score = (int) $score;
        if ($score >= 85) {
            return 'sólido';
        }
        if ($score >= 70) {
            return 'plausible';
        }
        if ($score >= 45) {
            return 'dudoso';
        }
        return 'incoherente';
    }
}

if (!function_exists('sigil')) {
    function sigil(int $size = 44): string
    {
        return '<svg class="sigil" width="' . $size . '" height="' . $size . '" viewBox="0 0 48 48" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">'
            . '<circle cx="24" cy="24" r="21" stroke="currentColor" stroke-width="1"/>'
            . '<path d="M24 6 L24 42 M6 24 L42 24" stroke="currentColor" stroke-width="0.6" opacity="0.5"/>'
            . '<circle cx="24" cy="24" r="7" stroke="#7a1128" stroke-width="1.2"/>'
            . '</svg>';
    }
}

if (!function_exists('human_bytes')) {
    function human_bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}

if (!function_exists('query_string')) {
    function query_string(array $overrides = []): string
    {
        $query = array_merge($_GET, $overrides);
        $query = array_filter($query, static fn($v) => $v !== null && $v !== '');
        return $query === [] ? '' : '?' . http_build_query($query);
    }
}
