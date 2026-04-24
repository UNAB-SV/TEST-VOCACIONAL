<?php

declare(strict_types=1);

namespace App\Repositories;

final class LazyCatalogRepository implements CatalogRepository
{
    private ?CatalogRepository $repository = null;

    /**
     * @param callable(): CatalogRepository $factory
     */
    public function __construct(private readonly mixed $factory)
    {
    }

    public function questionsDefinition(): array
    {
        return $this->repository()->questionsDefinition();
    }

    public function scalesConfig(): array
    {
        return $this->repository()->scalesConfig();
    }

    public function scoringRules(): array
    {
        return $this->repository()->scoringRules();
    }

    public function validityRules(): array
    {
        return $this->repository()->validityRules();
    }

    public function percentilesBySex(string $sex): array
    {
        return $this->repository()->percentilesBySex($sex);
    }

    private function repository(): CatalogRepository
    {
        if ($this->repository === null) {
            $repository = ($this->factory)();
            if (!$repository instanceof CatalogRepository) {
                throw new \RuntimeException('La factory de catálogo no devolvió un CatalogRepository.');
            }

            $this->repository = $repository;
        }

        return $this->repository;
    }
}

