<?php

declare(strict_types=1);

namespace App\Validators;

final class RequestValidator
{
    /**
     * Ejemplo básico de validador reusable.
     */
    public function required(string $value): bool
    {
        return trim($value) !== '';
    }
}
