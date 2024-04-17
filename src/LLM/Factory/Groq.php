<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Factory;

use AdrienBrault\Instructrice\LLM\LLMInterface;
use AdrienBrault\Instructrice\LLM\OpenAiCompatibleLLM;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function Psl\Json\encode;

class Groq
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
                'Authorization' => 'Bearer ' . getenv('GROQ_API_KEY'),
            ],
        ]);

        $this->systemPrompt = $systemPrompt ?? function ($schema): string {
            $encodedSchema = encode($schema);

            return <<<PROMPT
                You are a helpful assistant that answers in JSON.
                If the user intent is unclear, consider it a structured information extraction task.

                Here's the json schema you must adhere to:
                <schema>
                {$encodedSchema}
                </schema>

                ONLY OUTPUT JSON.
                PROMPT;
        };
    }

    public function mixtral(): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            'https://api.groq.com/openai/v1',
            $this->guzzleClient,
            $this->logger,
            'mixtral-8x7b-32768',
            $this->systemPrompt,
            null,
            null
        );
    }

    public function gemma7(): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            'https://api.groq.com/openai/v1',
            $this->guzzleClient,
            $this->logger,
            'gemma-7b-it',
            $this->systemPrompt,
            null,
            null
        );
    }
}
