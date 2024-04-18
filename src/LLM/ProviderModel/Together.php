<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\ProviderModel;

use AdrienBrault\Instructrice\LLM\Cost;
use AdrienBrault\Instructrice\LLM\LLMConfig;
use AdrienBrault\Instructrice\LLM\OpenAiToolStrategy;

enum Together: string implements ProviderModel
{
    case MISTRAL_7B = 'mistralai/Mistral-7B-Instruct-v0.2';
    case MIXTRAL_8x7B = 'mistralai/Mixtral-8x7B-Instruct-v0.1';
    case MIXTRAL_8x22B = 'mistralai/Mixtral-8x22B-Instruct-v0.1';
    case WIZARDLM2_8x22B = 'microsoft/WizardLM-2-8x22B';
    case CODE_LLAMA_34B = 'togethercomputer/CodeLlama-34b-Instruct';
    case DBRX = 'databricks/dbrx-instruct';

    public function getApiKeyEnvVar(): ?string
    {
        return 'TOGETHER_API_KEY';
    }

    public function getContextWindow(): int
    {
        return match ($this) {
            self::MISTRAL_7B => 32000,
            self::MIXTRAL_8x7B => 32000,
            self::MIXTRAL_8x22B => 65000,
            self::WIZARDLM2_8x22B => 65000,
            self::CODE_LLAMA_34B => 16000,
            self::DBRX => 32000,
        };
    }

    public function getMaxTokens(): ?int
    {
        return null;
    }

    public function getCost(): Cost
    {
        return match ($this) {
            self::MISTRAL_7B => Cost::create(0.2),
            self::MIXTRAL_8x7B => Cost::create(1.2),
            self::MIXTRAL_8x22B => Cost::create(1.2),
            self::WIZARDLM2_8x22B => Cost::create(1.2),
            self::CODE_LLAMA_34B => Cost::create(0.8),
            self::DBRX => Cost::create(1.2),
        };
    }

    public function getLabel(bool $prefixed = true): string
    {
        return ($prefixed ? 'Together - ' : '') . match ($this) {
            self::MISTRAL_7B => 'Mistral 7B',
            self::MIXTRAL_8x7B => 'Mixtral 8x7B',
            self::MIXTRAL_8x22B => 'Mixtral 8x22B',
            self::WIZARDLM2_8x22B => 'WizardLM 2 8x22B',
            self::CODE_LLAMA_34B => 'CodeLlama 34B',
            self::DBRX => 'DBRX',
        };
    }

    public function getDocUrl(): string
    {
        return 'https://docs.together.ai/docs/inference-models';
    }

    public function createConfig(string $apiKey): LLMConfig
    {
        $strategy = match ($this) {
            self::MIXTRAL_8x7B, self::CODE_LLAMA_34B => OpenAiToolStrategy::FUNCTION,
            default => null,
        };

        return new LLMConfig(
            $this,
            'https://api.together.xyz/v1/chat/completions',
            $this->value,
            $strategy,
            null,
            [
                'Authorization' => 'Bearer ' . $apiKey,
            ]
        );
    }
}
