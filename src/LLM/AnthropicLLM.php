<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

use AdrienBrault\Instructrice\Http\StreamingClientInterface;
use AdrienBrault\Instructrice\LLM\Parser\JsonParser;
use AdrienBrault\Instructrice\LLM\Parser\ParserInterface;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

use function Psl\Json\decode;
use function Psl\Type\int;
use function Psl\Type\literal_scalar;
use function Psl\Type\optional;
use function Psl\Type\shape;
use function Psl\Type\string;

class AnthropicLLM implements LLMInterface
{
    public function __construct(
        private readonly StreamingClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly LLMConfig $config,
        private readonly ParserInterface $parser = new JsonParser(),
    ) {
    }

    public function get(
        array $schema,
        string $context,
        string $instructions,
        bool $truncateAutomatically = false,
        ?callable $onChunk = null
    ): mixed {
        $messages = [
            [
                'role' => 'user',
                'content' => $context,
            ],
        ];

        $request = [
            'model' => $this->config->model,
            'messages' => $messages,
            'max_tokens' => 4000,
            'stream' => true,
            'system' => \call_user_func($this->config->systemPrompt, $schema, $instructions),
        ];

        // Tool and json modes do not support streaming.

        $this->logger->debug('Anthropic Request', $request);

        $requestedAt = new DateTimeImmutable();
        $updatesIterator = $this->client->request(
            'POST',
            $this->config->uri,
            $request,
            [
                ...$this->config->headers,
                'anthropic-version' => '2023-06-01',
                'anthropic-beta' => 'tools-2024-04-04',
            ],
        );

        $messageStartType = shape([
            'type' => literal_scalar('message_start'),
            'message' => shape([
                'usage' => shape([
                    'input_tokens' => int(),
                ], true),
            ], true),
        ], true);
        $messageDeltaType = shape([
            'type' => literal_scalar('message_delta'),
            'usage' => shape([
                'output_tokens' => int(),
            ], true),
        ], true);
        $contentBlockDeltaType = shape([
            'type' => literal_scalar('content_block_delta'),
            'delta' => optional(
                shape([
                    'text' => optional(string()),
                ], true)
            ),
            'usage' => optional(
                shape([
                    'input_tokens' => optional(int()),
                    'output_tokens' => optional(int()),
                ])
            ),
        ], true);

        $content = '';
        $lastContent = '';
        $promptTokens = 0;
        $completionTokens = null;
        $completionTokensEstimate = 0;
        foreach ($updatesIterator as $update) {
            $data = decode($update);

            if ($messageStartType->matches($data)) {
                $promptTokens = $data['message']['usage']['input_tokens'];
            }
            if ($messageDeltaType->matches($data)) {
                $completionTokens = $data['usage']['output_tokens'];
            }

            if (! $contentBlockDeltaType->matches($data)) {
                continue;
            }

            ++$completionTokensEstimate;

            $promptTokens = $data['usage']['input_tokens'] ?? $promptTokens;
            $completionTokens = $data['usage']['output_tokens'] ?? $completionTokens;

            $content .= $data['delta']['text'] ?? '';

            if ($content === $lastContent) {
                // If the content hasn't changed, we stop
                continue;
            }

            $firstTokenReceivedAt ??= new DateTimeImmutable();

            if ($onChunk !== null) {
                $chunk = new LLMChunk(
                    $content,
                    $this->parser->parse($content),
                    $promptTokens,
                    $completionTokens ?? $completionTokensEstimate,
                    $this->config->providerModel->getCost(),
                    $requestedAt,
                    $firstTokenReceivedAt
                );

                $onChunk($chunk->data, $chunk);
            }

            $lastContent = $content;
        }

        return $this->parser->parse($content);
    }
}
