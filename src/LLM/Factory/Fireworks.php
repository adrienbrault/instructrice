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

class Fireworks
{
    private ClientInterface $guzzleClient;

    private LoggerInterface $logger;

    /**
     * @var callable(mixed): string
     */
    private $systemPrompt = null;

    /**
     * @var callable(mixed): string
     */
    private $jsonSystemPrompt = null;

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
                'Authorization' => 'Bearer ' . getenv('FIREWORKS_API_KEY'),
            ],
        ]);

        $this->logger = $logger ?? new NullLogger();

        $this->systemPrompt = $systemPrompt ?? function ($schema): string {
            return 'You are a helpful assistant with access to functions. If the user intent is unclear, consider it a structured information extraction task.';
        };
        $this->jsonSystemPrompt = $systemPrompt ?? function ($schema): string {
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

    public function firefunctionV1(): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            'https://api.fireworks.ai/inference/v1',
            $this->guzzleClient,
            $this->logger,
            'accounts/fireworks/models/firefunction-v1',
            $this->systemPrompt,
            'function'
        );
    }

    public function mixtral(): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            'https://api.fireworks.ai/inference/v1',
            $this->guzzleClient,
            $this->logger,
            'accounts/fireworks/models/mixtral-8x7b-instruct',
            $this->jsonSystemPrompt,
            null,
            'json_mode_with_schema'
        );
    }

    public function bigMixtral(): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            'https://api.fireworks.ai/inference/v1',
            $this->guzzleClient,
            $this->logger,
            'fireworks/mixtral-8x22b-instruct-preview',
            $this->jsonSystemPrompt,
            null,
            'json_mode_with_schema'
        );
    }

    public function dbrx(): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            'https://api.fireworks.ai/inference/v1',
            $this->guzzleClient,
            $this->logger,
            'fireworks/dbrx-instruct',
            $this->jsonSystemPrompt,
            null,
            'json_mode_with_schema'
        );
    }

    public function hermes2pro(): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            'https://api.fireworks.ai/inference/v1',
            $this->guzzleClient,
            $this->logger,
            'fireworks/hermes-2-pro-mistral-7b',
            $this->jsonSystemPrompt,
            null,
            'json_mode_with_schema'
        );
    }
}
