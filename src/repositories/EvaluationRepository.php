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

    /**
     * @param array<string, scalar|null> $filters
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     per_page: int
     * }
     */
    public function findEvaluations(array $filters, int $page = 1, int $perPage = 10): array;

    /**
     * @return array<int, string>
     */
    public function listGroups(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findEvaluationDetailById(int $evaluationId): ?array;
}
