<?php

declare(strict_types=1);

namespace App\Validators;

final class TestResponseValidator
{
    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $block
     * @return array<string, string>
     */
    public function validateBlockAnswer(array $input, array $block): array
    {
        $errors = [];

        $mas = trim((string) ($input['mas'] ?? ''));
        $menos = trim((string) ($input['menos'] ?? ''));

        if ($mas === '') {
            $errors['mas'] = 'Debes marcar una actividad como "más".';
        }

        if ($menos === '') {
            $errors['menos'] = 'Debes marcar una actividad como "menos".';
        }

        if ($mas !== '' && $menos !== '' && $mas === $menos) {
            $errors['conflict'] = 'No puedes marcar la misma actividad como "más" y "menos".';
        }

        $validActivityIds = [];
        $activities = $block['actividades'] ?? [];
        if (is_array($activities)) {
            foreach ($activities as $activity) {
                if (is_array($activity)) {
                    $validActivityIds[] = (string) ($activity['id'] ?? '');
                }
            }
        }

        if ($mas !== '' && !in_array($mas, $validActivityIds, true)) {
            $errors['mas_invalid'] = 'La opción seleccionada como "más" no pertenece al bloque.';
        }

        if ($menos !== '' && !in_array($menos, $validActivityIds, true)) {
            $errors['menos_invalid'] = 'La opción seleccionada como "menos" no pertenece al bloque.';
        }

        return $errors;
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @param array<string, array{mas: string, menos: string}> $answers
     * @return array<string, string>
     */
    public function validateCompleteTest(array $blocks, array $answers): array
    {
        $errors = [];

        foreach ($blocks as $block) {
            $blockId = (string) ($block['id'] ?? '');
            if ($blockId === '') {
                continue;
            }

            if (!isset($answers[$blockId])) {
                $errors[$blockId] = 'Falta responder el bloque ' . $blockId . '.';
                continue;
            }

            $blockErrors = $this->validateBlockAnswer($answers[$blockId], $block);
            if ($blockErrors !== []) {
                $errors[$blockId] = 'El bloque ' . $blockId . ' tiene respuestas inválidas.';
            }
        }

        return $errors;
    }
}
