<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\ProviderModel;

use AdrienBrault\Instructrice\LLM\Cost;
use AdrienBrault\Instructrice\LLM\LLMConfig;
use AdrienBrault\Instructrice\LLM\OpenAiJsonStrategy;

enum Deepinfra: string implements ProviderModel
{
    case MIXTRAL_22 = 'mistralai/Mixtral-8x22B-Instruct-v0.1';
    case WIZARDLM2_22 = 'microsoft/WizardLM-2-8x22B';
    case WIZARDLM2_7 = 'microsoft/WizardLM-2-7B';
    case DBRX = 'databricks/dbrx-instruct';

    public function getApiKeyEnvVar(): ?string
    {
        return 'DEEPINFRA_API_KEY';
    }

    public function getContextWindow(): int
    {
        return match ($this) {
            self::MIXTRAL_22 => 64000,
            self::WIZARDLM2_22 => 64000,
            self::WIZARDLM2_7 => 32000,
            self::DBRX => 32000,
        };
    }

    public function getMaxTokens(): ?int
    {
        return null;
    }

    public function getCost(): Cost
    {
        return Cost::create(match ($this) {
            self::MIXTRAL_22 => 0.65,
            self::WIZARDLM2_22 => 0.65,
            self::WIZARDLM2_7 => 0.1,
            self::DBRX => 0.6,
        });
    }

    public function getLabel(bool $prefixed = true): string
    {
        return ($prefixed ? 'Deepinfra - ' : '') . match ($this) {
            self::MIXTRAL_22 => 'Mixtral 8x22B',
            self::WIZARDLM2_22 => 'WizardLM 2 8x22B',
            self::WIZARDLM2_7 => 'WizardLM 2 7B',
            self::DBRX => 'DBRX',
        };
    }

    public function getDocUrl(): string
    {
        return 'https://deepinfra.com/' . $this->value;
    }

    public function createConfig(string $apiKey): LLMConfig
    {
        return new LLMConfig(
            $this,
            'https://api.deepinfra.com/v1/openai/chat/completions',
            $this->value,
            OpenAiJsonStrategy::JSON,
            null,
            [
                'Authorization' => 'Bearer ' . $apiKey,
            ],
        );
    }
}
