<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Services\ParticipantSessionStore;
use App\Validators\ParticipantDataValidator;

final class HomeController
{
    public function __construct(
        private readonly ParticipantDataValidator $validator,
        private readonly ParticipantSessionStore $sessionStore
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
}
