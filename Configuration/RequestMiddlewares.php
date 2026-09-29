<?php

declare(strict_types=1);

use Woit\T3ContentQuality\Middleware\JsonLdInjector;

return [
    'frontend' => [
        'woit/t3-content-quality/json-ld-injector' => [
            'target' => JsonLdInjector::class,
            'after' => ['typo3/cms-frontend/output-compression'],
            'before' => ['typo3/cms-frontend/send-response'],
        ],
    ],
];
