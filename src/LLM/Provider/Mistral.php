<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Provider;

use AdrienBrault\Instructrice\LLM\Cost;
use AdrienBrault\Instructrice\LLM\LLMConfig;
use AdrienBrault\Instructrice\LLM\OpenAiJsonStrategy;

enum Mistral: string implements ProviderModel
{
    case MISTRAL_7B = 'open-mistral-7b';
    case MIXTRAL_8x7B = 'open-mixtral-8x7b';
    case MIXTRAL_8x22B = 'open-mixtral-8x22b';
    case MISTRAL_LARGE = 'mistral-large-latest';

    public function getApiKeyEnvVar(): ?string
    {
        return 'MISTRAL_API_KEY';
    }

    public function createConfig(string $apiKey): LLMConfig
    {
        return new LLMConfig(
            'https://api.mistral.ai/v1/chat/completions',
            $this->value,
            match ($this) {
                self::MISTRAL_7B => 32000,
                self::MIXTRAL_8x7B => 32000,
                self::MIXTRAL_8x22B => 64000,
                self::MISTRAL_LARGE => 32000,
            },
            match ($this) {
                self::MISTRAL_7B => 'Mistral 7B',
                self::MIXTRAL_8x7B => 'Mixtral 8x7B',
                self::MIXTRAL_8x22B => 'Mixtral 8x22B',
                self::MISTRAL_LARGE => 'Mistral Large',
            },
            'Mistral',
            match ($this) {
                self::MISTRAL_7B => Cost::create(0.25),
                self::MIXTRAL_8x7B => Cost::create(0.7),
                self::MIXTRAL_8x22B => new Cost(2, 6),
                self::MISTRAL_LARGE => new Cost(8, 24),
            },
            OpenAiJsonStrategy::JSON,
            headers: [
                'Authorization' => 'Bearer ' . $apiKey,
            ],
            docUrl: 'https://docs.mistral.ai/getting-started/models/'
        );
    }
}
