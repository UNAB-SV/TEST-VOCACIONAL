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
}
