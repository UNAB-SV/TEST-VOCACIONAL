<?php

declare(strict_types=1);

namespace App\Services;

final class CalculationEngine
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function submitResponses(array $payload): array
    {
        $_SESSION['calculation_result'] = [
            'received_at' => gmdate('c'),
            'payload' => $payload,
            'status' => 'received',
        ];

        return [
            'status' => 'ok',
            'message' => 'Respuestas enviadas correctamente al motor de cálculo.',
        ];
    }
}
