<?php

return [
    'ctrl' => [
        'title' => 'Content Quality Pending Fix',
        'label' => 'page_uid',
        'crdate' => 'created_at',
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
        'fix_type' => [
            'config' => ['type' => 'passthrough'],
        ],
        'target_ref' => [
            'config' => ['type' => 'passthrough'],
        ],
        'old_value' => [
            'config' => ['type' => 'passthrough'],
        ],
        'new_value' => [
            'config' => ['type' => 'passthrough'],
        ],
        'created_at' => [
            'config' => ['type' => 'passthrough'],
        ],
    ],
    'types' => [
        '1' => ['showitem' => 'page_uid, fix_type, created_at'],
    ],
];
