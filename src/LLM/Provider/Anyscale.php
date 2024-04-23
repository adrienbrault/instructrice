<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Provider;

use AdrienBrault\Instructrice\LLM\Cost;
use AdrienBrault\Instructrice\LLM\LLMConfig;
use AdrienBrault\Instructrice\LLM\OpenAiJsonStrategy;

enum Anyscale: string implements ProviderModel
{
    case MISTRAL_7B = 'mistralai/Mistral-7B-Instruct-v0.1';
    case MIXTRAL_8x7B = 'mistralai/Mixtral-8x7B-Instruct-v0.1';
    case MIXTRAL_8x22B = 'mistralai/Mixtral-8x22B-Instruct-v0.1';
    case GEMMA_7B = 'google/gemma-7b-it';
    case LLAMA3_8B = 'meta-llama/Llama-3-8b-chat-hf';
    case LLAMA3_70B = 'meta-llama/Llama-3-70b-chat-hf';

    public function getApiKeyEnvVar(): ?string
    {
        return 'ANYSCALE_API_KEY';
    }

    public function createConfig(string $apiKey): LLMConfig
    {
        return new LLMConfig(
            'https://api.endpoints.anyscale.com/v1/chat/completions',
            $this->value,
            match ($this) {
                self::MISTRAL_7B => 16000,
                self::MIXTRAL_8x7B => 32000,
                self::MIXTRAL_8x22B => 65000,
                self::GEMMA_7B, self::LLAMA3_8B, self::LLAMA3_70B => 8000,
            },
            match ($this) {
                self::MISTRAL_7B => 'Mistral 7B',
                self::MIXTRAL_8x7B => 'Mixtral 8x7B',
                self::MIXTRAL_8x22B => 'Mixtral 8x22B',
                self::GEMMA_7B => 'Gemma 7B',
                self::LLAMA3_8B => 'Llama 3 8B',
                self::LLAMA3_70B => 'Llama 3 70B',
            },
            'Anyscale',
            Cost::create(match ($this) {
                self::MISTRAL_7B, self::GEMMA_7B, self::LLAMA3_8B => 0.15,
                self::MIXTRAL_8x7B => 0.50,
                self::MIXTRAL_8x22B => 0.90,
                self::LLAMA3_70B => 1.0,
            }),
            match ($this) {
                self::MISTRAL_7B, self::MIXTRAL_8x7B => OpenAiJsonStrategy::JSON,
                default => OpenAiJsonStrategy::JSON_WITH_SCHEMA
            },
            headers: [
                'Authorization' => 'Bearer ' . $apiKey,
            ],
            docUrl: 'https://docs.endpoints.anyscale.com/pricing'
        );
    }
}
