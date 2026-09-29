<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Schema;

class JsonLdGenerator
{
    public function generate(string $schemaType, array $mappedData): array
    {
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => $schemaType,
        ];

        foreach ($mappedData as $key => $value) {
            if ($value !== null && $value !== '' && $value !== []) {
                $jsonLd[$key] = $value;
            }
        }

        return $jsonLd;
    }

    public function toJson(array $jsonLd): string
    {
        return (string)json_encode($jsonLd, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Returns list of structural validation error messages */
    public function validateJsonLd(array $jsonLd): array
    {
        $errors = [];

        if (($jsonLd['@context'] ?? '') !== 'https://schema.org') {
            $errors[] = '@context must be "https://schema.org"';
        }

        if (empty($jsonLd['@type'])) {
            $errors[] = '@type is required';
        }

        foreach (['startDate', 'endDate', 'datePublished', 'dateModified', 'datePosted', 'validThrough'] as $field) {
            if (isset($jsonLd[$field]) && !$this->isValidIso8601((string)$jsonLd[$field])) {
                $errors[] = sprintf('"%s" must be ISO 8601 format (e.g. 2024-01-15T10:00:00+01:00)', $field);
            }
        }

        return $errors;
    }

    private function isValidIso8601(string $value): bool
    {
        return (bool)preg_match(
            '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?([+-]\d{2}:\d{2}|Z)?)?$/',
            $value
        );
    }
}
