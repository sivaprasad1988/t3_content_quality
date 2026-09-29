<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Middleware;

use Woit\T3ContentQuality\Domain\Repository\ApprovedSchemaRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\StreamFactory;

class JsonLdInjector implements MiddlewareInterface
{
    public function __construct(
        private readonly ApprovedSchemaRepository $approvedSchemaRepository,
        private readonly StreamFactory $streamFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $routing = $request->getAttribute('routing');
        $pageUid = $routing?->getPageId() ?? 0;

        if ($pageUid === 0) {
            return $response;
        }

        $languageUid = (int)($request->getAttribute('language')?->getLanguageId() ?? 0);
        $approvedJsonLd = $this->approvedSchemaRepository->findJsonLd($pageUid, $languageUid);

        if ($approvedJsonLd === null) {
            return $response;
        }

        $contentType = $response->getHeaderLine('Content-Type');
        if (!str_contains($contentType, 'text/html')) {
            return $response;
        }

        $safeJsonLd = self::encodeForScriptTag($approvedJsonLd);
        if ($safeJsonLd === null) {
            return $response;
        }

        $body = (string)$response->getBody();
        $tag = '<script type="application/ld+json">' . "\n" . $safeJsonLd . "\n" . '</script>';

        $headEnd = stripos($body, '</head>');
        if ($headEnd === false) {
            return $response;
        }

        $modifiedBody = substr_replace($body, $tag . "\n", $headEnd, 0);

        return $response->withBody($this->streamFactory->createStream($modifiedBody));
    }

    /**
     * Re-encodes stored JSON-LD so it can not break out of the <script> element:
     * JSON_HEX_TAG turns "<" and ">" into \u003C / \u003E, so a value like
     * "</script><script>…" stays inert. Returns null for invalid JSON.
     */
    public static function encodeForScriptTag(string $jsonLd): ?string
    {
        try {
            $decoded = json_decode($jsonLd, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($decoded)) {
            return null;
        }

        return json_encode(
            $decoded,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        ) ?: null;
    }
}
