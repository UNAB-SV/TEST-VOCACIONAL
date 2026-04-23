<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\TestResultPresenter;
use App\Helpers\View;
use App\Repositories\EvaluationRepository;
use App\Repositories\GeoCatalogRepository;
use App\Repositories\QuestionsBlockRepository;
use App\Repositories\SchoolRepository;
use App\Services\CalculationEngine;
use App\Services\ParticipantSessionStore;
use App\Services\TestSessionStore;
use App\Validators\ParticipantDataValidator;
use App\Validators\TestResponseValidator;

final class HomeController
{
    public function __construct(
        private readonly ParticipantDataValidator $validator,
        private readonly SchoolRepository $schoolRepository,
        private readonly GeoCatalogRepository $geoCatalogRepository,
        private readonly int $elSalvadorCountryId,
        private readonly ParticipantSessionStore $sessionStore,
        private readonly QuestionsBlockRepository $questionsRepository,
        private readonly TestSessionStore $testSessionStore,
        private readonly TestResponseValidator $testResponseValidator,
        private readonly CalculationEngine $calculationEngine,
        private readonly TestResultPresenter $resultPresenter,
        private readonly EvaluationRepository $evaluationRepository
    ) {
    }

    public function index(array $oldInput = [], array $errors = []): void
    {
        $formData = $oldInput !== [] ? $oldInput : $this->sessionStore->get();
        if (!array_key_exists('pais_id', $formData) || (int) ($formData['pais_id'] ?? 0) <= 0) {
            $formData['pais_id'] = (string) $this->elSalvadorCountryId;
        }

        $selectedSchool = null;
        $selectedSchoolId = (int) ($formData['colegio_id'] ?? 0);
        $selectedCountryId = (int) ($formData['pais_id'] ?? 0);
        $selectedDepartmentId = (int) ($formData['departamento_id'] ?? 0);
        $departments = $selectedCountryId === $this->elSalvadorCountryId
            ? $this->geoCatalogRepository->listDepartments()
            : [];
        $municipalities = ($selectedCountryId === $this->elSalvadorCountryId && $selectedDepartmentId > 0)
            ? $this->geoCatalogRepository->listMunicipalitiesByDepartment($selectedDepartmentId)
            : [];
        if ($selectedSchoolId > 0) {
            $selectedSchool = $this->schoolRepository->findById($selectedSchoolId);
        }

        View::render('home/index', [
            'title' => 'Inicio del test',
            'errors' => $errors,
            'formData' => $formData,
            'selectedSchool' => $selectedSchool,
            'countries' => $this->geoCatalogRepository->listCountries(),
            'departments' => $departments,
            'municipalities' => $municipalities,
            'elSalvadorCountryId' => $this->elSalvadorCountryId,
        ]);
    }

    public function submit(): void
    {
        $input = [
            'nombres' => $_POST['nombres'] ?? '',
            'apellido_paterno' => $_POST['apellido_paterno'] ?? '',
            'apellido_materno' => $_POST['apellido_materno'] ?? '',
            'edad' => $_POST['edad'] ?? '',
            'sexo' => $_POST['sexo'] ?? '',
            'colegio_id' => $_POST['colegio_id'] ?? '',
            'pais_id' => $_POST['pais_id'] ?? '',
            'departamento_id' => $_POST['departamento_id'] ?? '',
            'municipio_id' => $_POST['municipio_id'] ?? '',
        ];

        $errors = $this->validator->validate($input);

        if ($errors !== []) {
            http_response_code(422);
            $this->index($input, $errors);
            return;
        }

        $school = $this->schoolRepository->findById((int) $input['colegio_id']);
        $input['colegio_nombre'] = (string) ($school['nombre'] ?? '');
        $input = $this->resolveGeoNames($input);

        $this->sessionStore->save($input);
        $this->testSessionStore->clearAnswers();

        header('Location: /instrucciones');
        exit;
    }

    public function instructions(): void
    {
        if (!$this->sessionStore->hasData()) {
            header('Location: /');
            exit;
        }

        $participant = $this->sessionStore->get();

        View::render('home/instructions', [
            'title' => 'Instrucciones',
            'participant' => $participant,
        ]);
    }

    public function startTest(): void
    {
        if (!$this->sessionStore->hasData()) {
            header('Location: /');
            exit;
        }

        $this->sessionStore->allowTestStart();

        header('Location: /prueba');
        exit;
    }

    public function test(): void
    {
        if (!$this->sessionStore->hasData()) {
            header('Location: /');
            exit;
        }

        if (!$this->sessionStore->canStartTest()) {
            header('Location: /instrucciones');
            exit;
        }

        View::render('home/test', [
            'title' => 'Prueba vocacional',
            'participant' => $this->sessionStore->get(),
            'blocks' => $this->questionsRepository->allBlocks(),
            'savedAnswers' => $this->testSessionStore->getAnswers(),
        ]);
    }

    public function saveTestBlock(): void
    {
        if (!$this->sessionStore->canStartTest()) {
            $this->jsonResponse(403, [
                'status' => 'error',
                'message' => 'No tienes una sesión válida para contestar la prueba.',
            ]);
            return;
        }

        $blockId = trim((string) ($_POST['block_id'] ?? ''));
        $block = $this->questionsRepository->findBlockById($blockId);

        if ($block === null) {
            $this->jsonResponse(422, [
                'status' => 'error',
                'message' => 'Bloque inválido.',
            ]);
            return;
        }

        $answerInput = [
            'mas' => $_POST['mas'] ?? '',
            'menos' => $_POST['menos'] ?? '',
        ];

        $errors = $this->testResponseValidator->validateBlockAnswer($answerInput, $block);
        if ($errors !== []) {
            $this->jsonResponse(422, [
                'status' => 'error',
                'message' => 'La respuesta del bloque no es válida.',
                'errors' => $errors,
            ]);
            return;
        }

        $this->testSessionStore->saveBlockAnswer($blockId, (string) $answerInput['mas'], (string) $answerInput['menos']);

        $this->jsonResponse(200, [
            'status' => 'ok',
            'message' => 'Bloque guardado correctamente.',
        ]);
    }

    public function finishTest(): void
    {
        if (!$this->sessionStore->canStartTest()) {
            header('Location: /');
            exit;
        }

        $blocks = $this->questionsRepository->allBlocks();
        $answers = $this->testSessionStore->getAnswers();

        $errors = $this->testResponseValidator->validateCompleteTest($blocks, $answers);
        if ($errors !== []) {
            http_response_code(422);
            View::render('home/test', [
                'title' => 'Prueba vocacional',
                'participant' => $this->sessionStore->get(),
                'blocks' => $blocks,
                'savedAnswers' => $answers,
                'serverErrors' => array_values($errors),
            ]);
            return;
        }

        $participant = $this->sessionStore->get();
        $result = $this->calculationEngine->submitResponses([
            'participant' => $participant,
            'answers' => $answers,
        ]);

        $appliedAt = gmdate('Y-m-d H:i:s');
        $this->evaluationRepository->saveEvaluation(
            $participant,
            $answers,
            is_array($result['resultado'] ?? null) ? $result['resultado'] : [],
            $appliedAt
        );

        View::render('home/test-finished', [
            'title' => 'Prueba finalizada',
            'report' => $this->resultPresenter->present($participant, $result, $appliedAt),
        ]);
    }

    public function previousEvaluations(): void
    {
        if (!$this->sessionStore->hasData()) {
            $this->jsonResponse(403, [
                'status' => 'error',
                'message' => 'No existe un evaluado activo para consultar historial.',
            ]);
            return;
        }

        $participant = $this->sessionStore->get();
        $history = $this->evaluationRepository->findPreviousEvaluationsByParticipant($participant, 25);

        $this->jsonResponse(200, [
            'status' => 'ok',
            'participant' => $participant,
            'evaluations' => $history,
        ]);
    }

    public function searchSchools(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $items = $this->schoolRepository->searchByName($query, 15);

        $this->jsonResponse(200, [
            'status' => 'ok',
            'items' => array_map(static function (array $item): array {
                return [
                    'id' => (int) ($item['id'] ?? 0),
                    'nombre' => (string) ($item['nombre'] ?? ''),
                    'tipo_institucion' => (int) ($item['tipo_institucion'] ?? 0),
                ];
            }, $items),
        ]);
    }

    public function listDepartments(): void
    {
        $countryId = (int) ($_GET['pais_id'] ?? 0);
        if ($countryId !== $this->elSalvadorCountryId) {
            $this->jsonResponse(200, [
                'status' => 'ok',
                'items' => [],
            ]);
            return;
        }

        $items = $this->geoCatalogRepository->listDepartments();
        $this->jsonResponse(200, [
            'status' => 'ok',
            'items' => $items,
        ]);
    }

    public function listMunicipalities(): void
    {
        $departmentId = (int) ($_GET['departamento_id'] ?? 0);
        if ($departmentId <= 0) {
            $this->jsonResponse(422, [
                'status' => 'error',
                'message' => 'Debes indicar un departamento válido.',
                'items' => [],
            ]);
            return;
        }

        if ($this->geoCatalogRepository->findDepartmentById($departmentId) === null) {
            $this->jsonResponse(422, [
                'status' => 'error',
                'message' => 'El departamento seleccionado no existe.',
                'items' => [],
            ]);
            return;
        }

        $items = $this->geoCatalogRepository->listMunicipalitiesByDepartment($departmentId);
        $this->jsonResponse(200, [
            'status' => 'ok',
            'items' => $items,
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function resolveGeoNames(array $input): array
    {
        $countryId = (int) ($input['pais_id'] ?? 0);
        $country = $this->geoCatalogRepository->findCountryById($countryId);
        $input['pais_nombre'] = (string) ($country['nombre'] ?? '');

        if ($countryId !== $this->elSalvadorCountryId) {
            $input['departamento_id'] = null;
            $input['departamento_nombre'] = null;
            $input['municipio_id'] = null;
            $input['municipio_nombre'] = null;
            return $input;
        }

        $departmentId = (int) ($input['departamento_id'] ?? 0);
        $department = $this->geoCatalogRepository->findDepartmentById($departmentId);
        $input['departamento_nombre'] = (string) ($department['nombre'] ?? '');

        $municipalityId = (int) ($input['municipio_id'] ?? 0);
        $municipality = $this->geoCatalogRepository->findMunicipalityByDepartmentAndId($departmentId, $municipalityId);
        $input['municipio_nombre'] = (string) ($municipality['nombre'] ?? '');

        return $input;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
