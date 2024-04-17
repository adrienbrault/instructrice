<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Factory;

use AdrienBrault\Instructrice\LLM\LLMInterface;
use AdrienBrault\Instructrice\LLM\OpenAiCompatibleLLM;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Deepinfra
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
        $apiKey = getenv('DEEPINFRA_API_KEY') ?: '';
        if ($apiKey === '') {
            throw new InvalidArgumentException('Missing DEEPINFRA_API_KEY');
        }

        $this->guzzleClient = $guzzleClient ?? new Client([
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer ' . $apiKey,
            ],
        ]);

        $this->systemPrompt = $systemPrompt ?? Mistral::getMixtralJsonSystem();
    }

    public function mixtral22(): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            'https://api.deepinfra.com/v1/openai',
            $this->guzzleClient,
            $this->logger,
            'mistralai/Mixtral-8x22B-Instruct-v0.1',
            $this->systemPrompt,
        );
    }

    public function wizardlm2_22(): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            'https://api.deepinfra.com/v1/openai',
            $this->guzzleClient,
            $this->logger,
            'microsoft/WizardLM-2-8x22B',
            $this->systemPrompt,
        );
    }

    public function wizardlm2_7(): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            'https://api.deepinfra.com/v1/openai',
            $this->guzzleClient,
            $this->logger,
            'microsoft/WizardLM-2-7B',
            $this->systemPrompt,
        );
    }

    public function dbrx(): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            'https://api.deepinfra.com/v1/openai',
            $this->guzzleClient,
            $this->logger,
            'databricks/dbrx-instruct',
            $this->systemPrompt,
        );
    }
}
