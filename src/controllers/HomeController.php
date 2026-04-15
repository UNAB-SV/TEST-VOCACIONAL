<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Services\HealthService;

final class HomeController
{
    public function __construct(private readonly HealthService $healthService)
    {
    }

    public function index(): void
    {
        View::render('home/index', [
            'title' => 'Inicio',
            'status' => $this->healthService->status(),
        ]);
    }
}
