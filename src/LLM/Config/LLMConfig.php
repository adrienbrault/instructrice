<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Config;

use AdrienBrault\Instructrice\LLM\OpenAiJsonStrategy;
use AdrienBrault\Instructrice\LLM\OpenAiToolStrategy;

class LLMConfig
{
    /**
     * @param callable(mixed, string): string $systemPrompt
     * @param array<string, mixed>            $headers
     */
    public function __construct(
        public readonly string $uri,
        public readonly string $model,
        public readonly string $label,
        public readonly ?string $docUrl,
        public readonly int $contextWindow,
        public readonly ?int $maxCompletionTokens,
        public readonly OpenAiToolStrategy|OpenAiJsonStrategy|null $strategy = null,
        public $systemPrompt = null,
        public readonly ?Cost $cost = null,
        public readonly array $headers = [],
    ) {
    }
}
