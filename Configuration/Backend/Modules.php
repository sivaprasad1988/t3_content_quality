<?php

use Woit\T3ContentQuality\Controller\BackendModuleController;

return [
    'web_t3contentquality' => [
        'parent' => 'web',
        'position' => ['after' => 'web_info'],
        'access' => 'user',
        'path' => '/module/page/content-quality',
        'iconIdentifier' => 'actions-document',
        'labels' => [
            'title' => 'LLL:EXT:t3_content_quality/Resources/Private/Language/locallang.xlf:module.title',
            'description' => 'LLL:EXT:t3_content_quality/Resources/Private/Language/locallang.xlf:module.description',
        ],
        'extensionName' => 'T3ContentQuality',
        'controllerActions' => [
            BackendModuleController::class => ['index', 'analyze', 'list'],
        ],
    ],
];
