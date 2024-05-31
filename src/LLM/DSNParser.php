<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

use AdrienBrault\Instructrice\LLM\Client\AnthropicLLM;
use AdrienBrault\Instructrice\LLM\Client\GoogleLLM;
use AdrienBrault\Instructrice\LLM\Client\OpenAiLLM;
use InvalidArgumentException;

use function Psl\Type\int;
use function Psl\Type\literal_scalar;
use function Psl\Type\optional;
use function Psl\Type\shape;
use function Psl\Type\string;
use function Psl\Type\union;

class DSNParser
{
    public function parse(string $dsn): LLMConfig
    {
        $parsedUrl = parse_url($dsn);

        if (! \is_array($parsedUrl)) {
            throw new InvalidArgumentException('The DSN could not be parsed');
        }

        $parsedUrl = shape([
            'scheme' => string(),
            'pass' => optional(string()),
            'host' => string(),
            'port' => optional(int()),
            'path' => optional(string()),
            'query' => string(),
        ], true)->coerce($parsedUrl);

        $apiKey = $parsedUrl['pass'] ?? null;
        $host = $parsedUrl['host'];
        $port = $parsedUrl['port'] ?? null;
        $path = $parsedUrl['path'] ?? null;
        $query = $parsedUrl['query'];

        $hostWithPort = $host . ($port === null ? '' : ':' . $port);

        $client = union(
            literal_scalar('openai'),
            literal_scalar('openai-http'),
            literal_scalar('anthropic'),
            literal_scalar('google')
        )->coerce($parsedUrl['scheme']);

        parse_str($query, $parsedQuery);
        $model = $parsedQuery['model'];
        $strategyName = $parsedQuery['strategy'] ?? null;
        $context = (int) ($parsedQuery['context'] ?? null);

        if (! \is_string($model)) {
            throw new InvalidArgumentException('The DSN "model" query string must be a string');
        }

        if ($context <= 0) {
            throw new InvalidArgumentException('The DSN "context" query string must be a positive integer');
        }

        $scheme = 'https';

        $strategy = null;
        if ($strategyName === 'json') {
            $strategy = OpenAiJsonStrategy::JSON;
        } elseif ($strategyName === 'json_with_schema') {
            $strategy = OpenAiJsonStrategy::JSON_WITH_SCHEMA;
        } elseif ($strategyName === 'tool_any') {
            $strategy = OpenAiToolStrategy::ANY;
        } elseif ($strategyName === 'tool_auto') {
            $strategy = OpenAiToolStrategy::AUTO;
        } elseif ($strategyName === 'tool_function') {
            $strategy = OpenAiToolStrategy::FUNCTION;
        }

        if ($client === 'anthropic') {
            $headers = [
                'x-api-key' => $apiKey,
            ];
            $llmClass = AnthropicLLM::class;
            $path ??= '/v1/messages';
        } elseif ($client === 'google') {
            $headers = [
                'x-api-key' => $apiKey,
            ];
            $llmClass = GoogleLLM::class;
            $path ??= '/v1beta/models';
        } elseif ($client === 'openai' || $client === 'openai-http') {
            $path ??= '/v1/chat/completions';
            $headers = $apiKey === null ? [] : [
                'Authorization' => 'Bearer ' . $apiKey,
            ];

            $llmClass = OpenAiLLM::class;

            if ($client === 'openai-http') {
                $scheme = 'http';
            }
        } else {
            throw new InvalidArgumentException(sprintf('Unknown client "%s", use one of %s', $client, implode(', ', ['openai', 'anthropic', 'google'])));
        }

        $uri = $scheme . '://' . $hostWithPort . $path;

        return new LLMConfig(
            $uri,
            $model,
            $context,
            $model,
            $hostWithPort,
            new Cost(),
            $strategy,
            headers: $headers,
            llmClass: $llmClass
        );
    }
}
