<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

use GregHunt\PartialJson\JsonParser;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;

use function Psl\Json\decode;
use function Psl\Regex\replace;

class AnthropicLLM implements LLMInterface
{
    /**
     * @param callable(mixed): string $systemPrompt
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly string $model,
        private $systemPrompt,
        private readonly string $baseUri = 'https://api.anthropic.com',
        private readonly JsonParser $jsonParser = new JsonParser()
    ) {
    }

    public function get(
        array $schema,
        string $context,
        ?callable $onChunk = null
    ): mixed {
        $messages = [
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
            'system' => \call_user_func($this->systemPrompt, $schema),
        ];

        // Tool mode does not support streaming.

        $this->logger->debug('Anthropic Request', $request);

        try {
            $response = $this->client->request(
                'POST',
                $this->baseUri . '/v1/messages',
                [
                    RequestOptions::JSON => $request,
                    RequestOptions::STREAM => true,
                    RequestOptions::HEADERS => [
                        'anthropic-version' => '2023-06-01',
                        'anthropic-beta' => 'tools-2024-04-04',
                    ],
                ]
            );
        } catch (RequestException $e) {
            $this->logger->error('Anthropic Request error', [
                'error' => $e->getMessage(),
                'response' => $e->getResponse()?->getBody()->getContents(),
            ]);

            throw $e;
        }

        $content = '';
        $lastContent = '';
        while (! $response->getBody()->eof()) {
            $line = OpenAiCompatibleLLM::readLine($response->getBody());

            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $data = trim(substr($line, \strlen('data:')));
            $data = decode($data);

            $delta = null;
            if (\is_array($data)) {
                $delta = $data['delta'] ?? null;
            }
            if (! \is_array($delta)) {
                continue;
            }

            $content .= $delta['text'] ?? '';

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

        return $this->parseData($content);
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
}
