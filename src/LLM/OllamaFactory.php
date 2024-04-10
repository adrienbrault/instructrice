<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

use OpenAI;
use OpenAI\Contracts\ClientContract;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use function Psl\Json\encode;

class OllamaFactory
{
    private ClientContract $openAiClient;

    private LoggerInterface $logger;

    /**
     * @var callable(mixed): string
     */
    private $systemPrompt = null;

    /**
     * @param callable(mixed): string $systemPrompt
     */
    public function __construct(
        ?ClientContract $openAiClient = null,
        ?LoggerInterface $logger = null,
        ?callable $systemPrompt = null,
    ) {
        $this->openAiClient = $openAiClient ?? OpenAI::factory()
            ->withBaseUri((getenv('OLLAMA_HOST') ?: 'http://localhost:11434') . '/v1')
            ->make();

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

    public function hermes2pro(string $quantization = 'q4_K_M'): LLMInterface
    {
        return new OpenAiLLM(
            $this->openAiClient,
            $this->logger,
            'adrienbrault/nous-hermes2pro:' . $quantization,
            $this->systemPrompt,
        );
    }

    public function stablelm2(string $quantization = 'q8_0'): LLMInterface
    {
        return new OpenAiLLM(
            $this->openAiClient,
            $this->logger,
            'stablelm2:1.6b-chat-' . $quantization,
            $this->systemPrompt,
        );
    }
}
