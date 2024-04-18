<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

use AdrienBrault\Instructrice\Http\StreamingClientInterface;
use AdrienBrault\Instructrice\LLM\Parser\JsonParser;
use AdrienBrault\Instructrice\LLM\Parser\ParserInterface;
use Psr\Log\LoggerInterface;

use function Psl\Json\decode;

class AnthropicLLM implements LLMInterface
{
    /**
     * @param callable(mixed): string $systemPrompt
     * @param array<string, mixed>    $headers
     */
    public function __construct(
        private readonly StreamingClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly string $model,
        private $systemPrompt,
        private readonly array $headers,
        private readonly string $baseUri = 'https://api.anthropic.com',
        private readonly ParserInterface $parser = new JsonParser(),
    ) {
    }

    public function get(
        array $schema,
        string $context,
        string $instructions,
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
            'system' => \call_user_func($this->systemPrompt, $schema, $instructions),
        ];

        // Tool mode does not support streaming.

        $this->logger->debug('Anthropic Request', $request);

        $updatesIterator = $this->client->request(
            'POST',
            $this->baseUri . '/v1/messages',
            $request,
            [
                ...$this->headers,
                'anthropic-version' => '2023-06-01',
                'anthropic-beta' => 'tools-2024-04-04',
            ],
        );

        $content = '';
        $lastContent = '';
        foreach ($updatesIterator as $update) {
            $data = decode($update);

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
                    $this->parser->parse($content),
                    $content
                );
            }

            $lastContent = $content;
        }

        return $this->parser->parse($content);
    }
}
