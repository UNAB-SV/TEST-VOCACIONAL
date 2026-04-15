<?php

declare(strict_types=1);

namespace App\Services;

final class TestSessionStore
{
    private const SESSION_KEY = 'test_answers';

    /**
     * @return array<string, array{mas: string, menos: string}>
     */
    public function getAnswers(): array
    {
        $answers = $_SESSION[self::SESSION_KEY]['answers'] ?? [];

        return is_array($answers) ? $answers : [];
    }

    public function saveBlockAnswer(string $blockId, string $masId, string $menosId): void
    {
        $_SESSION[self::SESSION_KEY]['answers'][$blockId] = [
            'mas' => $masId,
            'menos' => $menosId,
        ];
    }

    public function clearAnswers(): void
    {
        $_SESSION[self::SESSION_KEY]['answers'] = [];
    }
}
