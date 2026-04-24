<?php

declare(strict_types=1);

namespace App\Repositories;

use RuntimeException;

final class JsonCatalogRepository implements CatalogRepository
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $catalog = null;

    public function __construct(private readonly string $catalogPath)
    {
    }

    public function questionsDefinition(): array
    {
        return $this->loadJsonConfig((string) ($this->catalog()['files']['questions_blocks'] ?? ''));
    }

    public function scalesConfig(): array
    {
        return $this->loadJsonConfig((string) ($this->catalog()['files']['scales'] ?? ''));
    }

    public function scoringRules(): array
    {
        return $this->loadJsonConfig((string) ($this->catalog()['files']['scoring_rules'] ?? ''));
    }

    public function validityRules(): array
    {
        return $this->loadJsonConfig((string) ($this->catalog()['files']['validity_rules'] ?? ''));
    }

    public function percentilesBySex(string $sex): array
    {
        $normalizedSex = strtoupper(trim($sex));
        $path = (string) ($this->catalog()['files']['percentiles'][$normalizedSex] ?? '');

        if ($path === '') {
            throw new RuntimeException(sprintf('No existe tabla de percentiles configurada para el sexo "%s".', $normalizedSex));
        }

        return $this->loadJsonConfig($path);
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        if ($this->catalogPath === '' || !is_file($this->catalogPath)) {
            throw new RuntimeException(sprintf('No se encontró el archivo de catálogo: %s', $this->catalogPath));
        }

        $catalog = require $this->catalogPath;
        if (!is_array($catalog)) {
            throw new RuntimeException(sprintf('El catálogo no devolvió un arreglo válido: %s', $this->catalogPath));
        }

        $this->catalog = $catalog;

        return $this->catalog;
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

