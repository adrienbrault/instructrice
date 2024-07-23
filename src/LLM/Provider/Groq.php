<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Provider;

use AdrienBrault\Instructrice\LLM\Cost;
use AdrienBrault\Instructrice\LLM\LLMConfig;

enum Groq: string implements ProviderModel
{
    case MIXTRAL_8x7B = 'mixtral-8x7b-32768';
    case GEMMA_7B = 'gemma-7b-it';
    case LLAMA3_8B = 'llama3-8b-8192';
    case LLAMA3_70B = 'llama3-70b-8192';
    case LLAMA31_8B = 'llama-3.1-8b-instant';
    case LLAMA31_70B = 'llama-3.1-70b-versatile';
    case LLAMA31_405B = 'llama-3.1-405b-reasoning';

    public function getApiKeyEnvVar(): ?string
    {
        return 'GROQ_API_KEY';
    }

    public function createConfig(string $apiKey): LLMConfig
    {
        return new LLMConfig(
            'https://api.groq.com/openai/v1/chat/completions',
            $this->value,
            match ($this) {
                self::MIXTRAL_8x7B => 32768,
                self::LLAMA31_8B, self::LLAMA31_70B, self::LLAMA31_405B => 128000,
                default => 8192,
            },
            match ($this) {
                self::MIXTRAL_8x7B => 'Mixtral 8x7B',
                self::GEMMA_7B => 'Gemma 7B',
                self::LLAMA3_8B => 'Llama3 8B',
                self::LLAMA3_70B => 'Llama3 70B',
                self::LLAMA31_8B => 'Llama 3.1 8B',
                self::LLAMA31_70B => 'Llama 3.1 70B',
                self::LLAMA31_405B => 'Llama 3.1 405B',
            },
            'Groq',
            Cost::create(match ($this) {
                self::MIXTRAL_8x7B => 0.27,
                self::GEMMA_7B => 0.1,
                self::LLAMA3_8B => 0.1,
                self::LLAMA3_70B => 0.8,
                self::LLAMA31_8B => 0.1, // guess
                self::LLAMA31_70B => 0.8, // guess
                self::LLAMA31_405B => 0.8, // guess
            }),
            maxTokens: match ($this) {
                self::LLAMA31_8B, self::LLAMA31_70B, self::LLAMA31_405B => 8000,
                default => null,
            },
            strategy: match ($this) {
                default => null,
            },
            headers: [
                'Authorization' => 'Bearer ' . $apiKey,
            ],
            docUrl: 'https://console.groq.com/docs/models',
        );
    }
}
