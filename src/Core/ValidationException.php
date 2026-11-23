<?php
declare(strict_types=1);

namespace App\Core;

class ValidationException extends \RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly array $errors, string $message = 'Hay errores en el formulario')
    {
        parent::__construct($message);
    }
}
