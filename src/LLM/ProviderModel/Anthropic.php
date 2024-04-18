<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\ProviderModel;

use AdrienBrault\Instructrice\LLM\Cost;
use AdrienBrault\Instructrice\LLM\LLMConfig;

use function Psl\Json\encode;

enum Anthropic: string implements ProviderModel
{
    case CLAUDE3_HAIKU = 'claude-3-haiku-20240307';
    case CLAUDE3_SONNET = 'claude-3-sonnet-20240229';
    case CLAUDE3_OPUS = 'claude-3-opus-20240229';

    public function getApiKeyEnvVar(): ?string
    {
        return 'ANTHROPIC_API_KEY';
    }

    public function getContextWindow(): int
    {
        return 200000;
    }

    public function getMaxTokens(): int
    {
        return 4096;
    }

    public function getCost(): Cost
    {
        return match ($this) {
            self::CLAUDE3_HAIKU => new Cost(0.25, 1.25),
            self::CLAUDE3_SONNET => new Cost(3, 15),
            self::CLAUDE3_OPUS => new Cost(15, 75),
        };
    }

    public function getLabel(bool $prefixed = true): string
    {
        $label = match ($this) {
            self::CLAUDE3_HAIKU => 'Claude 3 Haiku',
            self::CLAUDE3_SONNET => 'Claude 3 Sonnet',
            self::CLAUDE3_OPUS => 'Claude 3 Opus',
        };

        return $prefixed ? 'Anthropic - ' . $label : $label;
    }

    public function getDocUrl(): string
    {
        return 'https://docs.anthropic.com/claude/docs/models-overview';
    }

    public function createConfig(string $apiKey): LLMConfig
    {
        $systemPrompt = function ($schema, string $instructions): string {
            $encodedSchema = encode($schema);

            return <<<PROMPT
                You are a helpful assistant that answers ONLY in JSON.
                If the user intent is unclear, consider it a structured information extraction task.

                <schema>
                {$encodedSchema}
                </schema>

                <instructions>
                {$instructions}
                </instructions>
                PROMPT;
        };

        return new LLMConfig(
            $this,
            'https://api.anthropic.com/v1/messages',
            $this->value,
            null,
            $systemPrompt,
            [
                'x-api-key' => $apiKey,
            ],
        );
    }
}
