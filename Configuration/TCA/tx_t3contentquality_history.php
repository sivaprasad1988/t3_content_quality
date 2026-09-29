<?php

return [
    'ctrl' => [
        'title' => 'Content Quality History',
        'label' => 'page_uid',
        'crdate' => 'analyzed_at',
        'hideTable' => true,
        'rootLevel' => -1,
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'columns' => [
        'page_uid' => [
            'config' => ['type' => 'passthrough'],
        ],
        'language_uid' => [
            'config' => ['type' => 'passthrough'],
        ],
        'overall_score' => [
            'config' => ['type' => 'passthrough'],
        ],
        'accessibility_score' => [
            'config' => ['type' => 'passthrough'],
        ],
        'seo_score' => [
            'config' => ['type' => 'passthrough'],
        ],
        'readability_score' => [
            'config' => ['type' => 'passthrough'],
        ],
        'schema_score' => [
            'config' => ['type' => 'passthrough'],
        ],
        'analyzed_at' => [
            'config' => ['type' => 'passthrough'],
        ],
    ],
    'types' => [
        '1' => ['showitem' => 'page_uid, overall_score, analyzed_at'],
    ],
];
