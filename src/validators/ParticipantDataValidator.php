<?php

declare(strict_types=1);

namespace App\Validators;

use App\Repositories\GeoCatalogRepository;
use App\Repositories\SchoolRepository;

final class ParticipantDataValidator
{
    public function __construct(
        private readonly SchoolRepository $schoolRepository,
        private readonly GeoCatalogRepository $geoCatalogRepository,
        private readonly int $elSalvadorCountryId
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function validate(array $input): array
    {
        $errors = [];

        $nombres = trim((string) ($input['nombres'] ?? ''));
        $apellidoPaterno = trim((string) ($input['apellido_paterno'] ?? ''));
        $apellidoMaterno = trim((string) ($input['apellido_materno'] ?? ''));
        $edad = trim((string) ($input['edad'] ?? ''));
        $sexo = trim((string) ($input['sexo'] ?? ''));
        $colegioIdRaw = trim((string) ($input['colegio_id'] ?? ''));
        $countryIdRaw = trim((string) ($input['pais_id'] ?? ''));
        $departmentIdRaw = trim((string) ($input['departamento_id'] ?? ''));
        $municipalityIdRaw = trim((string) ($input['municipio_id'] ?? ''));

        if ($nombres === '') {
            $errors['nombres'] = 'Ingresa tus nombres.';
        } elseif (!$this->isValidName($nombres)) {
            $errors['nombres'] = 'Los nombres solo pueden contener letras y espacios.';
        }

        if ($apellidoPaterno === '') {
            $errors['apellido_paterno'] = 'Ingresa tu apellido paterno.';
        } elseif (!$this->isValidName($apellidoPaterno)) {
            $errors['apellido_paterno'] = 'El apellido paterno solo puede contener letras y espacios.';
        }

        if ($apellidoMaterno === '') {
            $errors['apellido_materno'] = 'Ingresa tu apellido materno.';
        } elseif (!$this->isValidName($apellidoMaterno)) {
            $errors['apellido_materno'] = 'El apellido materno solo puede contener letras y espacios.';
        }

        if ($edad === '') {
            $errors['edad'] = 'Ingresa tu edad.';
        } elseif (!ctype_digit($edad)) {
            $errors['edad'] = 'La edad debe ser un número entero.';
        } else {
            $edadValue = (int) $edad;
            if ($edadValue < 10 || $edadValue > 99) {
                $errors['edad'] = 'La edad debe estar entre 10 y 99 años.';
            }
        }

        $allowedSexos = ['F', 'M', 'X'];
        if ($sexo === '') {
            $errors['sexo'] = 'Selecciona tu sexo.';
        } elseif (!in_array($sexo, $allowedSexos, true)) {
            $errors['sexo'] = 'El sexo seleccionado no es válido.';
        }

        if ($colegioIdRaw === '') {
            $errors['colegio_id'] = 'Selecciona tu institución de procedencia.';
        } elseif (!ctype_digit($colegioIdRaw) || (int) $colegioIdRaw <= 0) {
            $errors['colegio_id'] = 'La institución seleccionada no es válida.';
        } elseif (!$this->schoolRepository->existsById((int) $colegioIdRaw)) {
            $errors['colegio_id'] = 'La institución seleccionada no existe.';
        }

        if ($countryIdRaw === '') {
            $errors['pais_id'] = 'Selecciona un país.';
            return $errors;
        }

        if (!ctype_digit($countryIdRaw) || (int) $countryIdRaw <= 0) {
            $errors['pais_id'] = 'El país seleccionado no es válido.';
            return $errors;
        }

        $countryId = (int) $countryIdRaw;
        $country = $this->geoCatalogRepository->findCountryById($countryId);
        if ($country === null) {
            $errors['pais_id'] = 'El país seleccionado no existe.';
            return $errors;
        }

        if ($countryId !== $this->elSalvadorCountryId) {
            return $errors;
        }

        if ($departmentIdRaw === '') {
            $errors['departamento_id'] = 'Selecciona un departamento.';
            return $errors;
        }

        if (!ctype_digit($departmentIdRaw) || (int) $departmentIdRaw <= 0) {
            $errors['departamento_id'] = 'El departamento seleccionado no es válido.';
            return $errors;
        }

        $departmentId = (int) $departmentIdRaw;
        $department = $this->geoCatalogRepository->findDepartmentById($departmentId);
        if ($department === null) {
            $errors['departamento_id'] = 'El departamento seleccionado no existe.';
            return $errors;
        }

        if ($municipalityIdRaw === '') {
            $errors['municipio_id'] = 'Selecciona un municipio o distrito.';
            return $errors;
        }

        if (!ctype_digit($municipalityIdRaw) || (int) $municipalityIdRaw <= 0) {
            $errors['municipio_id'] = 'El municipio o distrito seleccionado no es válido.';
            return $errors;
        }

        $municipality = $this->geoCatalogRepository->findMunicipalityByDepartmentAndId($departmentId, (int) $municipalityIdRaw);
        if ($municipality === null) {
            $errors['municipio_id'] = 'El municipio o distrito no corresponde al departamento seleccionado.';
        }

        return $errors;
    }

    private function isValidName(string $value): bool
    {
        return (bool) preg_match('/^[\p{L} ]+$/u', $value);
    }
}
