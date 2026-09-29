<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Routing\PageArguments;
use Woit\T3ContentQuality\Domain\Repository\ApprovedSchemaRepository;
use Woit\T3ContentQuality\Middleware\JsonLdInjector;

final class JsonLdInjectorTest extends TestCase
{
    #[Test]
    public function encodeForScriptTagNeutralisesClosingScriptTags(): void
    {
        $json = (string)json_encode(['@type' => 'WebPage', 'name' => '</script><script>alert(1)</script>']);

        $encoded = JsonLdInjector::encodeForScriptTag($json);

        self::assertIsString($encoded);
        self::assertStringNotContainsStringIgnoringCase('</script', $encoded);
        self::assertSame('</script><script>alert(1)</script>', json_decode($encoded, true)['name']);
    }

    #[Test]
    public function encodeForScriptTagRejectsInvalidOrScalarJson(): void
    {
        self::assertNull(JsonLdInjector::encodeForScriptTag('not json'));
        self::assertNull(JsonLdInjector::encodeForScriptTag('"just a string"'));
    }

    #[Test]
    public function injectsApprovedSchemaOnceBeforeFirstHeadEnd(): void
    {
        $body = '<html><head><title>x</title></head><body>literal </head> in text</body></html>';
        $response = $this->process('{"@type":"WebPage","name":"A"}', $body, 'text/html; charset=utf-8');

        $html = (string)$response->getBody();
        self::assertSame(1, substr_count($html, 'application/ld+json'));
        self::assertLessThan(strpos($html, '</head>'), strpos($html, 'application/ld+json'));
        self::assertStringContainsString('literal </head> in text', $html);
    }

    #[Test]
    public function leavesNonHtmlResponsesUntouched(): void
    {
        $response = $this->process('{"@type":"WebPage"}', '{"a":1}', 'application/json');

        self::assertSame('{"a":1}', (string)$response->getBody());
    }

    #[Test]
    public function leavesPagesWithoutApprovedSchemaUntouched(): void
    {
        $response = $this->process(null, '<head></head>', 'text/html');

        self::assertSame('<head></head>', (string)$response->getBody());
    }

    private function process(?string $approved, string $body, string $contentType): ResponseInterface
    {
        $repository = $this->createMock(ApprovedSchemaRepository::class);
        $repository->method('findJsonLd')->willReturn($approved);

        $response = new Response();
        $response->getBody()->write($body);
        $response = $response->withHeader('Content-Type', $contentType);

        $handler = new class ($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $request = (new ServerRequest('https://example.org/'))
            ->withAttribute('routing', new PageArguments(42, '0', []));

        return (new JsonLdInjector($repository, new StreamFactory()))->process($request, $handler);
    }
}
