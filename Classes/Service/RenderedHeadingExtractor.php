<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Service;

use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Extracts the real heading structure (H1-H6) from the page's actual
 * rendered frontend HTML, rather than guessing it from tt_content's
 * header_layout field - custom content element templates (container,
 * service, and counter CTypes in this project, among others) frequently
 * ignore header_layout entirely and render the header field inside a
 * styled <div>/<span>, never a semantic heading tag.
 */
class RenderedHeadingExtractor
{
    public function __construct(
        private readonly RequestFactory $requestFactory,
    ) {}

    /**
     * @return array<int, array{level:int, text:string}>|null null when the
     * page could not be fetched (empty URL, offline, non-2xx response) -
     * callers should fall back to a DB-derived heading guess in that case.
     */
    public function extract(string $pageUrl): ?array
    {
        if ($pageUrl === '') {
            return null;
        }

        try {
            $response = $this->requestFactory->request($pageUrl, 'GET', ['timeout' => 10]);
            if ($response->getStatusCode() >= 400) {
                return null;
            }
            $html = (string)$response->getBody();
        } catch (\Throwable) {
            return null;
        }

        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER);

        $headings = [];
        foreach ($matches as $match) {
            $text = trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES));
            if ($text === '') {
                continue;
            }
            $headings[] = ['level' => (int)$match[1], 'text' => $text];
        }

        return $headings;
    }
}
