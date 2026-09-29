<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Analyzer;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Woit\T3ContentQuality\Service\AiProviderClient;
use Woit\T3ContentQuality\Service\AiSettings;

class AiImageMetadataAnalyzer implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly AiProviderClient $aiProviderClient,
    ) {}

    /**
     * Generate image metadata via AI vision.
     * Returns array with any subset of: alternative, title, description, caption
     */
    public function generateMetadata(
        string $imageBase64,
        string $mimeType,
        array $missingFields,
        AiSettings $settings,
    ): array {
        if (!$settings->isUsable()) {
            return [];
        }

        try {
            $text = $this->aiProviderClient->callVision($this->buildPrompt($missingFields), $imageBase64, $mimeType, $settings);
            return $this->parseJsonResponse($text);
        } catch (\Throwable $e) {
            $this->logger?->error('AI image metadata generation failed', ['exception' => $e->getMessage()]);
            return [];
        }
    }

    private function buildPrompt(array $missingFields): string
    {
        $fieldDescriptions = [
            'alternative' => 'alternative (alt text): short, descriptive text for screen readers, max 125 chars',
            'title'       => 'title: brief, descriptive file title, max 80 chars',
            'description' => 'description: longer description of image content, max 250 chars',
            'caption'     => 'caption: editorial caption suitable for display below the image, max 200 chars',
        ];

        $needed = array_intersect_key($fieldDescriptions, array_flip($missingFields));
        $fieldsList = implode("\n", array_map(
            static fn($k, $v) => "- $k: $v",
            array_keys($needed),
            $needed
        ));

        return <<<PROMPT
Analyze this image and generate ONLY the following metadata in German language:
{$fieldsList}

Return ONLY a valid JSON object with exactly these field keys. Example:
{"alternative": "Bagger auf einer Baustelle in der Stadt"}

Return only the JSON object, no other text.
PROMPT;
    }

    public function parseJsonResponse(string $text): array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/m', '', $text);

        $result = json_decode(trim($text), true);
        if (!is_array($result)) {
            return [];
        }

        $allowed = ['alternative', 'title', 'description', 'caption'];
        return array_filter(
            array_intersect_key($result, array_flip($allowed)),
            static fn($v) => is_string($v) && trim($v) !== ''
        );
    }
}
