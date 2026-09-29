<?php

declare(strict_types=1);

defined('TYPO3') or die();

$GLOBALS['TCA']['pages']['columns']['tx_t3contentquality_schema_type'] = [
    'label' => 'LLL:EXT:t3_content_quality/Resources/Private/Language/locallang.xlf:field.schemaType.label',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'items' => [
            ['label' => 'LLL:EXT:t3_content_quality/Resources/Private/Language/locallang.xlf:field.schemaType.autoDetect', 'value' => ''],
            ['label' => 'LLL:EXT:t3_content_quality/Resources/Private/Language/locallang.xlf:field.schemaType.event', 'value' => 'Event'],
            ['label' => 'LLL:EXT:t3_content_quality/Resources/Private/Language/locallang.xlf:field.schemaType.newsArticle', 'value' => 'NewsArticle'],
            ['label' => 'LLL:EXT:t3_content_quality/Resources/Private/Language/locallang.xlf:field.schemaType.touristAttraction', 'value' => 'TouristAttraction'],
            ['label' => 'LLL:EXT:t3_content_quality/Resources/Private/Language/locallang.xlf:field.schemaType.localBusiness', 'value' => 'LocalBusiness'],
            ['label' => 'LLL:EXT:t3_content_quality/Resources/Private/Language/locallang.xlf:field.schemaType.organization', 'value' => 'Organization'],
            ['label' => 'LLL:EXT:t3_content_quality/Resources/Private/Language/locallang.xlf:field.schemaType.faqPage', 'value' => 'FAQPage'],
            ['label' => 'LLL:EXT:t3_content_quality/Resources/Private/Language/locallang.xlf:field.schemaType.jobPosting', 'value' => 'JobPosting'],
            ['label' => 'LLL:EXT:t3_content_quality/Resources/Private/Language/locallang.xlf:field.schemaType.product', 'value' => 'Product'],
        ],
        'default' => '',
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette(
    'pages',
    'metatags',
    'tx_t3contentquality_schema_type'
);
