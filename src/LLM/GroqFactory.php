<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use function Psl\Json\encode;

class GroqFactory
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
                'Authorization' => 'Bearer ' . getenv('GROQ_API_KEY'),
            ],
        ]);

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

ONLY OUTPUT JSON.
PROMPT;
        };
    }

    public function mixtral(): LLMInterface
    {
        return new OpenAiLLM(
            'https://api.groq.com/openai/v1',
            $this->guzzleClient,
            $this->logger,
            'mixtral-8x7b-32768',
            $this->systemPrompt,
            null,
            null
        );
    }
}
