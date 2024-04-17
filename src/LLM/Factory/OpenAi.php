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

class OpenAi
{
    private ClientInterface $guzzleClient;

    private LoggerInterface $logger;

    /**
     * @var callable(mixed): string
     */
    private $systemPrompt = null;

    private string $baseUri = 'https://api.openai.com/v1';

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
                'Authorization' => 'Bearer ' . getenv('OPENAI_API_KEY'),
            ],
        ]);

        $this->logger = $logger ?? new NullLogger();

        $this->systemPrompt = $systemPrompt ?? function ($schema): string {
            return <<<PROMPT
You are a helpful assistant that answers in JSON.
If the user intent is unclear, consider it a structured information extraction task.
PROMPT;
        };
    }

    public function gpt35(string $version = ''): LLMInterface
    {
        $model = 'gpt-3.5-turbo';
        if ($version !== '') {
            $model .= '-' . $version;
        }

        return new OpenAiCompatibleLLM(
            $this->baseUri,
            $this->guzzleClient,
            $this->logger,
            $model,
            $this->systemPrompt,
            'function'
        );
    }

    public function gpt4(string $version = ''): LLMInterface
    {
        $model = 'gpt-4-turbo';
        if ($version !== '') {
            $model .= '-' . $version;
        }

        return new OpenAiCompatibleLLM(
            $this->baseUri,
            $this->guzzleClient,
            $this->logger,
            $model,
            $this->systemPrompt,
            'function'
        );
    }
}
