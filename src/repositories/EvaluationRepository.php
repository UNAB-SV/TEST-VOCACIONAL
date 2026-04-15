<?php

declare(strict_types=1);

namespace App\Repositories;

interface EvaluationRepository
{
    /**
     * @param array<string, string> $participant
     * @param array<string, array{mas: string, menos: string}> $answers
     * @param array<string, mixed> $result
     */
    public function saveEvaluation(array $participant, array $answers, array $result, string $appliedAt): int;

    /**
     * @param array<string, string> $participant
     * @return array<int, array<string, mixed>>
     */
    public function findPreviousEvaluationsByParticipant(array $participant, int $limit = 20): array;
}
