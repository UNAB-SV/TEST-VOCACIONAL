<?php

declare(strict_types=1);

$script = dirname(__DIR__) . '/scripts/validate_catalog_parity.php';
$command = 'php ' . escapeshellarg($script) . ' --report=' . escapeshellarg(dirname(__DIR__) . '/storage/logs/catalog_parity_test_report.json');

passthru($command, $exitCode);

if ($exitCode !== 0) {
    throw new RuntimeException('CatalogParityTest falló. Revisar storage/logs/catalog_parity_test_report.json.');
}

echo "CatalogParityTest OK\n";

