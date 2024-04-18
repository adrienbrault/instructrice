<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Config;

use AdrienBrault\Instructrice\LLM\OpenAiToolStrategy;

enum Together: string implements ProviderEnumInterface
{
    case MISTRAL_7B = 'mistralai/Mistral-7B-Instruct-v0.2';
    case MIXTRAL_8x7B = 'mistralai/Mixtral-8x7B-Instruct-v0.1';
    case MIXTRAL_8x22B = 'mistralai/Mixtral-8x22B-Instruct-v0.1';
    case WIZARDLM2_8x22B = 'microsoft/WizardLM-2-8x22B';
    case CODE_LLAMA_34B = 'togethercomputer/CodeLlama-34b-Instruct';
    case GEMMA_7B = 'google/gemma-7b-it';
    case DBRX = 'databricks/dbrx-instruct';

    public function createConfig(): ?LLMConfig
    {
        $config = match ($this) {
            self::MISTRAL_7B => [
                'contextWindow' => 32000,
                'label' => 'Mistral 7B',
                'ppm' => 0.2,
            ],
            self::MIXTRAL_8x7B => [
                'contextWindow' => 32000,
                'label' => 'Mixtral 8x7B',
                'ppm' => 1.2,
                'strategy' => OpenAiToolStrategy::FUNCTION,
            ],
            self::MIXTRAL_8x22B => [
                'contextWindow' => 65000,
                'label' => 'Mixtral 8x22B',
                'ppm' => 1.2,
            ],
            self::WIZARDLM2_8x22B => [
                'contextWindow' => 65000,
                'label' => 'WizardLM 2 8x22B',
                'ppm' => 1.2,
            ],
            self::CODE_LLAMA_34B => [
                'contextWindow' => 16000,
                'label' => 'CodeLlama 34B',
                'ppm' => 0.8,
                'strategy' => OpenAiToolStrategy::FUNCTION,
            ],
            self::GEMMA_7B => [
                'contextWindow' => 8000,
                'label' => 'Gemma 7B',
                'ppm' => 0.2,
            ],
            self::DBRX => [
                'contextWindow' => 32000,
                'label' => 'DBRX',
                'ppm' => 1.2,
            ],
        };

        $apiKey = getenv('TOGETHER_API_KEY');

        if (! \is_string($apiKey) || $apiKey === '') {
            return null;
        }

        return new LLMConfig(
            'https://api.together.xyz/v1/chat/completions',
            $this->value,
            'Together - ' . $config['label'],
            'https://docs.together.ai/docs/inference-models',
            $config['contextWindow'],
            null,
            $config['strategy'] ?? null,
            null,
            Cost::create($config['ppm']),
            [
                'Authorization' => 'Bearer ' . $apiKey,
            ]
        );
    }
}
