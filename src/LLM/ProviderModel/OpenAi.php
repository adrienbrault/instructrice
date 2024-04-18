<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\ProviderModel;

use AdrienBrault\Instructrice\LLM\Cost;
use AdrienBrault\Instructrice\LLM\LLMConfig;
use AdrienBrault\Instructrice\LLM\OpenAiToolStrategy;

enum OpenAi: string implements ProviderModel
{
    case GPT_35T = 'gpt-3.5-turbo';
    case GPT_4T = 'gpt-4-turbo';

    public function getApiKeyEnvVar(): ?string
    {
        return 'OPENAI_API_KEY';
    }

    public function getContextWindow(): int
    {
        return match ($this) {
            self::GPT_35T => 16385,
            self::GPT_4T => 128000,
        };
    }

    public function getMaxTokens(): int
    {
        return 4096;
    }

    public function getCost(): Cost
    {
        return match ($this) {
            self::GPT_35T => new Cost(0.5, 1.5),
            self::GPT_4T => new Cost(10, 30),
        };
    }

    public function getLabel(bool $prefixed = true): string
    {
        return ($prefixed ? 'OpenAI - ' : '') . match ($this) {
            self::GPT_35T => 'GPT-3.5 Turbo',
            self::GPT_4T => 'GPT-4 Turbo',
        };
    }

    public function getDocUrl(): string
    {
        return match ($this) {
            self::GPT_35T => 'https://platform.openai.com/docs/models/gpt-3-5-turbo',
            self::GPT_4T => 'https://platform.openai.com/docs/models/gpt-4-turbo-and-gpt-4',
        };
    }

    public function createConfig(string $apiKey): LLMConfig
    {
        return new LLMConfig(
            $this,
            'https://api.openai.com/v1/chat/completions',
            $this->value,
            OpenAiToolStrategy::FUNCTION,
            null,
            [
                'Authorization' => 'Bearer ' . $apiKey,
            ]
        );
    }
}
