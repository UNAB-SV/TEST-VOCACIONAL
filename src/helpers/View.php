<?php

declare(strict_types=1);

namespace App\Helpers;

final class View
{
    public static function render(string $template, array $data = [], string $layout = 'layouts/base'): void
    {
        $basePath = dirname(__DIR__, 2) . '/templates/';
        $templatePath = $basePath . $template . '.php';
        $layoutPath = $basePath . $layout . '.php';

        if (!is_readable($templatePath) || !is_readable($layoutPath)) {
            http_response_code(500);
            echo 'Error al cargar una plantilla.';
            return;
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $templatePath;
        $content = (string) ob_get_clean();

        require $layoutPath;
    }
}
