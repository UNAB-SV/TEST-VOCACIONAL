<?php

declare(strict_types=1);

namespace App\Helpers;

use RuntimeException;

/**
 * Contenedor de dependencias mínimo.
 */
final class ServiceContainer
{
    /**
     * @var array<string, callable(self): mixed>
     */
    private array $factories = [];

    /**
     * @var array<string, mixed>
     */
    private array $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!array_key_exists($id, $this->factories)) {
            throw new RuntimeException(sprintf('Servicio "%s" no registrado.', $id));
        }

        $this->instances[$id] = ($this->factories[$id])($this);

        return $this->instances[$id];
    }
}
