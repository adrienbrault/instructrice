<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Provider;

use AdrienBrault\Instructrice\LLM\Cost;
use AdrienBrault\Instructrice\LLM\LLMConfig;

enum Google: string implements ProviderModel
{
    case GEMINI_15_FLASH = 'gemini-1.5-flash';
    case GEMINI_15_PRO = 'gemini-1.5-pro';

    public function getApiKeyEnvVar(): ?string
    {
        return 'GEMINI_API_KEY';
    }

    public function createConfig(string $apiKey): LLMConfig
    {
        return new LLMConfig(
            'https://generativelanguage.googleapis.com/v1beta/models',
            $this->value,
            1_000_000,
            match ($this) {
                self::GEMINI_15_FLASH => 'Gemini 1.5 Flash',
                self::GEMINI_15_PRO => 'Gemini 1.5 Pro',
            },
            'Google',
            match ($this) {
                // TODO the tiered prices aren't yet supported
                self::GEMINI_15_FLASH => new Cost(0.35, 1.05),
                self::GEMINI_15_PRO => new Cost(3.5, 1.75),
            },
            headers: [
                'x-api-key' => $apiKey,
            ],
            docUrl: 'https://ai.google.dev/gemini-api/docs/models/gemini'
        );
    }
}
