<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

use AdrienBrault\Instructrice\Exception\LLMException;
use AdrienBrault\Instructrice\Http\StreamingClientInterface;
use AdrienBrault\Instructrice\LLM\Parser\JsonParser;
use AdrienBrault\Instructrice\LLM\Parser\ParserInterface;
use DateTimeImmutable;
use Exception;
use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;
use Gioni06\Gpt3Tokenizer\Gpt3TokenizerConfig;
use GuzzleHttp\Exception\RequestException;
use JsonException;
use Psl\Json\Exception\DecodeException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Throwable;

use function Psl\Json\decode;
use function Psl\Json\encode;
use function Psl\Json\typed;
use function Psl\Regex\matches;
use function Psl\Type\nullable;
use function Psl\Type\optional;
use function Psl\Type\shape;
use function Psl\Type\string;
use function Psl\Type\union;
use function Psl\Type\vec;

class OpenAiLLM implements LLMInterface
{
    public function __construct(
        private readonly LLMConfig $config,
        private readonly StreamingClientInterface $client,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly Gpt3Tokenizer $tokenizer = new Gpt3Tokenizer(new Gpt3TokenizerConfig()),
        private readonly ParserInterface $parser = new JsonParser(),
    ) {
    }

    public function get(
        array $schema,
        string $context,
        string $prompt,
        bool $truncateAutomatically = false,
        ?callable $onChunk = null,
    ): mixed {
        $system = $this->getSystemPrompt()($schema, $prompt);
        $systemTokens = $this->tokenizer->count($system);

        if ($truncateAutomatically) {
            $promptCompletionRatio = 0.7;
            $contextMaxTokens = (int) ceil(
                ($this->config->contextWindow - $systemTokens)
                * (1 - $promptCompletionRatio)
            );
            $context = $this->tokenizer->chunk($context, $contextMaxTokens)[0];
        }

        $messages = [];
        if ($system !== '') {
            $messages[] = [
                'role' => 'system',
                'content' => $system,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $context,
        ];

        $request = [
            'model' => $this->config->model,
            'messages' => $messages,
            'stream' => true,
        ];

        $request = $this->applyOpenAiStrategy($request, $schema);

        $promptTokensEstimate = $this->tokenizer->count(
            implode(
                "\n",
                [
                    $messages[0]['content'],
                    $messages[1]['content'] ?? '',
                    encode($request['tools'] ?? []),
                ]
            )
        );

        $request['max_tokens'] = min(
            $this->config->contextWindow - $promptTokensEstimate,
            $this->config->maxTokens ?? $this->config->contextWindow
        );

        $this->logger->debug('OpenAI Request', $request);

        try {
            $requestedAt = new DateTimeImmutable();
            $updatesIterator = $this->client->request(
                'POST',
                $this->config->uri,
                $request,
                $this->config->headers,
            );

            $content = '';
            foreach ($this->getFullContentUpdates($updatesIterator) as $contentUpdate) {
                $content = $contentUpdate;

                $firstTokenReceivedAt ??= new DateTimeImmutable();

                if ($onChunk !== null) {
                    $completionTokensEstimate = $this->tokenizer->count($content);

                    $chunk = new LLMChunk(
                        $content,
                        $this->parser->parse($content),
                        $promptTokensEstimate,
                        $completionTokensEstimate,
                        $this->config->cost,
                        $requestedAt,
                        $firstTokenReceivedAt
                    );

                    $onChunk($chunk->data, $chunk);
                }
            }
        } catch (RequestException|HttpExceptionInterface $exception) {
            throw $this->getExceptionToThrow($exception);
        }

        $this->logger->debug('OpenAI Response message content', [
            'content' => $content,
        ]);

        return $this->parser->parse($content);
    }

    /**
     * @param iterable<string> $updates
     *
     * @return iterable<string>
     */
    private function getFullContentUpdates(iterable $updates): iterable
    {
        $content = '';
        $lastContent = '';
        foreach ($updates as $update) {
            if ($update === '[DONE]') {
                break;
            }

            $content .= $this->getChunkContent($update);

            if (matches($content, '#(\n\s*|\t){3,}$#')) {
                // If the content ends with whitespace including at least 3 newlines, we stop

                break;
            }

            $this->logger->debug('OpenAI Response chunk', [
                'content' => $content,
            ]);

            if ($content === $lastContent) {
                // If the content hasn't changed, we stop
                continue;
            }

            yield $content;

            $lastContent = $content;
        }
    }

    private function getChunkContent(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $responseData = typed(
            $data,
            shape([
                'error' => optional(
                    shape([
                        'message' => union(string(), vec(string())),
                        'type' => string(),
                        'code' => string(),
                    ], true)
                ),
                'choices' => vec(
                    shape([
                        'delta' => shape([
                            'content' => optional(
                                nullable(string())
                            ),
                            'tool_calls' => optional(
                                nullable(vec(
                                    shape([
                                        'function' => shape([
                                            'arguments' => optional(
                                                nullable(string())
                                            ),
                                        ], true),
                                    ], true)
                                ))
                            ),
                        ], true),
                    ], true)
                ),
            ], true)
        );

        $errorMessage = $responseData['error']['message'] ?? null;
        if ($errorMessage !== null) {
            throw new Exception(\is_array($errorMessage) ? implode(', ', $errorMessage) : $errorMessage);
        }

        if ($this->config->strategy instanceof OpenAiToolStrategy) {
            return $responseData['choices'][0]['delta']['tool_calls'][0]['function']['arguments'] ?? '';
        }

        return $responseData['choices'][0]['delta']['content'] ?? '';
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function applyOpenAiStrategy(array $request, array $schema): array
    {
        if ($this->config->strategy instanceof OpenAiJsonStrategy) {
            $request['response_format'] = [
                'type' => 'json_object',
            ];
            if ($this->config->strategy === OpenAiJsonStrategy::JSON_WITH_SCHEMA) {
                $request['response_format']['schema'] = $schema;
            }
        } elseif ($this->config->strategy instanceof OpenAiToolStrategy) {
            $request['tools'] = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'extract',
                        'description' => 'Extract the relevant information',
                        'parameters' => $schema,
                    ],
                ],
            ];
            if ($this->config->strategy === OpenAiToolStrategy::ANY) {
                $request['tool_choice'] = 'any';
            } elseif ($this->config->strategy === OpenAiToolStrategy::AUTO) {
                $request['tool_choice'] = 'auto';
            } elseif ($this->config->strategy === OpenAiToolStrategy::FUNCTION) {
                $request['tool_choice'] = [
                    'type' => 'function',
                    'function' => [
                        'name' => 'extract',
                    ],
                ];
            }
        }

        if ($this->config->strategy === null && $this->config->stopTokens !== null) {
            $request['stop'] = $this->config->stopTokens;
        }

        return $request;
    }

    /**
     * @return callable(mixed, string): string
     */
    public function getSystemPrompt(): callable
    {
        if ($this->config->systemPrompt !== null) {
            return $this->config->systemPrompt;
        }

        $systemPrompt = function (mixed $schema): string {
            $encodedSchema = encode($schema);

            return <<<PROMPT
                You are a helpful assistant that answers in JSON.

                Here's the json schema you must STRICTLY adhere to:
                <schema>
                {$encodedSchema}
                </schema>

                Only write json, no other text. Do not add properties.
                PROMPT;
        };
        if ($this->config->strategy instanceof OpenAiToolStrategy) {
            $systemPrompt = fn ($schema): string => 'You are a helpful assistant with access to functions.';
        }

        return function (mixed $schema, string $prompt) use ($systemPrompt): string {
            return $systemPrompt($schema) . "\n\n" . <<<PROMPT
                # Instructions
                {$prompt}
                PROMPT;
        };
    }

    private function getExceptionToThrow(HttpExceptionInterface|RequestException|Exception $exception): Throwable
    {
        $responseBody = null;
        if ($exception instanceof RequestException) {
            $responseBody = $exception->getResponse()?->getBody()->getContents();
        } elseif ($exception instanceof ClientException) {
            $responseBody = $exception->getResponse()->getContent(false);
        }

        $response = null;
        if ($responseBody !== null) {
            try {
                $response = decode($responseBody);
            } catch (DecodeException|JsonException) {
                // ignore
            }
        }

        $openAiErrorShape = shape([
            'error' => shape([
                'message' => string(),
            ], true),
        ], true);

        if (! $openAiErrorShape->matches($response)) {
            return $exception;
        }

        return new LLMException($response['error']['message'], 0, $exception);
    }
}
