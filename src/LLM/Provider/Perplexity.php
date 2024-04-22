<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Provider;

use AdrienBrault\Instructrice\LLM\Cost;
use AdrienBrault\Instructrice\LLM\LLMConfig;

enum Perplexity: string implements ProviderModel
{
    case SONAR_SMALL = 'sonar-small-chat';
    case SONAR_SMALL_ONLINE = 'sonar-small-online';
    case SONAR_MEDIUM = 'sonar-medium-chat';
    case SONAR_MEDIUM_ONLINE = 'sonar-medium-online';
    case LLAMA_3_8B = 'llama-3-8b-instruct';
    case LLAMA_3_70B = 'llama-3-70b-instruct';
    case CODELLAMA_70B = 'codellama-70b-instruct';
    case MISTRAL_7B = 'mistral-7b-instruct';
    case MIXTRAL_8x7B = 'mixtral-8x7b-instruct';
    case MIXTRAL_8x22B = 'mixtral-8x22b-instruct';

    public function getApiKeyEnvVar(): ?string
    {
        return 'PERPLEXITY_API_KEY';
    }

    public function createConfig(string $apiKey): LLMConfig
    {
        return new LLMConfig(
            'https://api.perplexity.ai/chat/completions',
            $this->value,
            match ($this) {
                self::SONAR_SMALL, self::SONAR_SMALL_ONLINE, self::MISTRAL_7B => 12000,
                self::SONAR_MEDIUM, self::SONAR_MEDIUM_ONLINE, self::LLAMA_3_8B => 8192,
                self::LLAMA_3_70B, self::CODELLAMA_70B => 8192,
                self::MIXTRAL_8x7B, self::MIXTRAL_8x22B => 8192,
            },
            match ($this) {
                self::SONAR_SMALL => 'Sonar Small',
                self::SONAR_SMALL_ONLINE => 'Sonar Small Online',
                self::SONAR_MEDIUM => 'Sonar Medium',
                self::SONAR_MEDIUM_ONLINE => 'Sonar Medium Online',
                self::LLAMA_3_8B => 'Llama 3 8B',
                self::LLAMA_3_70B => 'Llama 3 70B',
                self::CODELLAMA_70B => 'Code Llama 70B',
                self::MISTRAL_7B => 'Mistral 7B',
                self::MIXTRAL_8x7B => 'Mixtral 8x7B',
                self::MIXTRAL_8x22B => 'Mixtral 8x22B',
            },
            'Perplexity',
            match ($this) {
                self::SONAR_SMALL, self::SONAR_SMALL_ONLINE, self::MISTRAL_7B => Cost::create(0.20),
                self::SONAR_MEDIUM, self::SONAR_MEDIUM_ONLINE, self::LLAMA_3_8B, self::LLAMA_3_70B, self::CODELLAMA_70B => Cost::create(0.20),
                self::MIXTRAL_8x7B => Cost::create(0.60),
                self::MIXTRAL_8x22B => Cost::create(1.00),
            },
            headers: [
                'Authorization' => 'Bearer ' . $apiKey,
            ],
            docUrl: 'https://docs.perplexity.ai/docs/pricing',
            stopTokens: false,
        );
    }
}
