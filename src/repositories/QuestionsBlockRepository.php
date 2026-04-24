<?php

declare(strict_types=1);

namespace App\Repositories;

final class QuestionsBlockRepository
{
    public function __construct(private readonly CatalogRepository $catalogRepository)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allBlocks(): array
    {
        $data = $this->catalogRepository->questionsDefinition();
        $blocks = $data['blocks'] ?? [];
        if (!is_array($blocks)) {
            return [];
        }

        usort($blocks, static function (array $a, array $b): int {
            return (int) ($a['orden'] ?? 0) <=> (int) ($b['orden'] ?? 0);
        });

        return $blocks;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBlockById(string $blockId): ?array
    {
        foreach ($this->allBlocks() as $block) {
            if ((string) ($block['id'] ?? '') === $blockId) {
                return $block;
            }
        }

        return null;
    }
}
