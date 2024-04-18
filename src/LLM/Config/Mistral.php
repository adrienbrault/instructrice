<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Config;

use AdrienBrault\Instructrice\LLM\OpenAiJsonStrategy;

enum Mistral: string implements ProviderEnumInterface
{
    case MISTRAL_7B = 'open-mistral-7b';
    case MIXTRAL_8x7B = 'open-mixtral-8x7b';
    case MIXTRAL_8x22B = 'open-mixtral-8x22b';
    case MISTRAL_LARGE = 'mistral-large-latest';

    public function createConfig(): ?LLMConfig
    {
        $config = match ($this) {
            self::MISTRAL_7B => [
                'contextWindow' => 32000,
                'label' => 'Mistral 7B',
                'promptPpm' => 0.25,
                'comletionPpm' => 0.25,
            ],
            self::MIXTRAL_8x7B => [
                'contextWindow' => 32000,
                'label' => 'Mixtral 8x7B',
                'promptPpm' => 0.7,
                'comletionPpm' => 0.7,
            ],
            self::MIXTRAL_8x22B => [
                'contextWindow' => 64000,
                'label' => 'Mixtral 8x22B',
                'promptPpm' => 2,
                'comletionPpm' => 6,
            ],
            self::MISTRAL_LARGE => [
                'contextWindow' => 32000,
                'label' => 'Mistral Large',
                'promptPpm' => 8,
                'comletionPpm' => 24,
            ],
        };

        $apiKey = getenv('MISTRAL_API_KEY');

        if (! \is_string($apiKey) || $apiKey === '') {
            return null;
        }

        return new LLMConfig(
            'https://api.mistral.ai/v1/chat/completions',
            $this->value,
            'Mistral - ' . $config['label'],
            null,
            $config['contextWindow'],
            null,
            OpenAiJsonStrategy::JSON,
            null,
            new Cost($config['promptPpm'], $config['comletionPpm']),
            [
                'Authorization' => 'Bearer ' . $apiKey,
            ]
        );
    }
}
