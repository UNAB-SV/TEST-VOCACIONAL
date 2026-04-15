<?php

declare(strict_types=1);

use App\Services\ScoreService;

require_once __DIR__ . '/../src/helpers/Autoloader.php';
App\Helpers\Autoloader::addNamespace('App', __DIR__ . '/../src');
App\Helpers\Autoloader::register();

/** @return array<string, mixed> */
function loadJson(string $relativePath): array
{
    $path = __DIR__ . '/../' . $relativePath;
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException('No se pudo leer el archivo: ' . $relativePath);
    }

    $decoded = json_decode($content, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('JSON inválido: ' . $relativePath);
    }

    return $decoded;
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' | Esperado: ' . var_export($expected, true) . ' | Actual: ' . var_export($actual, true));
    }
}

$service = new ScoreService();

$answers = [
    'B001' => ['mas' => 'A0001', 'menos' => 'A0002'],
    'B002' => ['mas' => 'A0004', 'menos' => 'A0005'],
    'B003' => ['mas' => 'A0007', 'menos' => 'A0008'],
];

$result = $service->calculate(
    $answers,
    loadJson('config/test-vocacional/questions_blocks.json'),
    loadJson('config/test-vocacional/scoring_rules.json'),
    loadJson('config/test-vocacional/percentiles/male.json'),
    'M',
    loadJson('config/test-vocacional/validity_rules.json')
);

assertSameValue(1, $result['puntajes_brutos']['mecanico'] ?? null, 'Puntaje bruto mecánico incorrecto');
assertSameValue(-1, $result['puntajes_brutos']['cientifico'] ?? null, 'Puntaje bruto científico incorrecto');
assertSameValue(60, $result['percentiles']['calculo'] ?? null, 'Percentil cálculo incorrecto');
assertSameValue('valido', $result['validez_estado'] ?? null, 'Estado de validez incorrecto');
assertSameValue(0, $result['validez_puntaje'] ?? null, 'Puntaje de validez incorrecto');
assertSameValue('aire_libre', $result['escalas_ordenadas_de_mayor_a_menor'][0]['escala'] ?? null, 'Orden de escalas incorrecto');

echo "ScoreServiceTest OK\n";
