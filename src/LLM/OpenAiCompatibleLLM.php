<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

use Exception;
use GregHunt\PartialJson\JsonParser;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

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
    private readonly JsonParser $jsonParser;

    /**
     * @param callable(mixed): string                  $systemPrompt
     * @param 'auto'|'any'|'function'|null             $toolMode
     * @param 'json_mode'|'json_mode_with_schema'|null $jsonMode
     */
    public function __construct(
        private readonly string $baseUri,
        private readonly ClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly string $model,
        private $systemPrompt,
        private readonly ?string $toolMode = null,
        private readonly ?string $jsonMode = 'json_mode'
    ) {
        $this->jsonParser = new JsonParser();
    }

    public function get(
        array $schema,
        string $context,
        ?callable $onChunk = null,
    ): mixed {
        $messages = [
            [
                'role' => 'system',
                'content' => \call_user_func($this->systemPrompt, $schema),
            ],
            [
                'role' => 'user',
                'content' => $context,
            ],
        ];

        $request = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => 4000,
            'stream' => true,
        ];
        if ($this->jsonMode !== null && $this->toolMode === null) {
            $request['response_format'] = [
                'type' => 'json_object',
            ];
            if ($this->jsonMode === 'json_mode_with_schema') {
                $request['response_format']['schema'] = $schema;
            }
        }
        if ($this->toolMode === null && $this->jsonMode === null) {
            $request['stop'] = ["```\n", "\n\n", "\n\n\n", "\t\n\t\n"];
        }

        if ($this->toolMode !== null) {
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
            if ($this->toolMode === 'any') {
                $request['tool_choice'] = 'any';
            } elseif ($this->toolMode === 'auto') {
                $request['tool_choice'] = 'auto';
            } elseif ($this->toolMode === 'function') {
                $request['tool_choice'] = [
                    'type' => 'function',
                    'function' => [
                        'name' => 'extract',
                    ],
                ];
            }
        }

        $this->logger->debug('OpenAI Request', $request);

        try {
            $response = $this->client->request(
                'POST',
                $this->baseUri . '/chat/completions',
                [
                    RequestOptions::JSON => $request,
                    RequestOptions::STREAM => true,
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

            if ($onChunk !== null) {
                $onChunk(
                    $this->parseData($content),
                    $content
                );
            }

            $lastContent = $content;
        }

        $this->logger->debug('OpenAI response message content', [
            'content' => $content,
        ]);

        return $this->parseData($content);
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
                                vec(
                                    shape([
                                        'function' => shape([
                                            'arguments' => optional(
                                                nullable(string())
                                            ),
                                        ], true),
                                    ], true)
                                )
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

        if ($this->toolMode !== null) {
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
}
