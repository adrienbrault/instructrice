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

class Together
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
                'Authorization' => 'Bearer ' . getenv('TOGETHER_API_KEY'),
            ],
        ]);

        $this->systemPrompt = $systemPrompt ?? function ($schema): string {
            return 'You are a helpful assistant that can access external functions. The responses from these function calls will be appended to this dialogue. Please provide responses based on the information from these function calls. If the user intent is unclear, consider it a structured information extraction task.';
        };
    }

    public function mixtral7(): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            'https://api.together.xyz/v1',
            $this->guzzleClient,
            $this->logger,
            'mistralai/Mixtral-8x7B-Instruct-v0.1',
            $this->systemPrompt,
            'function'
        );
    }

    public function mistral7(): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            'https://api.together.xyz/v1',
            $this->guzzleClient,
            $this->logger,
            'mistralai/Mistral-7B-Instruct-v0.1',
            $this->systemPrompt,
            'function'
        );
    }

    public function codeLLama34(): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            'https://api.together.xyz/v1',
            $this->guzzleClient,
            $this->logger,
            'togethercomputer/CodeLlama-34b-Instruct',
            $this->systemPrompt,
            'function'
        );
    }
}
