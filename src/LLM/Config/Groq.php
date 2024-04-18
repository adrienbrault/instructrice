<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Config;

use AdrienBrault\Instructrice\LLM\OpenAiJsonStrategy;
use GuzzleHttp\RequestOptions;

enum Groq: string implements ProviderEnumInterface
{
    case MIXTRAL_8x7B = 'mixtral-8x7b-32768';
    case GEMMA_7B = 'gemma-7b-it';

    public function createConfig(): ?LLMConfig
    {
        $config = match ($this) {
            self::MIXTRAL_8x7B => [
                'contextWindow' => 32768,
                'strategy' => OpenAiJsonStrategy::JSON,
                'label' => 'Mixtral 8x7B',
                'ppm' => 0.27,
            ],
            self::GEMMA_7B => [
                'contextWindow' => 8192,
                'strategy' => null,
                'label' => 'Gemma 7B',
                'ppm' => 0.1,
            ],
        };

        $apiKey = getenv('GROQ_API_KEY');

        if (! \is_string($apiKey) || $apiKey === '') {
            return null;
        }

        return new LLMConfig(
            'https://api.groq.com/openai/v1/chat/completions',
            $this->value,
            'Groq - ' . $config['label'],
            'https://console.groq.com/docs/models',
            $config['contextWindow'],
            null,
            null,
            null,
            Cost::create($config['ppm']),
            [
                RequestOptions::HEADERS => [
                    'Authorization' => 'Bearer ' . $apiKey,
                ],
            ]
        );
    }
}
