<?php
declare(strict_types=1);

namespace App\Core;

class HttpException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly ?string $redirectTo = null,
        public readonly array $payload = []
    ) {
        parent::__construct($message);
    }

    public static function notFound(string $message = 'No encontrado'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'No autorizado'): self
    {
        return new self(403, $message);
    }

    public static function unauthorized(string $message = 'Debes iniciar sesión'): self
    {
        return new self(401, $message);
    }

    public static function badRequest(string $message = 'Petición inválida', array $payload = []): self
    {
        return new self(422, $message, null, $payload);
    }

    public static function conflict(string $message = 'Conflicto'): self
    {
        return new self(409, $message);
    }
}
