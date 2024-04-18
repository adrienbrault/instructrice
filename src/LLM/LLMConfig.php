<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

use AdrienBrault\Instructrice\LLM\ProviderModel\ProviderModel;

class LLMConfig
{
    /**
     * @param callable(mixed, string): string $systemPrompt
     * @param array<string, mixed>            $headers
     */
    public function __construct(
        public readonly ProviderModel $providerModel,
        public readonly string $uri,
        public readonly string $model,
        public readonly OpenAiToolStrategy|OpenAiJsonStrategy|null $strategy = null,
        public $systemPrompt = null,
        public readonly array $headers = [],
    ) {
    }
}
