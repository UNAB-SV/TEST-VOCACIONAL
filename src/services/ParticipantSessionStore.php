<?php

declare(strict_types=1);

namespace App\Services;

final class ParticipantSessionStore
{
    private const SESSION_KEY = 'participant_data';
    private const FLOW_KEY = 'test_flow';

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): void
    {
        $_SESSION[self::SESSION_KEY] = [
            'nombres' => trim((string) ($data['nombres'] ?? '')),
            'apellido_paterno' => trim((string) ($data['apellido_paterno'] ?? '')),
            'apellido_materno' => trim((string) ($data['apellido_materno'] ?? '')),
            'edad' => (string) ((int) ($data['edad'] ?? 0)),
            'sexo' => trim((string) ($data['sexo'] ?? '')),
            'grupo' => trim((string) ($data['grupo'] ?? '')),
        ];

        $this->resetFlow();
    }

    /**
     * @return array<string, string>
     */
    public function get(): array
    {
        $sessionData = $_SESSION[self::SESSION_KEY] ?? [];

        return [
            'nombres' => (string) ($sessionData['nombres'] ?? ''),
            'apellido_paterno' => (string) ($sessionData['apellido_paterno'] ?? ''),
            'apellido_materno' => (string) ($sessionData['apellido_materno'] ?? ''),
            'edad' => (string) ($sessionData['edad'] ?? ''),
            'sexo' => (string) ($sessionData['sexo'] ?? ''),
            'grupo' => (string) ($sessionData['grupo'] ?? ''),
        ];
    }

    public function hasData(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY]);
    }

    public function allowTestStart(): void
    {
        $_SESSION[self::FLOW_KEY] = [
            'instructions_confirmed' => true,
        ];
    }

    public function canStartTest(): bool
    {
        return $this->hasData() && (bool) ($_SESSION[self::FLOW_KEY]['instructions_confirmed'] ?? false);
    }

    private function resetFlow(): void
    {
        $_SESSION[self::FLOW_KEY] = [
            'instructions_confirmed' => false,
        ];
    }
}
