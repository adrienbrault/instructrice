<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\Http;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Chunk\ServerSentEvent;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SymfonyStreamingClient implements StreamingClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function request(
        string $method,
        string $url,
        mixed $jsonBody,
        array $headers = [],
        bool $serverSentEvents = true
    ): iterable {
        $client = $this->client;
        if ($serverSentEvents) {
            $client = new EventSourceHttpClient($this->client);
        }

        try {
            $response = $client->request(
                $method,
                $url,
                [
                    'json' => $jsonBody,
                    'headers' => $headers,
                ]
            );
        } catch (HttpExceptionInterface $e) {
            $this->logger->error('OpenAI Request error', [
                'error' => $e->getMessage(),
                'response' => $e->getResponse()->getContent(),
            ]);

            throw $e;
        }

        foreach ($client->stream([$response]) as $r => $chunk) {
            if ($chunk->isTimeout()) {
                continue;
            }

            if (! $chunk instanceof ServerSentEvent) {
                if (! $serverSentEvents) {
                    yield $chunk->getContent();
                }

                continue;
            }

            $data = $chunk->getData();

            if ($data === '') {
                continue;
            }

            yield $data;
        }
    }
}
