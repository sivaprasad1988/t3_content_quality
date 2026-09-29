<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;
use Woit\T3ContentQuality\Service\AiProviderClient;
use Woit\T3ContentQuality\Service\AiSettings;
use Woit\T3ContentQuality\Service\OllamaEndpoint;

final class AiProviderClientTest extends TestCase
{
    #[Test]
    public function anthropicRequestForSonnet55SendsEffortAndDefaultFallback(): void
    {
        $request = $this->client()->buildAnthropicRequest(
            [['type' => 'text', 'text' => 'Hi']],
            $this->anthropic('claude-sonnet-5-5')
        );

        self::assertSame('claude-sonnet-5-5', $request['body']['model']);
        self::assertSame(['effort' => 'low'], $request['body']['output_config']);
        self::assertSame('default', $request['body']['fallbacks']);
        self::assertSame(AiProviderClient::ANTHROPIC_FALLBACK_BETA, $request['headers']['anthropic-beta']);
        self::assertSame('2023-06-01', $request['headers']['anthropic-version']);
        self::assertArrayNotHasKey('thinking', $request['body']);
    }

    #[Test]
    public function anthropicRequestForHaikuOmitsEffortAndFallback(): void
    {
        $request = $this->client()->buildAnthropicRequest(
            [['type' => 'text', 'text' => 'Hi']],
            $this->anthropic('claude-haiku-4-5')
        );

        self::assertArrayNotHasKey('output_config', $request['body']);
        self::assertArrayNotHasKey('fallbacks', $request['body']);
        self::assertArrayNotHasKey('anthropic-beta', $request['headers']);
    }

    #[Test]
    public function responseTextIsReadByBlockTypeNotPosition(): void
    {
        $text = $this->client()->parseAnthropicResponse([
            'stop_reason' => 'end_turn',
            'content' => [
                ['type' => 'thinking', 'thinking' => ''],
                ['type' => 'text', 'text' => ' {"title":"A"} '],
            ],
        ]);

        self::assertSame('{"title":"A"}', $text);
    }

    #[Test]
    public function refusalIsReportedWithCategory(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('general_harms');

        $this->client()->parseAnthropicResponse([
            'stop_reason' => 'refusal',
            'stop_details' => ['type' => 'refusal', 'category' => 'general_harms'],
            'content' => [],
        ]);
    }

    #[Test]
    public function maxTokensWithoutTextAsksForHigherLimit(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Increase maxTokens');

        $this->client()->parseAnthropicResponse([
            'stop_reason' => 'max_tokens',
            'content' => [['type' => 'thinking', 'thinking' => '']],
        ]);
    }

    #[Test]
    public function callSendsRequestToAnthropicAndReturnsText(): void
    {
        $captured = [];
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->method('request')->willReturnCallback(
            function (string $url, string $method, array $options) use (&$captured): Response {
                $captured = ['url' => $url, 'method' => $method, 'options' => $options];
                $response = new Response();
                $response->getBody()->write((string)json_encode([
                    'stop_reason' => 'end_turn',
                    'content' => [['type' => 'text', 'text' => 'Hello']],
                ]));
                return $response;
            }
        );

        $client = new AiProviderClient($requestFactory, $this->ollamaEndpoint());
        $text = $client->call('Say hello', $this->anthropic('claude-sonnet-5-5'));

        self::assertSame('Hello', $text);
        self::assertSame(AiProviderClient::ANTHROPIC_URL, $captured['url']);
        self::assertSame('POST', $captured['method']);
        self::assertSame('secret', $captured['options']['headers']['x-api-key']);
        self::assertSame('Say hello', json_decode($captured['options']['body'], true)['messages'][0]['content'][0]['text']);
    }

    #[Test]
    public function httpErrorBecomesRuntimeException(): void
    {
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->method('request')->willReturn(new Response('php://temp', 401));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('returned 401');

        (new AiProviderClient($requestFactory, $this->ollamaEndpoint()))->call('x', $this->anthropic('claude-sonnet-5-5'));
    }

    #[Test]
    public function ollamaVisionSendsImageToConfiguredServer(): void
    {
        $captured = [];
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->method('request')->willReturnCallback(
            function (string $url, string $method, array $options) use (&$captured): Response {
                $captured = ['url' => $url, 'body' => json_decode($options['body'], true)];
                $response = new Response();
                $response->getBody()->write('{"message":{"content":"{\"title\":\"T\"}"}}');
                return $response;
            }
        );

        $client = new AiProviderClient($requestFactory, $this->ollamaEndpoint('http://localhost:11434/'));
        $text = $client->callVision('Describe', 'QUJD', 'image/png', new AiSettings('ollama', '', 'llava', 1000));

        self::assertSame('{"title":"T"}', $text);
        self::assertSame('http://localhost:11434/api/chat', $captured['url']);
        self::assertSame(['QUJD'], $captured['body']['messages'][0]['images']);
        self::assertSame('json', $captured['body']['format']);
    }

    private function anthropic(string $model): AiSettings
    {
        return new AiSettings('anthropic', 'secret', $model, 16000, true, 'low', true);
    }

    private function client(): AiProviderClient
    {
        return new AiProviderClient($this->createMock(RequestFactory::class), $this->ollamaEndpoint());
    }

    private function ollamaEndpoint(string $url = ''): OllamaEndpoint
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn($url);
        return new OllamaEndpoint($extensionConfiguration);
    }
}
