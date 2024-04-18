<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Config;

use AdrienBrault\Instructrice\Http\GuzzleStreamingClient;
use AdrienBrault\Instructrice\Http\StreamingClientInterface;
use AdrienBrault\Instructrice\LLM\AnthropicLLM;
use AdrienBrault\Instructrice\LLM\LLMInterface;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function Psl\Json\encode;

class Anthropic
{
    private readonly StreamingClientInterface $httpClient;

    /**
     * @var callable(mixed): string
     */
    private $systemPrompt;

    /**
     * @param callable(mixed): string $systemPrompt
     */
    public function __construct(
        ?StreamingClientInterface $httpClient = null,
        private readonly LoggerInterface $logger = new NullLogger(),
        ?callable $systemPrompt = null,
    ) {
        $this->httpClient = $httpClient ?? new GuzzleStreamingClient(new Client([]), $this->logger);

        $this->systemPrompt = $systemPrompt ?? function ($schema): string {
            $encodedSchema = encode($schema);

            return <<<PROMPT
                You are a helpful assistant that answers ONLY in JSON.
                If the user intent is unclear, consider it a structured information extraction task.

                <schema>
                {$encodedSchema}
                </schema>
                PROMPT;
        };
    }

    public function haiku(): LLMInterface
    {
        return new AnthropicLLM(
            $this->httpClient,
            $this->logger,
            'claude-3-haiku-20240307',
            $this->systemPrompt,
            [
                'x-api-key' => getenv('ANTHROPIC_API_KEY'),
            ]
        );
    }

    public function sonnet(): LLMInterface
    {
        return new AnthropicLLM(
            $this->httpClient,
            $this->logger,
            'claude-3-sonnet-20240229',
            $this->systemPrompt,
            [
                'x-api-key' => getenv('ANTHROPIC_API_KEY'),
            ]
        );
    }

    public function opus(): LLMInterface
    {
        return new AnthropicLLM(
            $this->httpClient,
            $this->logger,
            'claude-3-opus-20240229',
            $this->systemPrompt,
            [
                'x-api-key' => getenv('ANTHROPIC_API_KEY'),
            ]
        );
    }
}
