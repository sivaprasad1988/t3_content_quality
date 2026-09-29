<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use Woit\T3ContentQuality\Service\AiSettingsFactory;

final class AiSettingsFactoryTest extends TestCase
{
    #[Test]
    public function emptyConfigurationFallsBackToAnthropicDefaults(): void
    {
        $settings = $this->factory([])->create();

        self::assertSame('anthropic', $settings->provider);
        self::assertSame(AiSettingsFactory::DEFAULT_ANTHROPIC_MODEL, $settings->model);
        self::assertSame('claude-sonnet-5-5', $settings->model);
        self::assertSame(16000, $settings->maxTokens);
        self::assertSame('low', $settings->anthropicEffort);
        self::assertTrue($settings->anthropicFallback);
        self::assertTrue($settings->analysisEnabled);
        self::assertFalse($settings->isUsable());
    }

    #[Test]
    public function openAiSettingsAreResolved(): void
    {
        $settings = $this->factory([
            'aiProvider' => 'openai',
            'openaiApiKey' => ' sk-test ',
            'openaiModel' => 'gpt-4o',
            'maxTokens' => '2048',
            'enableAiAnalysis' => '0',
        ])->create();

        self::assertSame('openai', $settings->provider);
        self::assertSame('sk-test', $settings->apiKey);
        self::assertSame('gpt-4o', $settings->model);
        self::assertSame(2048, $settings->maxTokens);
        self::assertFalse($settings->analysisEnabled);
        self::assertTrue($settings->isUsable());
    }

    #[Test]
    public function ollamaNeedsNoKeyAndUnknownEffortIsDropped(): void
    {
        $settings = $this->factory(['aiProvider' => 'ollama', 'anthropicEffort' => 'off'])->create();

        self::assertSame('ollama', $settings->provider);
        self::assertSame('llama3.2', $settings->model);
        self::assertSame('', $settings->anthropicEffort);
        self::assertTrue($settings->isUsable());
    }

    #[Test]
    public function unknownProviderIsTreatedAsAnthropic(): void
    {
        self::assertSame('anthropic', $this->factory(['aiProvider' => 'foo'])->create()->provider);
    }

    private function factory(array $config): AiSettingsFactory
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn($config);
        return new AiSettingsFactory($extensionConfiguration);
    }
}
