<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\Tests\LLM;

use AdrienBrault\Instructrice\LLM\Client\AnthropicLLM;
use AdrienBrault\Instructrice\LLM\Client\OpenAiLLM;
use AdrienBrault\Instructrice\LLM\DSNParser;
use AdrienBrault\Instructrice\LLM\LLMConfig;
use AdrienBrault\Instructrice\LLM\OpenAiJsonStrategy;
use AdrienBrault\Instructrice\LLM\OpenAiToolStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DSNParser::class)]
class DSNParserTest extends TestCase
{
    public function testOpenAi(): void
    {
        $config = (new DSNParser())->parse('openai://:api_key@api.openai.com/v1/chat/completions?model=gpt-3.5-turbo&strategy=tool_auto&context=16000');

        $this->assertInstanceOf(LLMConfig::class, $config);
        $this->assertSame(OpenAiLLM::class, $config->llmClass);
        $this->assertSame('https://api.openai.com/v1/chat/completions', $config->uri);
        $this->assertSame('gpt-3.5-turbo', $config->model);
        $this->assertSame(16000, $config->contextWindow);
        $this->assertSame('gpt-3.5-turbo', $config->label);
        $this->assertSame('api.openai.com', $config->provider);
        $this->assertSame(OpenAiToolStrategy::AUTO, $config->strategy);
        $this->assertSame([
            'Authorization' => 'Bearer api_key',
        ], $config->headers);
        $this->assertNull($config->docUrl);
    }

    public function testOpenAiPathLess(): void
    {
        $config = (new DSNParser())->parse('openai://:api_key@api.openai.com?model=gpt-3.5-turbo&strategy=tool_auto&context=16000');

        $this->assertInstanceOf(LLMConfig::class, $config);
        $this->assertSame(OpenAiLLM::class, $config->llmClass);
        $this->assertSame('https://api.openai.com/v1/chat/completions', $config->uri);
    }

    public function testAnthropic(): void
    {
        $config = (new DSNParser())->parse('anthropic://:api_key@api.anthropic.com/v1/messages?model=claude-3-haiku-20240307&context=200000');

        $this->assertInstanceOf(LLMConfig::class, $config);
        $this->assertSame(AnthropicLLM::class, $config->llmClass);
        $this->assertSame('https://api.anthropic.com/v1/messages', $config->uri);
        $this->assertSame('claude-3-haiku-20240307', $config->model);
        $this->assertSame(200000, $config->contextWindow);
        $this->assertSame('claude-3-haiku-20240307', $config->label);
        $this->assertSame('api.anthropic.com', $config->provider);
        $this->assertNull($config->strategy);
        $this->assertSame([
            'x-api-key' => 'api_key',
        ], $config->headers);
    }

    public function testAnthropicPathLess(): void
    {
        $config = (new DSNParser())->parse('anthropic://:api_key@api.anthropic.com?model=claude-3-haiku-20240307&context=200000');

        $this->assertInstanceOf(LLMConfig::class, $config);
        $this->assertSame(AnthropicLLM::class, $config->llmClass);
        $this->assertSame('https://api.anthropic.com/v1/messages', $config->uri);
    }

    public function testOllama(): void
    {
        $config = (new DSNParser())->parse('openai-http://localhost:11434?model=adrienbrault/nous-hermes2theta-llama3-8b&strategy=json&context=8000');

        $this->assertInstanceOf(LLMConfig::class, $config);
        $this->assertSame(OpenAiLLM::class, $config->llmClass);
        $this->assertSame('http://localhost:11434/v1/chat/completions', $config->uri);
        $this->assertSame('adrienbrault/nous-hermes2theta-llama3-8b', $config->model);
        $this->assertSame(8000, $config->contextWindow);
        $this->assertSame('adrienbrault/nous-hermes2theta-llama3-8b', $config->label);
        $this->assertSame('localhost:11434', $config->provider);
        $this->assertSame(OpenAiJsonStrategy::JSON, $config->strategy);
    }
}
