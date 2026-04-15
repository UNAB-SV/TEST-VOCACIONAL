<?php

declare(strict_types=1);

namespace App\Repositories;

final class NullEvaluationRepository implements EvaluationRepository
{
    public function saveEvaluation(array $participant, array $answers, array $result, string $appliedAt): int
    {
        return 0;
    }

    public function findPreviousEvaluationsByParticipant(array $participant, int $limit = 20): array
    {
        return [];
    }

    public function findEvaluations(array $filters, int $page = 1, int $perPage = 10): array
    {
        return [
            'items' => [],
            'total' => 0,
            'page' => max(1, $page),
            'per_page' => max(1, $perPage),
        ];
    }

    public function listGroups(): array
    {
        return [];
    }

    public function findEvaluationDetailById(int $evaluationId): ?array
    {
        return null;
    }
}
