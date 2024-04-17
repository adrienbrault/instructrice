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

class Mistral
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
                'Authorization' => 'Bearer ' . getenv('MISTRAL_API_KEY'),
            ],
        ]);

        $this->systemPrompt = $systemPrompt ?? function ($schema): string {
            $encodedSchema = encode($schema);

            return <<<PROMPT
                You are a helpful assistant that answers in JSON.
                If the user intent is unclear, consider it a structured information extraction task.

                <schema>
                {$encodedSchema}
                </schema>
                PROMPT;
        };
    }

    public function mixtral7(?string $version = null): LLMInterface
    {
        $model = 'open-mixtral-8x7b';
        if ($version !== null) {
            $model .= '-' . $version;
        }

        return new OpenAiCompatibleLLM(
            'https://api.mistral.ai/v1',
            $this->guzzleClient,
            $this->logger,
            $model,
            $this->systemPrompt,
        );
    }

    public function mixtral22(?string $version = null): LLMInterface
    {
        $model = 'open-mixtral-8x22b';
        if ($version !== null) {
            $model .= '-' . $version;
        }

        return new OpenAiCompatibleLLM(
            'https://api.mistral.ai/v1',
            $this->guzzleClient,
            $this->logger,
            $model,
            $this->systemPrompt,
        );
    }

    public function mistralLarge(string $version = 'latest'): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            'https://api.mistral.ai/v1',
            $this->guzzleClient,
            $this->logger,
            'mistral-large-' . $version,
            $this->systemPrompt,
        );
    }
}