<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Provider;

use AdrienBrault\Instructrice\LLM\Cost;
use AdrienBrault\Instructrice\LLM\LLMConfig;
use AdrienBrault\Instructrice\LLM\OpenAiJsonStrategy;

enum DeepInfra: string implements ProviderModel
{
    case MIXTRAL_22 = 'mistralai/Mixtral-8x22B-Instruct-v0.1';
    case WIZARDLM2_22 = 'microsoft/WizardLM-2-8x22B';
    case WIZARDLM2_7 = 'microsoft/WizardLM-2-7B';
    case DBRX = 'databricks/dbrx-instruct';
    case GEMMA_7B = 'google/gemma-1.1-7b-it';
    case LLAMA3_8B = 'meta-llama/Meta-Llama-3-8B-Instruct';
    case LLAMA3_70B = 'meta-llama/Meta-Llama-3-70B-Instruct';
    case LLAMA31_8B = 'meta-llama/Meta-Llama-3.1-8B-Instruct';
    case LLAMA31_70B = 'meta-llama/Meta-Llama-3.1-70B-Instruct';

    public function getApiKeyEnvVar(): ?string
    {
        return 'DEEPINFRA_API_KEY';
    }

    public function createConfig(string $apiKey): LLMConfig
    {
        return new LLMConfig(
            'https://api.deepinfra.com/v1/openai/chat/completions',
            $this->value,
            match ($this) {
                self::MIXTRAL_22 => 64000,
                self::WIZARDLM2_22 => 64000,
                self::WIZARDLM2_7 => 32000,
                self::DBRX => 32000,
                self::LLAMA3_8B, self::LLAMA3_70B, self::GEMMA_7B => 8000,
                self::LLAMA31_8B, self::LLAMA31_70B => 8000,
            },
            match ($this) {
                self::MIXTRAL_22 => 'Mixtral 8x22B',
                self::WIZARDLM2_22 => 'WizardLM 2 8x22B',
                self::WIZARDLM2_7 => 'WizardLM 2 7B',
                self::DBRX => 'DBRX',
                self::GEMMA_7B => 'Gemma 7B',
                self::LLAMA3_8B => 'Llama3 8B',
                self::LLAMA3_70B => 'Llama3 70B',
                self::LLAMA31_8B => 'Llama 3.1 8B',
                self::LLAMA31_70B => 'Llama 3.1 70B',
            },
            'DeepInfra',
            match ($this) {
                self::MIXTRAL_22 => Cost::create(0.65),
                self::WIZARDLM2_22 => Cost::create(0.65),
                self::WIZARDLM2_7 => Cost::create(0.1),
                self::DBRX => Cost::create(0.6),
                self::GEMMA_7B => Cost::create(0.1),
                self::LLAMA3_8B => Cost::create(0.1),
                self::LLAMA3_70B => new Cost(0.59, 0.79),
                self::LLAMA31_8B => Cost::create(0.09),
                self::LLAMA31_70B => new Cost(0.52, 0.75),
            },
            match ($this) {
                default => OpenAiJsonStrategy::JSON_WITH_SCHEMA,
            },
            headers: [
                'Authorization' => 'Bearer ' . $apiKey,
            ],
            docUrl: 'https://deepinfra.com/' . $this->value
        );
    }
}
