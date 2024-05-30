<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Client;

use AdrienBrault\Instructrice\Http\StreamingClientInterface;
use AdrienBrault\Instructrice\LLM\LLMChunk;
use AdrienBrault\Instructrice\LLM\LLMConfig;
use AdrienBrault\Instructrice\LLM\LLMInterface;
use AdrienBrault\Instructrice\LLM\Parser\JsonParser;
use AdrienBrault\Instructrice\LLM\Parser\ParserInterface;
use DateTimeImmutable;
use Exception;
use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;
use Gioni06\Gpt3Tokenizer\Gpt3TokenizerConfig;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

use function Psl\Json\encode;
use function Psl\Vec\map;

class GoogleLLM implements LLMInterface
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
        $schema = $this->fixSchema($schema);

        $systemPrompt = sprintf(
            '%s\n\n<context>%s</context>',
            $prompt,
            $context,
        );

        if ($this->config->model === 'gemini-1.5-flash') {
            // While Gemini 1.5 Flash models only accept a text description of the JSON schema you want returned, the Gemini 1.5 Pro models let you pass a schema object
            // https://ai.google.dev/gemini-api/docs/api-overview#json

            $systemPrompt = sprintf(
                '%s\n\n<schema>%s</schema>\n\n<context>%s</context>',
                $prompt,
                encode($schema),
                $context,
            );
        }

        $request = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $systemPrompt,
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema,
                'temperature' => 0,
            ],
        ];

        $promptTokensEstimate = $this->tokenizer->count(
            implode(
                "\n",
                [
                    $request['contents'][0]['parts'][0]['text'],
                    encode($schema),
                ]
            )
        );

        $this->logger->debug('Gemini Request', $request);

        $headers = $this->config->headers;
        $apiKey = $this->config->headers['x-api-key'] ?? null;

        if (! \is_string($apiKey)) {
            throw new Exception('Google Gemini API key is required');
        }

        unset($headers['x-api-key']);
        $uri = sprintf(
            '%s/%s:streamGenerateContent?key=%s', // todo check api key
            $this->config->uri, // https://generativelanguage.googleapis.com/v1/models
            $this->config->model, // gemini-1.5-flash
            $apiKey
        );

        try {
            $requestedAt = new DateTimeImmutable();

            $updatesIterator = $this->client->request('POST', $uri, $request, $headers, false);

            $response = '';
            $content = '';
            $lastContent = '';
            foreach ($updatesIterator as $contentUpdate) {
                $response .= $contentUpdate;
                $parsedResponse = $this->parser->parse($response);

                if (! \is_array($parsedResponse)) {
                    continue;
                }

                $content = implode(
                    '',
                    map(
                        $parsedResponse,
                        fn ($candidates) => $candidates['candidates'][0]['content']['parts'][0]['text'] ?? ''
                    )
                );

                if ($content === $lastContent) {
                    continue;
                }

                $lastContent = $content;

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
            throw new Exception('Gemini Request failed', 0, $exception);
        }

        $this->logger->debug('Gemini Response message content', [
            'content' => $content,
        ]);

        return $this->parser->parse($content);
    }

    /**
     * @param array<mixed> $schema
     *
     * @return array<mixed>
     */
    private function fixSchema(array $schema): array
    {
        $newSchema = [];
        if (isset($schema['type']) && \is_array($schema['type']) && \count($schema['type']) === 2 && $schema['type'][1] === 'null') {
            $schema['type'] = $schema['type'][0];
            $schema['nullable'] = true;
        }
        foreach ($schema as $key => $value) {
            if (\is_array($value)) {
                $value = $this->fixSchema($value);
            }

            $newSchema[$key] = $value;
        }

        return $newSchema;
    }
}
