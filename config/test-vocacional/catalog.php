<?php

declare(strict_types=1);

return [
    'base_path' => __DIR__,
    'files' => [
        'test' => __DIR__ . '/test.json',
        'scales' => __DIR__ . '/scales.json',
        'questions_blocks' => __DIR__ . '/questions_blocks.json',
        'scoring_rules' => __DIR__ . '/scoring_rules.json',
        'validity_rules' => __DIR__ . '/validity_rules.json',
        'excel_mapping' => __DIR__ . '/excel_mapping.json',
        'percentiles' => [
            'M' => __DIR__ . '/percentiles/male.json',
            'F' => __DIR__ . '/percentiles/female.json',
        ],
    ],
];
