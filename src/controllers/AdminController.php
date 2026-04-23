<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\TestResultPresenter;
use App\Helpers\View;
use App\Repositories\EvaluationRepository;

final class AdminController
{
    public function __construct(
        private readonly EvaluationRepository $evaluationRepository,
        private readonly TestResultPresenter $resultPresenter
    ) {
    }

    public function evaluations(): void
    {
        $filters = [
            'nombre' => trim((string) ($_GET['nombre'] ?? '')),
            'grupo' => trim((string) ($_GET['grupo'] ?? '')),
            'fecha' => trim((string) ($_GET['fecha'] ?? '')),
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 10;

        $result = $this->evaluationRepository->findEvaluations($filters, $page, $perPage);

        View::render('admin/evaluations', [
            'title' => 'Administración de evaluaciones',
            'filters' => $filters,
            'groups' => $this->evaluationRepository->listGroups(),
            'items' => is_array($result['items'] ?? null) ? $result['items'] : [],
            'total' => (int) ($result['total'] ?? 0),
            'page' => (int) ($result['page'] ?? $page),
            'perPage' => (int) ($result['per_page'] ?? $perPage),
        ]);
    }

    public function evaluationDetail(): void
    {
        $evaluationId = (int) ($_GET['id'] ?? 0);
        if ($evaluationId <= 0) {
            http_response_code(422);
            echo 'Identificador de evaluación inválido.';
            return;
        }

        $detail = $this->evaluationRepository->findEvaluationDetailById($evaluationId);
        if ($detail === null) {
            http_response_code(404);
            echo 'No se encontró la evaluación solicitada.';
            return;
        }

        View::render('admin/evaluation-detail', [
            'title' => 'Detalle de evaluación #' . $evaluationId,
            'detail' => $detail,
        ]);
    }

    public function reprintEvaluation(): void
    {
        $evaluationId = (int) ($_GET['id'] ?? 0);
        if ($evaluationId <= 0) {
            http_response_code(422);
            echo 'Identificador de evaluación inválido.';
            return;
        }

        $detail = $this->evaluationRepository->findEvaluationDetailById($evaluationId);
        if ($detail === null) {
            http_response_code(404);
            echo 'No se encontró la evaluación solicitada.';
            return;
        }

        $participant = [
            'nombres' => (string) ($detail['first_name'] ?? ''),
            'apellido_paterno' => (string) ($detail['last_name'] ?? ''),
            'apellido_materno' => (string) ($detail['middle_name'] ?? ''),
            'edad' => (string) ($detail['age'] ?? ''),
            'sexo' => strtolower((string) ($detail['sex'] ?? '')),
            'colegio_nombre' => (string) (($detail['colegio_nombre'] ?? '') !== '' ? $detail['colegio_nombre'] : ($detail['group_name'] ?? '')),
        ];

        $rawScores = [];
        $percentiles = [];

        foreach ((array) ($detail['scales'] ?? []) as $scaleRow) {
            if (!is_array($scaleRow)) {
                continue;
            }

            $scaleId = (string) ($scaleRow['scale_id'] ?? '');
            if ($scaleId === '') {
                continue;
            }

            $rawScores[$scaleId] = (int) ($scaleRow['raw_score'] ?? 0);
            $percentiles[$scaleId] = (int) ($scaleRow['percentile'] ?? 0);
        }

        $report = $this->resultPresenter->present($participant, [
            'resultado' => [
                'puntajes_brutos' => $rawScores,
                'percentiles' => $percentiles,
                'validez_puntaje' => (int) ($detail['validity_score'] ?? 0),
                'validez_estado' => (string) ($detail['validity_state'] ?? 'invalido'),
            ],
        ]);

        View::render('home/test-finished', [
            'title' => 'Reimpresión de evaluación #' . $evaluationId,
            'report' => $report,
        ]);
    }
}
