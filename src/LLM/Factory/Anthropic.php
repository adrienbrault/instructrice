<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Factory;

use AdrienBrault\Instructrice\LLM\AnthropicLLM;
use AdrienBrault\Instructrice\LLM\LLMInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function Psl\Json\encode;

class Anthropic
{
    private readonly ClientInterface $guzzleClient;

    /**
     * @var callable(mixed): string
     */
    private $systemPrompt;

    /**
     * @param callable(mixed): string $systemPrompt
     */
    public function __construct(
        ?ClientInterface $guzzleClient = null,
        private readonly LoggerInterface $logger = new NullLogger(),
        ?callable $systemPrompt = null,
    ) {
        $this->guzzleClient = $guzzleClient ?? new Client([
            RequestOptions::HEADERS => [
                'x-api-key' => getenv('ANTHROPIC_API_KEY'),
            ],
        ]);

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
            $this->guzzleClient,
            $this->logger,
            'claude-3-haiku-20240307',
            $this->systemPrompt,
        );
    }

    public function sonnet(): LLMInterface
    {
        return new AnthropicLLM(
            $this->guzzleClient,
            $this->logger,
            'claude-3-sonnet-20240229',
            $this->systemPrompt,
        );
    }

    public function opus(): LLMInterface
    {
        return new AnthropicLLM(
            $this->guzzleClient,
            $this->logger,
            'claude-3-opus-20240229',
            $this->systemPrompt,
        );
    }
}
