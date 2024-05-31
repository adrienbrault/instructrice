<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Provider;

use AdrienBrault\Instructrice\LLM\Client\AnthropicLLM;
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

    public function createConfig(string $apiKey): LLMConfig
    {
        $systemPrompt = function ($schema, string $prompt): string {
            $encodedSchema = encode($schema);

            return <<<PROMPT
                You are a helpful assistant that answers ONLY in JSON.

                <schema>
                {$encodedSchema}
                </schema>

                <instructions>
                {$prompt}
                </instructions>

                Reply with:
                ```json
                {"...
                ```
                PROMPT;
        };

        return new LLMConfig(
            'https://api.anthropic.com/v1/messages',
            $this->value,
            200000,
            match ($this) {
                self::CLAUDE3_HAIKU => 'Claude 3 Haiku',
                self::CLAUDE3_SONNET => 'Claude 3 Sonnet',
                self::CLAUDE3_OPUS => 'Claude 3 Opus',
            },
            'Anthropic',
            match ($this) {
                self::CLAUDE3_HAIKU => new Cost(0.25, 1.25),
                self::CLAUDE3_SONNET => new Cost(3, 15),
                self::CLAUDE3_OPUS => new Cost(15, 75),
            },
            maxTokens: 4096,
            systemPrompt: $systemPrompt,
            headers: [
                'x-api-key' => $apiKey,
            ],
            docUrl: 'https://docs.anthropic.com/claude/docs/models-overview',
            llmClass: AnthropicLLM::class,
        );
    }
}
