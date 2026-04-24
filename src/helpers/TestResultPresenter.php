<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Repositories\CatalogRepository;
use RuntimeException;

final class TestResultPresenter
{
    public function __construct(private readonly CatalogRepository $catalogRepository)
    {
    }

    /**
     * @param array<string, string> $participant
     * @param array<string, mixed> $calculationPayload
     * @return array<string, mixed>
     */
    public function present(array $participant, array $calculationPayload, ?string $appliedAt = null): array
    {
        $result = $calculationPayload['resultado'] ?? [];
        if (!is_array($result)) {
            throw new RuntimeException('El resultado de cálculo no tiene la estructura esperada.');
        }

        $scalesById = $this->indexScales($this->catalogRepository->scalesConfig());
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
                'institucion' => $this->resolveInstitutionName($participant),
                'pais_nombre' => trim((string) ($participant['pais_nombre'] ?? '')),
                'departamento_nombre' => trim((string) ($participant['departamento_nombre'] ?? '')),
                'municipio_nombre' => trim((string) ($participant['municipio_nombre'] ?? '')),
            ],
            'applied_at' => $this->resolveAppliedAt($participant, $appliedAt),
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

    private function resolveAppliedAt(array $participant, ?string $appliedAt): string
    {
        $source = $appliedAt ?? (string) ($participant['applied_at'] ?? '');
        return trim($source);
    }

    private function resolveInstitutionName(array $participant): string
    {
        $schoolName = trim((string) ($participant['colegio_nombre'] ?? ''));
        if ($schoolName !== '') {
            return $schoolName;
        }

        $groupName = trim((string) ($participant['group_name'] ?? ''));
        if ($groupName !== '') {
            return $groupName;
        }

        return trim((string) ($participant['grupo'] ?? ''));
    }

}
