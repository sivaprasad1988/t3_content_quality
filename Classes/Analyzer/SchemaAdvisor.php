<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Analyzer;

use Woit\T3ContentQuality\Service\AiSettings;
use Woit\T3ContentQuality\Domain\Model\AnalysisResult;
use Woit\T3ContentQuality\Schema\JsonLdGenerator;
use Woit\T3ContentQuality\Schema\PageIntentDetector;
use Woit\T3ContentQuality\Schema\SchemaMapper;
use Woit\T3ContentQuality\Schema\SchemaTypes;
use Woit\T3ContentQuality\Schema\SchemaValidator;

class SchemaAdvisor
{
    public function __construct(
        private readonly PageIntentDetector $intentDetector,
        private readonly SchemaMapper $mapper,
        private readonly SchemaValidator $validator,
        private readonly JsonLdGenerator $generator,
        private readonly AiSchemaDetector $aiSchemaDetector,
    ) {}

    public function analyze(
        array $pageData,
        AnalysisResult $result,
        ?AiSettings $aiSettings = null,
    ): void {
        $aiResult = null;

        if ($aiSettings !== null && $aiSettings->isUsable()) {
            $aiResult = $this->aiSchemaDetector->detect($pageData, $aiSettings);
        }

        if ($aiResult !== null) {
            $schemaType  = $aiResult['type'];
            $mappedData  = $aiResult['fields'];
            $detectedBy  = 'ai';
        } else {
            $schemaType = $this->intentDetector->detect($pageData);
            if ($schemaType === null) {
                $result->schemaScore = 0;
                $result->metadata['schema'] = [
                    'detected_type'    => null,
                    'detected_by'      => null,
                    'mapped_data'      => [],
                    'jsonld'           => null,
                    'jsonld_preview'   => null,
                    'validation_errors'=> [],
                ];
                return;
            }
            $mappedData = $this->mapper->map($schemaType, $pageData);
            $detectedBy = 'rules';
        }

        $this->validator->validate($schemaType, $mappedData, $result);

        $jsonLd           = $this->generator->generate($schemaType, $mappedData);
        $validationErrors = $this->generator->validateJsonLd($jsonLd);

        $result->schemaScore = $this->validator->calculateScore($schemaType, $mappedData);

        $result->metadata['schema'] = [
            'detected_type'     => $schemaType,
            'detected_by'       => $detectedBy,
            'mapped_data'       => $mappedData,
            'jsonld'            => $jsonLd,
            'jsonld_preview'    => $this->generator->toJson($jsonLd),
            'validation_errors' => $validationErrors,
        ];
    }
}
