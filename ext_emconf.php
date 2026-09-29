<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'AI Content Quality Assistant',
    'description' => 'AI-powered accessibility, SEO, and readability checks for TYPO3 editors. Analyzes pages and provides concrete improvement suggestions.',
    'category' => 'be',
    'state' => 'beta',
    'author' => 'Sivaprasad Sisupalan',
    'author_email' => 'sivaprasad.s88@gmail.com',
    'version' => '0.1.1',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.99.99',
            'typo3' => '14.0.0-14.99.99',
            'extbase' => '',
            'fluid' => '',
            'filelist' => '',
            'filemetadata' => '',
        ],
        'conflicts' => [],
        'suggests' => [
            'rte_ckeditor' => '',
        ],
    ],
];
