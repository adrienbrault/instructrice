<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class TogetherFactory
{
    private ClientInterface $guzzleClient;

    private LoggerInterface $logger;

    /**
     * @var callable(mixed): string
     */
    private $systemPrompt = null;

    /**
     * @param callable(mixed): string $systemPrompt
     */
    public function __construct(
        ?ClientInterface $guzzleClient = null,
        ?LoggerInterface $logger = null,
        ?callable $systemPrompt = null,
    ) {
        $this->guzzleClient = $guzzleClient ?? new Client([
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer ' . getenv('TOGETHER_API_KEY'),
            ],
        ]);

        $this->logger = $logger ?? new NullLogger();

        $this->systemPrompt = $systemPrompt ?? function ($schema): string {
            return 'You are a helpful assistant that can access external functions. The responses from these function calls will be appended to this dialogue. Please provide responses based on the information from these function calls. If the user intent is unclear, consider it a structured information extraction task.';
        };
    }

    public function mixtral(): LLMInterface
    {
        return new OpenAiLLM(
            'https://api.together.xyz/v1',
            $this->guzzleClient,
            $this->logger,
            'mistralai/Mixtral-8x7B-Instruct-v0.1',
            $this->systemPrompt,
            'function'
        );
    }

    public function mistral7B(): LLMInterface
    {
        return new OpenAiLLM(
            'https://api.together.xyz/v1',
            $this->guzzleClient,
            $this->logger,
            'mistralai/Mistral-7B-Instruct-v0.1',
            $this->systemPrompt,
            'function'
        );
    }

    public function codeLLama34b(): LLMInterface
    {
        return new OpenAiLLM(
            'https://api.together.xyz/v1',
            $this->guzzleClient,
            $this->logger,
            'togethercomputer/CodeLlama-34b-Instruct',
            $this->systemPrompt,
            'function'
        );
    }
}
