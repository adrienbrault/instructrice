<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\ProviderModel;

use AdrienBrault\Instructrice\LLM\Cost;
use AdrienBrault\Instructrice\LLM\LLMConfig;
use AdrienBrault\Instructrice\LLM\OpenAiJsonStrategy;
use AdrienBrault\Instructrice\LLM\OpenAiToolStrategy;

enum Fireworks: string implements ProviderModel
{
    case FIREFUNCTION_V1 = 'firefunction-v1';
    case MIXTRAL_7 = 'mixtral-8x7b-instruct';
    case MIXTRAL_22 = 'mixtral-8x22b-instruct';
    case DBRX = 'dbrx-instruct';
    case HERMES_2_PRO = 'hermes-2-pro-mistral-7b';
    case CAPYBARA_34 = 'yi-34b-200k-capybara';
    case GEMMA_7B = 'gemma-7b-it';
    case LLAMA3_8B = 'llama-v3-8b-instruct';
    case LLAMA3_70B = 'llama-v3-70b-instruct';

    public function getApiKeyEnvVar(): ?string
    {
        return 'FIREWORKS_API_KEY';
    }

    public function getContextWindow(): int
    {
        return match ($this) {
            self::FIREFUNCTION_V1 => 32768,
            self::MIXTRAL_7 => 32768,
            self::MIXTRAL_22 => 65536,
            self::DBRX => 32768,
            self::HERMES_2_PRO => 4000,
            self::CAPYBARA_34 => 200000,
            self::LLAMA3_8B, self::LLAMA3_70B, self::GEMMA_7B => 8000,
        };
    }

    public function getMaxTokens(): ?int
    {
        return null;
    }

    public function getCost(): Cost
    {
        return Cost::create(match ($this) {
            self::FIREFUNCTION_V1 => 0,
            self::MIXTRAL_7 => 0.2,
            self::MIXTRAL_22 => 0.9,
            self::DBRX => 1.6,
            self::HERMES_2_PRO => 0.2,
            self::CAPYBARA_34 => 0.9,
            self::GEMMA_7B => 0.2,
            self::LLAMA3_8B => 0.2,
            self::LLAMA3_70B => 0.9,
        });
    }

    public function getLabel(bool $prefixed = true): string
    {
        return ($prefixed ? 'Fireworks - ' : '') . match ($this) {
            self::FIREFUNCTION_V1 => 'Firefunction V1',
            self::MIXTRAL_7 => 'Mixtral 8x7B',
            self::MIXTRAL_22 => 'Mixtral 8x22B',
            self::DBRX => 'DBRX',
            self::HERMES_2_PRO => 'Hermes 2 Pro',
            self::CAPYBARA_34 => 'Capybara 34B',
            self::GEMMA_7B => 'Gemma 7B',
            self::LLAMA3_8B => 'Llama3 8B',
            self::LLAMA3_70B => 'Llama3 70B',
        };
    }

    public function getDocUrl(): string
    {
        return 'https://fireworks.ai/models/fireworks/' . $this->value;
    }

    public function createConfig(string $apiKey): LLMConfig
    {
        return new LLMConfig(
            $this,
            'https://api.fireworks.ai/inference/v1/chat/completions',
            'accounts/fireworks/models/' . $this->value,
            match ($this) {
                self::FIREFUNCTION_V1 => OpenAiToolStrategy::FUNCTION,
                default => OpenAiJsonStrategy::JSON_WITH_SCHEMA,
            },
            null,
            [
                'Authorization' => 'Bearer ' . $apiKey,
            ]
        );
    }
}
