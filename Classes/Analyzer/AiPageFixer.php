<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Analyzer;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Woit\T3ContentQuality\Service\AiProviderClient;
use Woit\T3ContentQuality\Service\AiSettings;

class AiPageFixer implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly AiProviderClient $aiProviderClient,
    ) {}

    public function generateFixes(
        array $pageData,
        array $issues,
        AiSettings $settings,
    ): array {
        $prompt = $this->buildPrompt($pageData, $issues);
        $text = $this->aiProviderClient->call($prompt, $settings);
        $fixes = $this->parseJson($text);

        return array_intersect_key($fixes, array_flip(['title', 'description']));
    }

    public function generateAltTexts(
        array $images,
        array $pageData,
        AiSettings $settings,
    ): array {
        if (empty($images)) {
            return [];
        }

        $prompt = $this->buildAltTextPrompt($images, $pageData);
        $text = $this->aiProviderClient->call($prompt, $settings);

        return $this->parseAltTextLines($text);
    }

    public function parseAltTextLines(string $text): array
    {
        $result = [];
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if (preg_match('/^(\d+)[|:]\s*(.+)$/', $line, $m)) {
                $result[(int)$m[1]] = trim($m[2]);
            }
        }
        return $result;
    }

    public function parseJson(string $text): array
    {
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/\s*```$/m', '', $text);
        $text = trim($text);

        // Extract only the JSON object — strip any trailing text after closing }
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $text = substr($text, $start, $end - $start + 1);
        }

        // Fix unquoted integer keys anywhere: {  1: or ,  2: → {"1": or ,"2":
        $text = preg_replace('/([\{,])\s*(\d+)\s*:/', '$1"$2":', $text);

        $decoded = json_decode($text, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('AI returned invalid JSON: ' . $text);
        }

        return $decoded;
    }

    private function buildPrompt(array $pageData, array $issues): string
    {
        $title = $pageData['page_title'] ?? '';
        $meta = $pageData['meta_description'] ?? '';
        $textSnippet = mb_substr(trim($pageData['text_content'] ?? ''), 0, 1500);

        $issueList = implode(
            "\n",
            array_map(static fn($i) => '- ' . ($i['message'] ?? ''), $issues)
        );

        return <<<PROMPT
You are an SEO expert. Fix the SEO metadata for this page.

Current page title: {$title}
Current meta description: {$meta}

Detected issues:
{$issueList}

Page text excerpt:
{$textSnippet}

Rules:
- Write in the SAME language as the existing content (if German, write German)
- title: 30–60 characters, compelling, includes main keyword
- description: 120–160 characters, clear summary, includes call to action if appropriate
- If the existing value is already good, return it unchanged

Return ONLY a JSON object with these two keys, nothing else:
{"title": "...", "description": "..."}
PROMPT;
    }

    private function buildAltTextPrompt(array $images, array $pageData): string
    {
        $pageTitle = $pageData['page_title'] ?? '';
        $textSnippet = mb_substr(trim($pageData['text_content'] ?? ''), 0, 500);

        $imageList = '';
        foreach ($images as $img) {
            $imageList .= sprintf(
                "- uid: %d, filename: \"%s\", title: \"%s\", caption: \"%s\"\n",
                $img['uid'],
                $img['filename'] ?? '',
                $img['title'] ?? '',
                $img['caption'] ?? ''
            );
        }

        return <<<PROMPT
You are a web accessibility expert. Generate descriptive ALT text for images on a webpage.

Page title: {$pageTitle}
Page context: {$textSnippet}

Images missing ALT text:
{$imageList}

Rules:
- Write in the SAME language as the page title/context (if German, write German)
- ALT text must be concise (under 120 characters)
- Describe what is visually in the image based on filename/title/caption clues
- Do not start with "Image of", "Picture of", "Bild von", or "Foto von"

Return ONLY lines in this exact format, one per image, nothing else:
{uid}| {alt text}

Example:
42| Moderne Wohnanlage mit grüner Außenfläche
87| Seniorengerechte Küche mit heller Ausstattung
PROMPT;
    }
}
