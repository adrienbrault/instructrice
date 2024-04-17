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

class Ollama
{
    private ClientInterface $guzzleClient;

    private LoggerInterface $logger;

    /**
     * @var callable(mixed): string
     */
    private $systemPrompt;

    private string $baseUri;

    /**
     * @param callable(mixed): string $systemPrompt
     */
    public function __construct(
        ?ClientInterface $guzzleClient = null,
        ?LoggerInterface $logger = null,
        ?callable $systemPrompt = null,
        ?string $baseUri = null,
    ) {
        $this->guzzleClient = $guzzleClient ?? new Client([
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer ' . getenv('TOGETHER_API_KEY'),
            ],
        ]);
        $this->baseUri = $baseUri ?? (getenv('OLLAMA_HOST') ?: 'http://localhost:11434') . '/v1';

        $this->logger = $logger ?? new NullLogger();

        $this->systemPrompt = $systemPrompt ?? function ($schema): string {
            $encodedSchema = encode($schema);

            return <<<PROMPT
                You are a helpful assistant that answers in JSON.
                If the user intent is unclear, consider it a structured information extraction task.

                Here's the json schema you must adhere to:
                <schema>
                {$encodedSchema}
                </schema>
                PROMPT;
        };
    }

    public function hermes2pro(string $quantization = 'Q4_K_M'): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            $this->baseUri,
            $this->guzzleClient,
            $this->logger,
            'adrienbrault/nous-hermes2pro:' . $quantization,
            $this->systemPrompt,
        );
    }

    public function dolphincoder7B(string $quantization = 'q4_K_M'): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            $this->baseUri,
            $this->guzzleClient,
            $this->logger,
            'dolphincoder:7b-starcoder2-' . $quantization,
            $this->systemPrompt,
        );
    }

    public function dolphincoder15B(string $quantization = 'q4_K_M'): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            $this->baseUri,
            $this->guzzleClient,
            $this->logger,
            'dolphincoder:15b-starcoder2-' . $quantization,
            $this->systemPrompt,
        );
    }

    public function stablelm2(string $quantization = 'q8_0'): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            $this->baseUri,
            $this->guzzleClient,
            $this->logger,
            'stablelm2:1.6b-chat-' . $quantization,
            $this->systemPrompt,
        );
    }

    public function commandR(string $quantization = 'q4_K_M'): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            $this->baseUri,
            $this->guzzleClient,
            $this->logger,
            'command-r:35b-v0.1-' . $quantization,
            $this->getCommandRSystem(),
            null,
            null
        );
    }

    public function commandRPlus(string $quantization = 'q2_K'): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            $this->baseUri,
            $this->guzzleClient,
            $this->logger,
            'command-r-plus:104b-' . $quantization,
            $this->getCommandRSystem(),
            null,
            null
        );
    }

    /**
     * @return callable(mixed): string
     */
    private function getCommandRSystem()
    {
        return function ($schema): string {
            $encodedSchema = encode($schema);

            return <<<PROMPT
                You are a helpful assistant that answers in JSON.
                If the user intent is unclear, consider it a structured information extraction task.

                ## Available Tools

                A single tool is available with the following schema:
                ```json
                {$encodedSchema}
                ```

                Here is an example invocation:
                ```json
                {"firstProperty":...}
                ```

                Strictly follow the schema.
                PROMPT;
        };
    }
}
