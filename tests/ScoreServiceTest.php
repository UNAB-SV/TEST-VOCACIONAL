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

assertSameValue(2, $result['puntajes_brutos']['servicio_social'] ?? null, 'Puntaje bruto servicio_social incorrecto');
assertSameValue(2, $result['puntajes_brutos']['validez'] ?? null, 'Puntaje bruto validez incorrecto');
assertSameValue('invalido', $result['validez_estado'] ?? null, 'Estado de validez incorrecto');
assertSameValue('servicio_social', $result['escalas_ordenadas_de_mayor_a_menor'][0]['escala'] ?? null, 'Orden de escalas incorrecto');
assertSameValue('matriz_por_bloque_indice_respuesta', $result['traza_calculo']['asignacion_puntajes_por_escala'][0]['regla_aplicada'] ?? null, 'Traza de regla aplicada incorrecta');
assertSameValue(1, $result['traza_calculo']['asignacion_puntajes_por_escala'][0]['indice_en_bloque'] ?? null, 'Índice de actividad incorrecto en traza');

/**
 * @param array<string, mixed> $basePercentiles
 * @return array<string, mixed>
 */
function withLookupMethod(array $basePercentiles, string $method): array
{
    $basePercentiles['lookup_method'] = $method;
    return $basePercentiles;
}

$femaleResult = $service->calculate(
    $answers,
    loadJson('config/test-vocacional/questions_blocks.json'),
    loadJson('config/test-vocacional/scoring_rules.json'),
    withLookupMethod(loadJson('config/test-vocacional/percentiles/female.json'), 'floor'),
    'F',
    loadJson('config/test-vocacional/validity_rules.json')
);

assertSameValue(null, $femaleResult['percentiles']['calculo'] ?? null, 'Percentil cálculo femenino incorrecto con floor');

$nearestResult = $service->calculate(
    $answers,
    loadJson('config/test-vocacional/questions_blocks.json'),
    loadJson('config/test-vocacional/scoring_rules.json'),
    withLookupMethod(loadJson('config/test-vocacional/percentiles/female.json'), 'nearest'),
    'F',
    loadJson('config/test-vocacional/validity_rules.json')
);

assertSameValue(null, $nearestResult['percentiles']['aire_libre'] ?? null, 'Percentil aire libre incorrecto con nearest');

echo "ScoreServiceTest OK\n";
