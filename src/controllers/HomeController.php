<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\QuestionsBlockRepository;
use App\Services\CalculationEngine;
use App\Services\ParticipantSessionStore;
use App\Services\TestSessionStore;
use App\Validators\ParticipantDataValidator;
use App\Validators\TestResponseValidator;

final class HomeController
{
    public function __construct(
        private readonly ParticipantDataValidator $validator,
        private readonly ParticipantSessionStore $sessionStore,
        private readonly QuestionsBlockRepository $questionsRepository,
        private readonly TestSessionStore $testSessionStore,
        private readonly TestResponseValidator $testResponseValidator,
        private readonly CalculationEngine $calculationEngine
    ) {
    }

    public function index(array $oldInput = [], array $errors = []): void
    {
        $formData = $oldInput !== [] ? $oldInput : $this->sessionStore->get();

        View::render('home/index', [
            'title' => 'Inicio del test',
            'errors' => $errors,
            'formData' => $formData,
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
            'grupo' => $_POST['grupo'] ?? '',
        ];

        $errors = $this->validator->validate($input);

        if ($errors !== []) {
            http_response_code(422);
            $this->index($input, $errors);
            return;
        }

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

        $result = $this->calculationEngine->submitResponses([
            'participant' => $this->sessionStore->get(),
            'answers' => $answers,
        ]);

        View::render('home/test-finished', [
            'title' => 'Prueba finalizada',
            'result' => $result,
            'totalBlocks' => count($blocks),
        ]);
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
