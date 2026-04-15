<?php

declare(strict_types=1);

namespace App\Repositories;

final class QuestionsBlockRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function allBlocks(): array
    {
        $path = dirname(__DIR__, 2) . '/config/test-vocacional/questions_blocks.json';

        if (!is_readable($path)) {
            return [];
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return [];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }

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
