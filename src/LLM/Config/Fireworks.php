<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Config;

use AdrienBrault\Instructrice\LLM\OpenAiJsonStrategy;
use AdrienBrault\Instructrice\LLM\OpenAiToolStrategy;

enum Fireworks: string implements ProviderEnumInterface
{
    case FIREFUNCTION_V1 = 'firefunction-v1';
    case MIXTRAL_7 = 'mixtral-8x7b-instruct';
    case MIXTRAL_22 = 'mixtral-8x22b-instruct';
    case DBRX = 'dbrx-instruct';
    case HERMES_2_PRO = 'hermes-2-pro-mistral-7b';
    case CAPYBARA_34 = 'yi-34b-200k-capybara';

    public function createConfig(): ?LLMConfig
    {
        $config = match ($this) {
            self::FIREFUNCTION_V1 => [
                'contextWindow' => 32768,
                'strategy' => OpenAiToolStrategy::FUNCTION,
                'label' => 'Firefunction V1',
                'ppm' => 0,
            ],
            self::MIXTRAL_7 => [
                'contextWindow' => 32768,
                'strategy' => OpenAiJsonStrategy::JSON_WITH_SCHEMA,
                'label' => 'Mixtral 8x7B',
                'ppm' => 0.2,
            ],
            self::MIXTRAL_22 => [
                'contextWindow' => 65536,
                'strategy' => OpenAiJsonStrategy::JSON_WITH_SCHEMA,
                'label' => 'Mixtral 8x22B',
                'ppm' => 0.9,
            ],
            self::DBRX => [
                'contextWindow' => 32768,
                'strategy' => OpenAiJsonStrategy::JSON_WITH_SCHEMA,
                'label' => 'DBRX',
                'ppm' => 1.6,
            ],
            self::HERMES_2_PRO => [
                'contextWindow' => 4000,
                'strategy' => OpenAiJsonStrategy::JSON_WITH_SCHEMA,
                'label' => 'Hermes 2 Pro',
                'ppm' => 0.2,
            ],
            self::CAPYBARA_34 => [
                'contextWindow' => 200000,
                'strategy' => OpenAiJsonStrategy::JSON_WITH_SCHEMA,
                'label' => 'Capybara 34',
                'ppm' => 0.9,
            ],
        };

        $apiKey = getenv('FIREWORKS_API_KEY');

        if (! \is_string($apiKey) || $apiKey === '') {
            return null;
        }

        return new LLMConfig(
            'https://api.fireworks.ai/inference/v1/chat/completions',
            'accounts/fireworks/models/' . $this->value,
            'Fireworks - ' . $config['label'],
            'https://fireworks.ai/models/fireworks/' . $this->value,
            $config['contextWindow'],
            null,
            $config['strategy'],
            null,
            Cost::create($config['ppm']),
            [
                'Authorization' => 'Bearer ' . $apiKey,
            ]
        );
    }
}
