<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CatalogRepository;
use RuntimeException;

final class CalculationEngine
{
    public function __construct(
        private readonly ScoreService $scoreService,
        private readonly CatalogRepository $catalogRepository
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function submitResponses(array $payload): array
    {
        $participant = $payload['participant'] ?? null;
        $answers = $payload['answers'] ?? null;

        if (!is_array($participant) || !is_array($answers)) {
            throw new RuntimeException('No se pudo procesar la calificación por estructura de payload inválida.');
        }

        $sex = strtoupper(trim((string) ($participant['sexo'] ?? '')));
        $questionsDefinition = $this->catalogRepository->questionsDefinition();
        $scoringRules = $this->catalogRepository->scoringRules();
        $validityRules = $this->catalogRepository->validityRules();
        $percentilesBySex = $this->catalogRepository->percentilesBySex($sex);

        $result = $this->scoreService->calculate(
            $answers,
            $questionsDefinition,
            $scoringRules,
            $percentilesBySex,
            $sex,
            $validityRules
        );

        $_SESSION['calculation_result'] = [
            'received_at' => gmdate('c'),
            'participant' => $participant,
            'result' => $result,
            'status' => 'completed',
        ];

        return [
            'status' => 'ok',
            'message' => 'Respuestas calificadas correctamente.',
            'resultado' => $result,
        ];
    }

}
