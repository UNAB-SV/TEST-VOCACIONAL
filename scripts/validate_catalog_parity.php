<?php

declare(strict_types=1);

use App\Repositories\JsonCatalogRepository;
use App\Repositories\MysqlCatalogRepository;
use App\Repositories\PdoConnectionFactory;
use App\Services\ScoreService;

require_once dirname(__DIR__) . '/src/helpers/Autoloader.php';

App\Helpers\Autoloader::addNamespace('App', dirname(__DIR__) . '/src');
App\Helpers\Autoloader::register();

/**
 * @param array<int, string> $argv
 */
function parityOptionValue(array $argv, string $name, ?string $default = null): ?string
{
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $prefix)) {
            return substr($arg, strlen($prefix));
        }
    }

    return $default;
}

/**
 * @param array<int, string> $argv
 */
function parityHasFlag(array $argv, string $name): bool
{
    return in_array('--' . $name, $argv, true);
}

/**
 * @return array<string, mixed>
 */
function parityLoadConfig(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException(sprintf('No existe el archivo de configuración: %s', $path));
    }

    $config = require $path;
    if (!is_array($config)) {
        throw new RuntimeException(sprintf('El archivo de configuración no devolvió un arreglo: %s', $path));
    }

    return $config;
}

/**
 * @return array<string, mixed>
 */
function parityDatabaseConfig(array $appConfig): array
{
    $database = $appConfig['database'] ?? [];
    if (!is_array($database)) {
        $database = [];
    }

    return [
        'host' => getenv('DB_HOST') !== false ? (string) getenv('DB_HOST') : ($database['host'] ?? '127.0.0.1'),
        'port' => getenv('DB_PORT') !== false ? (int) getenv('DB_PORT') : ($database['port'] ?? 3306),
        'database' => getenv('DB_DATABASE') !== false ? (string) getenv('DB_DATABASE') : ($database['database'] ?? 'test_vocacional'),
        'username' => getenv('DB_USERNAME') !== false ? (string) getenv('DB_USERNAME') : ($database['username'] ?? ''),
        'password' => getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : ($database['password'] ?? ''),
        'charset' => getenv('DB_CHARSET') !== false ? (string) getenv('DB_CHARSET') : ($database['charset'] ?? 'utf8mb4'),
    ];
}

/**
 * @return array<string, mixed>
 */
function canonicalizeCatalogValue(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    $isList = array_is_list($value);
    $canonical = [];
    foreach ($value as $key => $item) {
        $canonical[$key] = canonicalizeCatalogValue($item);
    }

    if (!$isList) {
        ksort($canonical);
    }

    return $canonical;
}

function encodeForHash(mixed $value): string
{
    $encoded = json_encode(canonicalizeCatalogValue($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new RuntimeException('No se pudo serializar valor para comparación.');
    }

    return $encoded;
}

/**
 * @return array<int, array{path: string, json: mixed, mysql: mixed}>
 */
function diffValues(mixed $jsonValue, mixed $mysqlValue, string $path = '$', int $limit = 50): array
{
    if ($limit <= 0) {
        return [];
    }

    if (!is_array($jsonValue) || !is_array($mysqlValue)) {
        return $jsonValue === $mysqlValue ? [] : [[
            'path' => $path,
            'json' => $jsonValue,
            'mysql' => $mysqlValue,
        ]];
    }

    $diffs = [];
    $keys = array_values(array_unique(array_merge(array_keys($jsonValue), array_keys($mysqlValue))));
    usort($keys, static fn (int|string $a, int|string $b): int => (string) $a <=> (string) $b);

    foreach ($keys as $key) {
        if (count($diffs) >= $limit) {
            break;
        }

        $childPath = is_int($key) ? sprintf('%s[%d]', $path, $key) : sprintf('%s.%s', $path, $key);
        if (!array_key_exists($key, $jsonValue)) {
            $diffs[] = ['path' => $childPath, 'json' => '__MISSING__', 'mysql' => $mysqlValue[$key]];
            continue;
        }

        if (!array_key_exists($key, $mysqlValue)) {
            $diffs[] = ['path' => $childPath, 'json' => $jsonValue[$key], 'mysql' => '__MISSING__'];
            continue;
        }

        $diffs = array_merge($diffs, diffValues($jsonValue[$key], $mysqlValue[$key], $childPath, $limit - count($diffs)));
    }

    return $diffs;
}

/**
 * @return array<string, array{mas: string, menos: string}>
 */
function buildScenarioAnswers(array $questionsDefinition, string $mode): array
{
    $answers = [];
    $blocks = $questionsDefinition['blocks'] ?? [];
    if (!is_array($blocks)) {
        throw new RuntimeException('La definición de preguntas no contiene bloques válidos.');
    }

    foreach ($blocks as $blockIndex => $block) {
        if (!is_array($block)) {
            continue;
        }

        $blockId = (string) ($block['id'] ?? '');
        $activities = $block['actividades'] ?? [];
        if ($blockId === '' || !is_array($activities) || count($activities) < 2) {
            continue;
        }

        $activityIds = array_values(array_map(
            static fn (array $activity): string => (string) ($activity['id'] ?? ''),
            array_filter($activities, 'is_array')
        ));

        if (count($activityIds) < 2) {
            continue;
        }

        if ($mode === 'rotating') {
            $masIndex = $blockIndex % count($activityIds);
            $menosIndex = ($masIndex + 1) % count($activityIds);
        } elseif ($mode === 'reverse') {
            $masIndex = count($activityIds) - 1;
            $menosIndex = 0;
        } else {
            $masIndex = 0;
            $menosIndex = 1;
        }

        if ($activityIds[$masIndex] === $activityIds[$menosIndex]) {
            $menosIndex = ($menosIndex + 1) % count($activityIds);
        }

        $answers[$blockId] = [
            'mas' => $activityIds[$masIndex],
            'menos' => $activityIds[$menosIndex],
        ];
    }

    return $answers;
}

function printCheck(string $label, bool $ok): void
{
    echo sprintf("[%s] %s\n", $ok ? 'OK' : 'FAIL', $label);
}

$root = dirname(__DIR__);
$argv = $_SERVER['argv'] ?? [];

if (parityHasFlag($argv, 'help')) {
    echo <<<HELP
Valida equivalencia estructural y funcional entre catálogo JSON y catálogo MySQL.

Uso:
  php scripts/validate_catalog_parity.php [--version-key=current-json] [--report=storage/logs/catalog_parity_report.json]

Opciones:
  --version-key  Versión MySQL a comparar. Default: config/app.php catalog.version_key o current-json.
  --report       Ruta del reporte JSON. Default: storage/logs/catalog_parity_report.json.
  --help         Muestra esta ayuda.

La conexión usa config/app.php y permite override por DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_CHARSET.

HELP;
    exit(0);
}

try {
    $appConfig = parityLoadConfig($root . '/config/app.php');
    $catalogConfig = is_array($appConfig['catalog'] ?? null) ? $appConfig['catalog'] : [];
    $versionKey = parityOptionValue($argv, 'version-key', (string) ($catalogConfig['version_key'] ?? 'current-json'));
    $reportPath = parityOptionValue($argv, 'report', $root . '/storage/logs/catalog_parity_report.json');
    if ($reportPath !== null && !str_starts_with($reportPath, '/')) {
        $reportPath = $root . '/' . $reportPath;
    }

    $jsonRepository = new JsonCatalogRepository($root . '/config/test-vocacional/catalog.php');
    $mysqlRepository = new MysqlCatalogRepository(
        PdoConnectionFactory::create(parityDatabaseConfig($appConfig)),
        $versionKey
    );

    $comparisons = [
        'scales' => [
            'json' => $jsonRepository->scalesConfig(),
            'mysql' => $mysqlRepository->scalesConfig(),
        ],
        'questions_blocks' => [
            'json' => $jsonRepository->questionsDefinition(),
            'mysql' => $mysqlRepository->questionsDefinition(),
        ],
        'scoring_rules' => [
            'json' => $jsonRepository->scoringRules(),
            'mysql' => $mysqlRepository->scoringRules(),
        ],
        'validity_rules' => [
            'json' => $jsonRepository->validityRules(),
            'mysql' => $mysqlRepository->validityRules(),
        ],
        'percentiles_M' => [
            'json' => $jsonRepository->percentilesBySex('M'),
            'mysql' => $mysqlRepository->percentilesBySex('M'),
        ],
        'percentiles_F' => [
            'json' => $jsonRepository->percentilesBySex('F'),
            'mysql' => $mysqlRepository->percentilesBySex('F'),
        ],
    ];

    $report = [
        'version_key' => $versionKey,
        'status' => 'pending',
        'structural' => [],
        'functional' => [],
        'summary' => [
            'structural_failures' => 0,
            'functional_failures' => 0,
        ],
    ];

    foreach ($comparisons as $name => $pair) {
        $jsonHash = hash('sha256', encodeForHash($pair['json']));
        $mysqlHash = hash('sha256', encodeForHash($pair['mysql']));
        $ok = $jsonHash === $mysqlHash;
        $diffs = $ok ? [] : diffValues(canonicalizeCatalogValue($pair['json']), canonicalizeCatalogValue($pair['mysql']));

        $report['structural'][$name] = [
            'ok' => $ok,
            'json_hash' => $jsonHash,
            'mysql_hash' => $mysqlHash,
            'diffs' => $diffs,
        ];
        if (!$ok) {
            $report['summary']['structural_failures']++;
        }

        printCheck('estructura ' . $name, $ok);
    }

    $scoreService = new ScoreService();
    $scenarios = [
        'first_two_M' => ['sex' => 'M', 'answers' => buildScenarioAnswers($jsonRepository->questionsDefinition(), 'first_two')],
        'rotating_M' => ['sex' => 'M', 'answers' => buildScenarioAnswers($jsonRepository->questionsDefinition(), 'rotating')],
        'reverse_F' => ['sex' => 'F', 'answers' => buildScenarioAnswers($jsonRepository->questionsDefinition(), 'reverse')],
        'sample_3_blocks_M' => ['sex' => 'M', 'answers' => [
            'B001' => ['mas' => 'A0001', 'menos' => 'A0002'],
            'B002' => ['mas' => 'A0004', 'menos' => 'A0005'],
            'B003' => ['mas' => 'A0007', 'menos' => 'A0008'],
        ]],
    ];

    foreach ($scenarios as $name => $scenario) {
        $sex = (string) $scenario['sex'];
        $answers = $scenario['answers'];

        $jsonResult = $scoreService->calculate(
            $answers,
            $jsonRepository->questionsDefinition(),
            $jsonRepository->scoringRules(),
            $jsonRepository->percentilesBySex($sex),
            $sex,
            $jsonRepository->validityRules()
        );

        $mysqlResult = $scoreService->calculate(
            $answers,
            $mysqlRepository->questionsDefinition(),
            $mysqlRepository->scoringRules(),
            $mysqlRepository->percentilesBySex($sex),
            $sex,
            $mysqlRepository->validityRules()
        );

        $fields = [
            'puntajes_brutos' => [$jsonResult['puntajes_brutos'] ?? null, $mysqlResult['puntajes_brutos'] ?? null],
            'percentiles' => [$jsonResult['percentiles'] ?? null, $mysqlResult['percentiles'] ?? null],
            'validez_puntaje' => [$jsonResult['validez_puntaje'] ?? null, $mysqlResult['validez_puntaje'] ?? null],
            'validez_estado' => [$jsonResult['validez_estado'] ?? null, $mysqlResult['validez_estado'] ?? null],
            'ranking' => [$jsonResult['escalas_ordenadas_de_mayor_a_menor'] ?? null, $mysqlResult['escalas_ordenadas_de_mayor_a_menor'] ?? null],
        ];

        $scenarioOk = true;
        $fieldReports = [];
        foreach ($fields as $field => [$jsonValue, $mysqlValue]) {
            $ok = canonicalizeCatalogValue($jsonValue) === canonicalizeCatalogValue($mysqlValue);
            $scenarioOk = $scenarioOk && $ok;
            $fieldReports[$field] = [
                'ok' => $ok,
                'diffs' => $ok ? [] : diffValues(canonicalizeCatalogValue($jsonValue), canonicalizeCatalogValue($mysqlValue)),
            ];
        }

        $report['functional'][$name] = [
            'ok' => $scenarioOk,
            'sex' => $sex,
            'answers_count' => count($answers),
            'fields' => $fieldReports,
        ];
        if (!$scenarioOk) {
            $report['summary']['functional_failures']++;
        }

        printCheck('funcional ' . $name, $scenarioOk);
    }

    $allOk = $report['summary']['structural_failures'] === 0 && $report['summary']['functional_failures'] === 0;
    $report['status'] = $allOk ? 'equivalent' : 'different';

    if ($reportPath === null) {
        throw new RuntimeException('Ruta de reporte inválida.');
    }

    $reportDirectory = dirname($reportPath);
    if (!is_dir($reportDirectory)) {
        mkdir($reportDirectory, 0775, true);
    }

    file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);

    echo sprintf("Reporte: %s\n", str_replace($root . '/', '', $reportPath));
    echo sprintf("Resultado: %s\n", $report['status']);

    exit($allOk ? 0 : 1);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Error validando paridad de catálogo: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

