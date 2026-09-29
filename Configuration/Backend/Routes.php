<?php

use Woit\T3ContentQuality\Controller\AiFileMetadataController;
use Woit\T3ContentQuality\Controller\AiTextGeneratorController;
use Woit\T3ContentQuality\Controller\AnalyzePageController;
use Woit\T3ContentQuality\Controller\BulkFixController;
use Woit\T3ContentQuality\Controller\FixPageController;
use Woit\T3ContentQuality\Controller\LinkCheckController;
use Woit\T3ContentQuality\Controller\PendingFixController;
use Woit\T3ContentQuality\Controller\SchemaAdvisorController;

return [
    't3contentquality_analyze' => [
        'path' => '/content-quality/analyze',
        'target' => AnalyzePageController::class . '::handleRequest',
        'access' => 'user',
    ],
    't3contentquality_fix' => [
        'path' => '/content-quality/fix',
        'target' => FixPageController::class . '::handleRequest',
        'access' => 'user',
    ],
    't3contentquality_schema_ai_detect' => [
        'path' => '/content-quality/schema/ai-detect',
        'target' => SchemaAdvisorController::class . '::aiDetectAction',
        'access' => 'user',
    ],
    't3contentquality_schema_approve' => [
        'path' => '/content-quality/schema/approve',
        'target' => SchemaAdvisorController::class . '::approveAction',
        'access' => 'user',
        'methods' => ['POST'],
    ],
    't3contentquality_schema_reject' => [
        'path' => '/content-quality/schema/reject',
        'target' => SchemaAdvisorController::class . '::rejectAction',
        'access' => 'user',
    ],
    't3contentquality_schema_export' => [
        'path' => '/content-quality/schema/export',
        'target' => SchemaAdvisorController::class . '::exportAction',
        'access' => 'user',
    ],
    't3contentquality_check_links' => [
        'path' => '/content-quality/check-links',
        'target' => LinkCheckController::class . '::handleRequest',
        'access' => 'user',
    ],
    't3contentquality_pending_apply' => [
        'path' => '/content-quality/pending/apply',
        'target' => PendingFixController::class . '::applyAction',
        'access' => 'user',
    ],
    't3contentquality_pending_discard' => [
        'path' => '/content-quality/pending/discard',
        'target' => PendingFixController::class . '::discardAction',
        'access' => 'user',
    ],
    't3contentquality_bulk_fix_generate' => [
        'path' => '/content-quality/bulk-fix/generate',
        'target' => BulkFixController::class . '::generateAction',
        'access' => 'user',
        'methods' => ['POST'],
    ],
    't3contentquality_ai_file_metadata' => [
        'path' => '/content-quality/ai-file-metadata',
        'target' => AiFileMetadataController::class . '::handleRequest',
        'access' => 'user',
    ],
];
