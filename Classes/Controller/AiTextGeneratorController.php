<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Controller;

use Woit\T3ContentQuality\Service\AiProviderClient;
use Woit\T3ContentQuality\Service\AiSettingsFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\SiteFinder;

class AiTextGeneratorController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly AiProviderClient $aiProviderClient,
        private readonly AiSettingsFactory $aiSettingsFactory,
        private readonly SiteFinder $siteFinder,
    ) {}

    public function generateAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = json_decode((string)$request->getBody(), true) ?? [];

        $prompt      = trim((string)($body['prompt'] ?? ''));
        $maxChars    = max(50, min(5000, (int)($body['maxChars'] ?? 500)));
        $tone        = $this->sanitizeTone((string)($body['tone'] ?? 'neutral'));
        $language    = $this->sanitizeLanguage((string)($body['language'] ?? 'auto'));
        $format      = $this->sanitizeFormat((string)($body['format'] ?? 'paragraph'));
        $languageUid = isset($body['languageUid']) ? (int)$body['languageUid'] : null;

        if ($language === 'auto' && $languageUid !== null) {
            $language = $this->resolveLanguageFromUid($languageUid) ?? 'auto';
        }

        if ($prompt === '') {
            return new JsonResponse(['error' => 'Prompt is required.'], 400);
        }

        $aiSettings = $this->aiSettingsFactory->create();

        if (!$aiSettings->isUsable()) {
            return new JsonResponse(['error' => 'No AI API key configured. Check extension settings.'], 500);
        }

        try {
            $fullPrompt = $this->buildPrompt($prompt, $maxChars, $tone, $language, $format);
            $text = $this->aiProviderClient->call($fullPrompt, $aiSettings);
            return new JsonResponse(['text' => $text]);
        } catch (\Throwable $e) {
            $this->logger?->error('AI text generation failed', ['exception' => $e->getMessage()]);
            return new JsonResponse(['error' => 'AI request failed: ' . $e->getMessage()], 500);
        }
    }

    private function buildPrompt(string $prompt, int $maxChars, string $tone, string $language, string $format): string
    {
        $langInstruction = $language !== 'auto'
            ? "Write in {$language}."
            : 'Write in the same language as the topic/prompt implies.';

        $formatInstruction = match ($format) {
            'bullets' => 'Format the response as bullet points.',
            'headline' => 'Write a single short headline only.',
            default    => 'Write as one or more paragraphs of flowing text.',
        };

        return <<<PROMPT
You are a professional copywriter assistant for a web CMS.

Task: {$prompt}

Requirements:
- {$langInstruction}
- Tone: {$tone}
- {$formatInstruction}
- Target length: around {$maxChars} characters. Do NOT exceed {$maxChars} characters. Aim for 80–90% of the limit to avoid truncation.
- Return ONLY the generated text. No preamble, no explanation, no quotes around the text.
PROMPT;
    }

    private function sanitizeTone(string $tone): string
    {
        return in_array($tone, ['formal', 'neutral', 'friendly', 'professional'], true) ? $tone : 'neutral';
    }

    private function sanitizeLanguage(string $language): string
    {
        return in_array($language, ['auto', 'German', 'English', 'French', 'Spanish'], true) ? $language : 'auto';
    }

    private function resolveLanguageFromUid(int $languageUid): ?string
    {
        static $languageNames = [
            'de' => 'German', 'en' => 'English', 'fr' => 'French',
            'es' => 'Spanish', 'it' => 'Italian', 'nl' => 'Dutch',
            'pl' => 'Polish', 'pt' => 'Portuguese', 'ru' => 'Russian',
            'tr' => 'Turkish', 'cs' => 'Czech', 'sk' => 'Slovak',
            'ro' => 'Romanian', 'hu' => 'Hungarian', 'sv' => 'Swedish',
            'da' => 'Danish', 'fi' => 'Finnish', 'nb' => 'Norwegian',
        ];

        try {
            foreach ($this->siteFinder->getAllSites() as $site) {
                try {
                    $siteLanguage = $site->getLanguageById($languageUid);
                    $code = $siteLanguage->getLocale()->getLanguageCode();
                    return $languageNames[$code] ?? ucfirst($code) ?: null;
                } catch (\Throwable) {
                    continue;
                }
            }
        } catch (\Throwable) {}

        return null;
    }

    private function sanitizeFormat(string $format): string
    {
        return in_array($format, ['paragraph', 'bullets', 'headline'], true) ? $format : 'paragraph';
    }
}
