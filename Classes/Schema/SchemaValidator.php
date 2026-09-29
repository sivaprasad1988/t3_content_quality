<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Schema;

use Woit\T3ContentQuality\Domain\Model\AnalysisIssue;
use Woit\T3ContentQuality\Domain\Model\AnalysisResult;

class SchemaValidator
{
    public function validate(string $schemaType, array $mappedData, AnalysisResult $result): void
    {
        foreach (SchemaTypes::REQUIRED[$schemaType] ?? [] as $field) {
            if ($this->isMissing($mappedData, $field)) {
                $result->addIssue(new AnalysisIssue(
                    AnalysisIssue::CATEGORY_SCHEMA,
                    AnalysisIssue::SEVERITY_ERROR,
                    sprintf('Schema.org %s: required field "%s" is missing.', $schemaType, $field),
                    sprintf('Add a value for "%s" to satisfy schema.org requirements.', $field)
                ));
            }
        }

        foreach (SchemaTypes::RECOMMENDED[$schemaType] ?? [] as $field) {
            if ($this->isMissing($mappedData, $field)) {
                $result->addIssue(new AnalysisIssue(
                    AnalysisIssue::CATEGORY_SCHEMA,
                    AnalysisIssue::SEVERITY_WARNING,
                    sprintf('Schema.org %s: recommended field "%s" is missing.', $schemaType, $field),
                    sprintf('Adding "%s" improves rich result eligibility.', $field)
                ));
            }
        }
    }

    public function calculateScore(string $schemaType, array $mappedData): int
    {
        $penalty = 0;

        foreach (SchemaTypes::REQUIRED[$schemaType] ?? [] as $field) {
            if ($this->isMissing($mappedData, $field)) {
                $penalty += 25;
            }
        }

        foreach (SchemaTypes::RECOMMENDED[$schemaType] ?? [] as $field) {
            if ($this->isMissing($mappedData, $field)) {
                $penalty += 5;
            }
        }

        return max(0, 100 - $penalty);
    }

    private function isMissing(array $data, string $field): bool
    {
        if (!array_key_exists($field, $data)) {
            return true;
        }
        $value = $data[$field];
        if (is_string($value)) {
            return trim($value) === '';
        }
        if (is_array($value)) {
            return empty($value);
        }
        return $value === null;
    }
}
