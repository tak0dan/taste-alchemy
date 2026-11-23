<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validador declarativo pequeño, sin dependencias.
 * Reglas: required, email, string, min:n, max:n, numeric, integer, between:a,b,
 *         in:a,b,c, date, after:today, before:today, confirmed, slug, url, array
 */
final class Validator
{
    private array $errors = [];

    public function __construct(private readonly array $data)
    {
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    /** @param array<string, string> $rules */
    public function validate(array $rules): array
    {
        $clean = [];
        foreach ($rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            $rulesList = explode('|', $ruleString);
            $required = in_array('required', $rulesList, true);

            if (is_string($value)) {
                $value = trim($value);
            }

            if (!$required && ($value === null || $value === '')) {
                $clean[$field] = $value === '' ? null : $value;
                continue;
            }

            foreach ($rulesList as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $label = ucfirst(str_replace('_', ' ', $field));

                switch ($name) {
                    case 'required':
                        if ($value === null || $value === '' || (is_array($value) && $value === [])) {
                            $this->errors[$field] ??= "El campo {$label} es obligatorio.";
                        }
                        break;
                    case 'email':
                        if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $this->errors[$field] ??= 'Introduce un correo electrónico válido.';
                        }
                        break;
                    case 'string':
                        if (!is_string($value)) {
                            $this->errors[$field] ??= "El campo {$label} debe ser texto.";
                        }
                        break;
                    case 'numeric':
                        if (!is_numeric($value)) {
                            $this->errors[$field] ??= "El campo {$label} debe ser numérico.";
                        }
                        break;
                    case 'integer':
                        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                            $this->errors[$field] ??= "El campo {$label} debe ser un número entero.";
                        }
                        break;
                    case 'min':
                        if (is_string($value) && mb_strlen($value) < (int) $arg) {
                            $this->errors[$field] ??= "El campo {$label} debe tener al menos {$arg} caracteres.";
                        }
                        if (is_numeric($value) && (float) $value < (float) $arg) {
                            $this->errors[$field] ??= "El campo {$label} debe ser mayor o igual que {$arg}.";
                        }
                        break;
                    case 'max':
                        if (is_string($value) && mb_strlen($value) > (int) $arg) {
                            $this->errors[$field] ??= "El campo {$label} no puede superar {$arg} caracteres.";
                        }
                        if (is_numeric($value) && (float) $value > (float) $arg) {
                            $this->errors[$field] ??= "El campo {$label} no puede superar {$arg}.";
                        }
                        break;
                    case 'between':
                        [$min, $max] = array_pad(explode(',', (string) $arg), 2, null);
                        if (!is_numeric($value) || (float) $value < (float) $min || (float) $value > (float) $max) {
                            $this->errors[$field] ??= "El campo {$label} debe estar entre {$min} y {$max}.";
                        }
                        break;
                    case 'in':
                        $allowed = explode(',', (string) $arg);
                        if (!in_array((string) $value, $allowed, true)) {
                            $this->errors[$field] ??= "El valor de {$label} no es válido.";
                        }
                        break;
                    case 'date':
                        if (!is_string($value) || strtotime($value) === false) {
                            $this->errors[$field] ??= "El campo {$label} debe ser una fecha válida.";
                        }
                        break;
                    case 'after':
                        $limit = $arg === 'today' ? strtotime('today') : strtotime((string) $arg);
                        if (strtotime((string) $value) !== false && strtotime((string) $value) < $limit) {
                            $this->errors[$field] ??= "El campo {$label} debe ser posterior a {$arg}.";
                        }
                        break;
                    case 'confirmed':
                        if (($this->data[$field . '_confirmation'] ?? null) !== $value) {
                            $this->errors[$field] ??= 'La confirmación no coincide.';
                        }
                        break;
                    case 'slug':
                        if (!is_string($value) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
                            $this->errors[$field] ??= "El campo {$label} debe ser un slug válido.";
                        }
                        break;
                    case 'url':
                        if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_URL)) {
                            $this->errors[$field] ??= "El campo {$label} debe ser una URL válida.";
                        }
                        break;
                    case 'array':
                        if (!is_array($value)) {
                            $this->errors[$field] ??= "El campo {$label} debe ser una lista.";
                        }
                        break;
                }
            }

            if (!isset($this->errors[$field])) {
                $clean[$field] = $value;
            }
        }

        if ($this->errors !== []) {
            throw new ValidationException($this->errors);
        }
        return $clean;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
