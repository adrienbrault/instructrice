<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

use GregHunt\PartialJson\JsonParser;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use function Psl\Json\encode;
use function Psl\Regex\matches;
use function Psl\Regex\replace;

class OpenAiLLM implements LLMInterface
{
    private JsonParser $jsonParser;

    /**
     * @param callable(mixed): string $systemPrompt
     * @param null|'auto'|'any'|'function' $toolMode
     * @param null|'json_mode'|'json_mode_with_schema' $jsonMode
     */
    public function __construct(
        private string $baseUri,
        private readonly ClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly string $model,
        private $systemPrompt,
        private ?string $toolMode = null,
        private ?string $jsonMode = 'json_mode'
    ) {
        $this->jsonParser = new JsonParser();
    }

    public function get(
        array $schema,
        string $context,
        array $errors = [],
        mixed $errorsData = null,
        ?callable $onChunk = null,
    ): mixed {
        $messages = [
            [
                'role' => 'system',
                'content' => call_user_func($this->systemPrompt, $schema),
            ],
            [
                'role' => 'user',
                'content' => $context,
            ],
        ];

        if ($errors !== []) {
            if ($this->toolMode !== null) {
                $messages[] = [
                    'tool_call_id' => '123',
                    'role' => 'function',
                    'name' => 'extract',
                    'content' => encode($errorsData),
                ];
            } else {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => encode($errorsData),
                ];
            }
            $messages[] = [
                'role' => 'user',
                'content' => sprintf(
                    'Try again, fixing the following errors: %s',
                    encode($errors)
                ),
            ];
        }

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
                'response' => $e->getResponse()->getBody()->getContents(true),
            ]);

            throw $e;
        }

        $content = '';
        $lastContent = '';
        while (! $response->getBody()->eof()) {
            $line = $this->readLine($response->getBody());

            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $data = trim(substr($line, strlen('data:')));

            if ($data === '[DONE]') {
                break;
            }

            /** @var array{error?: array{message: string|array<int, string>, type: string, code: string}} $response */
            $responseData = json_decode(
                $data,
                true,
                flags: JSON_THROW_ON_ERROR
            );

            if (isset($responseData['error'])) {
                throw new \Exception($response['error']);
            }

            if ($this->toolMode !== null) {
                $content .= $responseData['choices'][0]['delta']['tool_calls'][0]['function']['arguments'] ?? '';
            } else {
                $content .= $responseData['choices'][0]['delta']['content'] ?? '';
            }

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

    /**
     * @return array|mixed|string
     * @throws \Exception
     */
    public function parseData(string $content): mixed
    {
        $data = null;
        if ($content !== null) {
            $content = trim($content);

            if (! str_starts_with($content, '{')
                && ! str_starts_with($content, '[')
                && str_contains($content, '```json')
            ) {
                $content = substr($content, strpos($content, '```json') + strlen('```json'));
                $content = replace($content, '#(.+)```.+$#m', '\1');
                $content = trim($content);
            }

            if (str_starts_with($content, '{') || str_starts_with($content, '[')) {
                $data = $this->jsonParser->parse($content);
            }
        }

        if (! is_array($data) && ! is_string($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Read a line from the stream.
     */
    private function readLine(StreamInterface $stream): string
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
