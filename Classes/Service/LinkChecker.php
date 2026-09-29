<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Service;

use TYPO3\CMS\Core\Http\RequestFactory;

class LinkChecker
{
    private const TIMEOUT = 5;
    private const MAX_LINKS = 30;

    public function __construct(
        private readonly RequestFactory $requestFactory,
    ) {}

    /**
     * @param array<int, array<string, mixed>> $links
     * @return array<int, array{url:string,status:int,ok:bool}>
     */
    public function checkLinks(array $links, string $siteBaseUrl): array
    {
        $seen = [];
        $results = [];

        foreach ($links as $link) {
            $href = trim((string)($link['href'] ?? ''));
            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }

            $url = $this->resolveUrl($href, $siteBaseUrl);
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;

            if (count($results) >= self::MAX_LINKS) {
                break;
            }

            $results[] = $this->checkUrl($url);
        }

        return $results;
    }

    private function resolveUrl(string $href, string $siteBaseUrl): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }
        if ($siteBaseUrl === '') {
            return '';
        }
        return rtrim($siteBaseUrl, '/') . '/' . ltrim($href, '/');
    }

    private function checkUrl(string $url): array
    {
        try {
            $response = $this->requestFactory->request($url, 'HEAD', [
                'timeout' => self::TIMEOUT,
                'connect_timeout' => self::TIMEOUT,
                'allow_redirects' => true,
            ]);
            $status = $response->getStatusCode();

            if ($status >= 400) {
                // Some servers reject HEAD but allow GET
                $response = $this->requestFactory->request($url, 'GET', [
                    'timeout' => self::TIMEOUT,
                    'connect_timeout' => self::TIMEOUT,
                    'allow_redirects' => true,
                ]);
                $status = $response->getStatusCode();
            }

            return ['url' => $url, 'status' => $status, 'ok' => $status < 400];
        } catch (\Throwable) {
            return ['url' => $url, 'status' => 0, 'ok' => false];
        }
    }
}
