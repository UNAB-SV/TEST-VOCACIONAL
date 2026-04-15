<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class CalculationEngine
{
    public function __construct(
        private readonly ScoreService $scoreService,
        private readonly string $catalogPath
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

        $catalog = $this->loadPhpConfig($this->catalogPath);
        $questionsDefinition = $this->loadJsonConfig((string) ($catalog['files']['questions_blocks'] ?? ''));
        $scoringRules = $this->loadJsonConfig((string) ($catalog['files']['scoring_rules'] ?? ''));
        $validityRules = $this->loadJsonConfig((string) ($catalog['files']['validity_rules'] ?? ''));

        $sex = strtoupper(trim((string) ($participant['sexo'] ?? '')));
        $percentilePath = (string) ($catalog['files']['percentiles'][$sex] ?? '');
        if ($percentilePath === '') {
            throw new RuntimeException(sprintf('No existe tabla de percentiles configurada para el sexo "%s".', $sex));
        }

        $percentilesBySex = $this->loadJsonConfig($percentilePath);

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

    /**
     * @return array<string, mixed>
     */
    private function loadPhpConfig(string $path): array
    {
        if ($path === '' || !is_file($path)) {
            throw new RuntimeException(sprintf('No se encontró el archivo de catálogo: %s', $path));
        }

        $config = require $path;
        if (!is_array($config)) {
            throw new RuntimeException(sprintf('El catálogo no devolvió un arreglo válido: %s', $path));
        }

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadJsonConfig(string $path): array
    {
        if ($path === '' || !is_file($path)) {
            throw new RuntimeException(sprintf('No se encontró el archivo de configuración: %s', $path));
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException(sprintf('No se pudo leer el archivo de configuración: %s', $path));
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('JSON inválido en archivo de configuración: %s', $path));
        }

        return $decoded;
    }
}
