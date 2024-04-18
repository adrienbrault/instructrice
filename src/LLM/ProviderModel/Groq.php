<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\ProviderModel;

use AdrienBrault\Instructrice\LLM\Cost;
use AdrienBrault\Instructrice\LLM\LLMConfig;

enum Groq: string implements ProviderModel
{
    case MIXTRAL_8x7B = 'mixtral-8x7b-32768';
    case GEMMA_7B = 'gemma-7b-it';

    public function getApiKeyEnvVar(): ?string
    {
        return 'GROQ_API_KEY';
    }

    public function getContextWindow(): int
    {
        return match ($this) {
            self::MIXTRAL_8x7B => 32768,
            self::GEMMA_7B => 8192,
        };
    }

    public function getMaxTokens(): ?int
    {
        return null;
    }

    public function getCost(): Cost
    {
        return Cost::create(match ($this) {
            self::MIXTRAL_8x7B => 0.27,
            self::GEMMA_7B => 0.1,
        });
    }

    public function getLabel(bool $prefixed = true): string
    {
        return ($prefixed ? 'Groq - ' : '') . match ($this) {
            self::MIXTRAL_8x7B => 'Mixtral 8x7B',
            self::GEMMA_7B => 'Gemma 7B',
        };
    }

    public function getDocUrl(): string
    {
        return 'https://console.groq.com/docs/models';
    }

    public function createConfig(string $apiKey): LLMConfig
    {
        return new LLMConfig(
            $this,
            'https://api.groq.com/openai/v1/chat/completions',
            $this->value,
            null,
            null,
            [
                'Authorization' => 'Bearer ' . $apiKey,
            ]
        );
    }
}
