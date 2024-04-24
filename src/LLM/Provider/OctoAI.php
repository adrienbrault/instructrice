<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Provider;

use AdrienBrault\Instructrice\LLM\Cost;
use AdrienBrault\Instructrice\LLM\LLMConfig;
use AdrienBrault\Instructrice\LLM\OpenAiJsonStrategy;

enum OctoAI: string implements ProviderModel
{
    case MISTRAL_7B = 'mistral-7b-instruct';
    case MIXTRAL_8x7B = 'mixtral-8x7b-instruct';
    case MIXTRAL_8x22B = 'mixtral-8x22b-instruct';
    case WIZARDLM2_8x22B = 'mixtral-8x22b-finetuned';
    case LLAMA3_8B = 'meta-llama-3-8b-instruct';
    case LLAMA3_70B = 'meta-llama-3-70b-instruct';
    case QWEN15_32B = 'qwen1.5-32b-chat';
    case HERMES2PRO = 'hermes-2-pro-mistral-7b';

    public function getApiKeyEnvVar(): ?string
    {
        return 'OCTOAI_API_KEY';
    }

    public function createConfig(string $apiKey): LLMConfig
    {
        $price7B = new Cost(0.1, 0.25);
        $price70B = new Cost(0.6, 1.9);

        return new LLMConfig(
            'https://text.octoai.run/v1/chat/completions',
            $this->value,
            match ($this) {
                self::LLAMA3_8B, self::LLAMA3_70B => 8000,
                self::MIXTRAL_8x22B, self::WIZARDLM2_8x22B => 65000,
                default => 32000,
            },
            match ($this) {
                self::MISTRAL_7B => 'Mistral 7B',
                self::MIXTRAL_8x7B => 'Mixtral 8x7B',
                self::MIXTRAL_8x22B => 'Mixtral 8x22B',
                self::WIZARDLM2_8x22B => 'WizardLM 2 8x22B',
                self::LLAMA3_8B => 'Llama3 8B',
                self::LLAMA3_70B => 'Llama3 70B',
                self::HERMES2PRO => 'Nous Hermes 2 Pro',
                self::QWEN15_32B => 'Qwen 1.5 32B',
            },
            'OctoAI',
            match ($this) {
                self::MIXTRAL_8x7B => new Cost(0.3, 0.5),
                self::MIXTRAL_8x22B, self::WIZARDLM2_8x22B => Cost::create(1.2),
                self::LLAMA3_8B, self::MISTRAL_7B, self::HERMES2PRO => $price7B,
                self::LLAMA3_70B => $price70B,
                self::QWEN15_32B => new Cost(0.5, 1)
            },
            OpenAiJsonStrategy::JSON_WITH_SCHEMA,
            headers: [
                'Authorization' => 'Bearer ' . $apiKey,
            ],
            maxTokens: 4000
        );
    }
}
