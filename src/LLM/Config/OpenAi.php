<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Config;

use AdrienBrault\Instructrice\LLM\OpenAiToolStrategy;
use GuzzleHttp\RequestOptions;

enum OpenAi: string implements ProviderEnumInterface
{
    case GPT_35T = 'gpt-3.5-turbo';
    case GPT_4T = 'gpt-4-turbo';

    public function createConfig(): ?LLMConfig
    {
        $config = match ($this) {
            self::GPT_35T => [
                'contextWindow' => 16385,
                'maxTokens' => 4096,
                'label' => 'GPT-3.5 Turbo',
                'promptPpm' => 0.7,
                'completionPpm' => 0.7,
                'docUrl' => 'https://platform.openai.com/docs/models/gpt-3-5-turbo',
            ],
            self::GPT_4T => [
                'contextWindow' => 128000,
                'maxTokens' => 4096,
                'label' => 'GPT-4 Turbo',
                'promptPpm' => 0.7,
                'completionPpm' => 0.7,
                'docUrl' => 'https://platform.openai.com/docs/models/gpt-4-turbo-and-gpt-4',
            ],
        };

        $apiKey = getenv('OPENAI_API_KEY');

        if (! \is_string($apiKey) || $apiKey === '') {
            return null;
        }

        return new LLMConfig(
            'https://api.openai.com/v1/chat/completions',
            $this->value,
            'OpenAI - ' . $config['label'],
            $config['docUrl'],
            $config['contextWindow'],
            $config['maxTokens'],
            OpenAiToolStrategy::FUNCTION,
            null,
            new Cost($config['completionPpm'], $config['promptPpm']),
            [
                RequestOptions::HEADERS => [
                    'Authorization' => 'Bearer ' . $apiKey,
                ],
            ]
        );
    }
}
