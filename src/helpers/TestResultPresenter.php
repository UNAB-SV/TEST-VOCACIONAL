<?php

declare(strict_types=1);

namespace App\Helpers;

use RuntimeException;

final class TestResultPresenter
{
    public function __construct(private readonly string $catalogPath)
    {
    }

    /**
     * @param array<string, string> $participant
     * @param array<string, mixed> $calculationPayload
     * @return array<string, mixed>
     */
    public function present(array $participant, array $calculationPayload): array
    {
        $result = $calculationPayload['resultado'] ?? [];
        if (!is_array($result)) {
            throw new RuntimeException('El resultado de cálculo no tiene la estructura esperada.');
        }

        $catalog = $this->loadPhpConfig($this->catalogPath);
        $scalesConfigPath = (string) ($catalog['files']['scales'] ?? '');
        $scalesConfig = $this->loadJsonConfig($scalesConfigPath);

        $scalesById = $this->indexScales($scalesConfig);
        $rawScores = is_array($result['puntajes_brutos'] ?? null) ? $result['puntajes_brutos'] : [];
        $percentiles = is_array($result['percentiles'] ?? null) ? $result['percentiles'] : [];

        $scaleRows = [];
        $missingPercentiles = [];
        foreach ($scalesById as $scaleId => $scaleMeta) {
            if (($scaleMeta['grupo'] ?? '') !== 'intereses') {
                continue;
            }

            $percentileValue = $percentiles[$scaleId] ?? null;
            $hasPercentile = is_int($percentileValue);
            if (!$hasPercentile) {
                $missingPercentiles[] = $scaleId;
            }

            $scaleRows[] = [
                'id' => $scaleId,
                'nombre' => $scaleMeta['nombre'] ?? $scaleId,
                'puntaje_bruto' => (int) ($rawScores[$scaleId] ?? 0),
                'percentil' => $hasPercentile ? $percentileValue : null,
                'percentil_disponible' => $hasPercentile,
            ];
        }

        usort(
            $scaleRows,
            static fn (array $a, array $b): int => $b['puntaje_bruto'] <=> $a['puntaje_bruto']
        );

        return [
            'evaluado' => [
                'nombre_completo' => trim(sprintf(
                    '%s %s %s',
                    (string) ($participant['nombres'] ?? ''),
                    (string) ($participant['apellido_paterno'] ?? ''),
                    (string) ($participant['apellido_materno'] ?? '')
                )),
                'edad' => (string) ($participant['edad'] ?? ''),
                'sexo' => strtoupper((string) ($participant['sexo'] ?? '')),
                'institucion' => (string) (($participant['colegio_nombre'] ?? '') !== '' ? $participant['colegio_nombre'] : ($participant['grupo'] ?? '')),
            ],
            'validez_puntaje' => (int) ($result['validez_puntaje'] ?? 0),
            'validez_estado' => $this->mapValidityState((string) ($result['validez_estado'] ?? '')),
            'escalas' => $scaleRows,
            'ranking' => $scaleRows,
            'alertas_tecnicas' => [
                'escalas_sin_percentil' => $missingPercentiles,
            ],
        ];
    }

    /**
     * @return array<string, array{nombre: string, grupo: string}>
     */
    private function indexScales(array $scalesConfig): array
    {
        $indexed = [];
        $scales = $scalesConfig['scales'] ?? [];

        if (!is_array($scales)) {
            throw new RuntimeException('El catálogo de escalas es inválido.');
        }

        foreach ($scales as $scale) {
            if (!is_array($scale)) {
                continue;
            }

            $id = (string) ($scale['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $indexed[$id] = [
                'nombre' => (string) ($scale['nombre'] ?? $id),
                'grupo' => (string) ($scale['grupo'] ?? ''),
            ];
        }

        return $indexed;
    }

    private function mapValidityState(string $state): array
    {
        return match (strtolower(trim($state))) {
            'valido' => ['codigo' => 'valido', 'etiqueta' => 'Prueba válida', 'es_valida' => true],
            'dudoso' => ['codigo' => 'dudoso', 'etiqueta' => 'Prueba dudosa', 'es_valida' => false],
            default => ['codigo' => 'invalido', 'etiqueta' => 'Prueba no válida', 'es_valida' => false],
        };
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
