<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

use AdrienBrault\Instructrice\LLM\Config\LLMConfig;
use Exception;
use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;
use Gioni06\Gpt3Tokenizer\Gpt3TokenizerConfig;
use GregHunt\PartialJson\JsonParser;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

use function Psl\Json\encode;
use function Psl\Json\typed;
use function Psl\Regex\matches;
use function Psl\Regex\replace;
use function Psl\Type\nullable;
use function Psl\Type\optional;
use function Psl\Type\shape;
use function Psl\Type\string;
use function Psl\Type\union;
use function Psl\Type\vec;

class OpenAiCompatibleLLM implements LLMInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly LLMConfig $config,
        private readonly JsonParser $jsonParser = new JsonParser(),
        private readonly Gpt3Tokenizer $tokenizer = new Gpt3Tokenizer(new Gpt3TokenizerConfig()),
    ) {
    }

    public function get(
        array $schema,
        string $context,
        string $instructions,
        ?callable $onChunk = null,
    ): mixed {
        $messages = [
            [
                'role' => 'system',
                'content' => $this->getSystemPrompt()($schema, $instructions),
            ],
            [
                'role' => 'user',
                'content' => $context,
            ],
        ];

        $request = [
            'model' => $this->config->model,
            'messages' => $messages,
            'stream' => true,
        ];

        $request = $this->applyOpenAiStrategy($request, $schema);

        $completionEstimatedTokens = $this->tokenizer->count(
            implode(
                "\n",
                [
                    $messages[0]['content'],
                    $messages[1]['content'],
                    encode($request['tools'] ?? []),
                ]
            )
        );

        $request['max_tokens'] = min(
            $this->config->contextWindow - $completionEstimatedTokens,
            $this->config->maxCompletionTokens ?? $this->config->contextWindow
        );

        $this->logger->debug('OpenAI Request', $request);

        try {
            $response = $this->client->request(
                'POST',
                $this->config->uri,
                [
                    RequestOptions::JSON => $request,
                    RequestOptions::STREAM => true,
                    ...$this->config->guzzleOptions,
                ]
            );
        } catch (RequestException $e) {
            $this->logger->error('OpenAI Request error', [
                'error' => $e->getMessage(),
                'response' => $e->getResponse()?->getBody()->getContents(),
            ]);

            throw $e;
        }

        $content = '';
        foreach ($this->getFullContentUpdates($response) as $contentUpdate) {
            $content = $contentUpdate;

            if ($onChunk !== null) {
                $onChunk(
                    $this->parseData($content),
                    $content
                );
            }
        }

        $this->logger->debug('OpenAI response message content', [
            'content' => $content,
        ]);

        return $this->parseData($content);
    }

    /**
     * @return iterable<string>
     */
    private function getFullContentUpdates(ResponseInterface $response): iterable
    {
        $content = '';
        $lastContent = '';
        while (! $response->getBody()->eof()) {
            $line = self::readLine($response->getBody());

            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $data = trim(substr($line, \strlen('data:')));

            if ($data === '[DONE]') {
                break;
            }

            $content .= $this->getChunkContent($data);

            if (matches($content, '#(\n\s*){3,}$#')) {
                // If the content ends with whitespace including at least 3 newlines, we stop

                $response->getBody()->close();
                break;
            }

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

    private function parseData(?string $content): mixed
    {
        $data = null;
        if ($content !== null) {
            $content = trim($content);

            if (! str_starts_with($content, '{')
                && ! str_starts_with($content, '[')
                && str_contains($content, '```json')
            ) {
                $content = substr($content, strpos($content, '```json') + \strlen('```json'));
                $content = replace($content, '#(.+)```.+$#m', '\1');
                $content = trim($content);
            }

            if (str_starts_with($content, '{') || str_starts_with($content, '[')) {
                $data = $this->jsonParser->parse($content);
            }
        }

        if (! \is_array($data) && ! \is_string($data)) {
            return null;
        }

        return $data;
    }

    public static function readLine(StreamInterface $stream): string
    {
        $buffer = '';

        while (! $stream->eof()) {
            if ('' === ($byte = $stream->read(1))) {
                return $buffer;
            }
            $buffer .= $byte;
            if ($byte === "\n") {
                break;
            }
        }

        return $buffer;
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

        if ($this->config->strategy === null) {
            $request['stop'] = ["```\n\n", "\n\n", "\n\n\n", "\t\n\t\n"];
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
                If the user intent is unclear, consider it a structured information extraction task.

                Here's the json schema you must adhere to:
                <schema>
                {$encodedSchema}
                </schema>

                ONLY OUTPUT JSON, eg:
                ```json
                {"firstProperty":...}
                ```
                PROMPT;
        };
        if ($this->config->strategy instanceof OpenAiToolStrategy) {
            $systemPrompt = fn ($schema): string => 'You are a helpful assistant with access to functions.';
        }

        return function (mixed $schema, string $instructions) use ($systemPrompt): string {
            return $systemPrompt($schema) . <<<PROMPT

                # Instructions
                {$instructions}
                PROMPT;
        };
    }
}
