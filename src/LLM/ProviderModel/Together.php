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
    case QWEN_15_4B = 'Qwen/Qwen1.5-4B-Chat';
    case QWEN_15_7B = 'Qwen/Qwen1.5-7B-Chat';
    case QWEN_15_14B = 'Qwen/Qwen1.5-14B-Chat';
    case QWEN_15_32B = 'Qwen/Qwen1.5-32B-Chat';
    case QWEN_15_72B = 'Qwen/Qwen1.5-72B-Chat';
    case GEMMA_7B = 'google/gemma-7b-it';
    case GEMMA_2B = 'google/gemma-2b-it';
    case LLAMA3_8B = 'meta-llama/Meta-Llama-3-8B-Instruct';
    case LLAMA3_70B = 'meta-llama/Meta-Llama-3-70B-Instruct';

    public function getApiKeyEnvVar(): ?string
    {
        return 'TOGETHER_API_KEY';
    }

    public function getContextWindow(): int
    {
        return match ($this) {
            self::MIXTRAL_8x22B => 65000,
            self::MIXTRAL_8x7B => 30000,
            self::WIZARDLM2_8x22B => 65000,
            self::CODE_LLAMA_34B => 16000,
            self::LLAMA3_8B, self::LLAMA3_70B, self::GEMMA_7B, self::GEMMA_2B => 8000,
            default => 32000,
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
            self::QWEN_15_4B => Cost::create(0.1),
            self::QWEN_15_7B => Cost::create(0.2),
            self::QWEN_15_14B => Cost::create(0.3),
            self::QWEN_15_32B => Cost::create(0.8),
            self::QWEN_15_72B => Cost::create(0.9),
            self::GEMMA_7B, self::GEMMA_2B => Cost::create(0.2),
            self::LLAMA3_8B => Cost::create(0.2),
            self::LLAMA3_70B => Cost::create(0.9),
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
            self::QWEN_15_4B => 'Qwen 1.5 4B',
            self::QWEN_15_7B => 'Qwen 1.5 7B',
            self::QWEN_15_14B => 'Qwen 1.5 14B',
            self::QWEN_15_32B => 'Qwen 1.5 32B',
            self::QWEN_15_72B => 'Qwen 1.5 72B',
            self::GEMMA_2B => 'Gemma 2B',
            self::GEMMA_7B => 'Gemma 7B',
            self::LLAMA3_8B => 'Llama 3 8B',
            self::LLAMA3_70B => 'Llama 3 70B',
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
            ],
            match ($this) {
                self::LLAMA3_8B, self::LLAMA3_70B => ["```\n\n", '<|im_end|>', '<|eot_id|>', "\t\n\t\n"],
                default => null,
            }
        );
    }
}
