<?php

declare(strict_types=1);

namespace App\Services;

final class HealthService
{
    /**
     * Servicio de ejemplo para validar la estructura base.
     */
    public function status(): string
    {
        return 'OK';
    }
}
