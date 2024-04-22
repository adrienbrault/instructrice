<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

use AdrienBrault\Instructrice\LLM\ProviderModel\ProviderModel;

class LLMConfig
{
    /**
     * @var list<string>|null
     */
    public readonly ?array $stopTokens;

    /**
     * @param callable(mixed, string): string $systemPrompt
     * @param array<string, mixed>            $headers
     * @param list<string>                    $stopTokens
     */
    public function __construct(
        public readonly ProviderModel $providerModel,
        public readonly string $uri,
        public readonly string $model,
        public readonly OpenAiToolStrategy|OpenAiJsonStrategy|null $strategy = null,
        public $systemPrompt = null,
        public readonly array $headers = [],
        array|false|null $stopTokens = null,
    ) {
        if ($stopTokens !== false) {
            $this->stopTokens = $stopTokens ?? ["```\n\n", '<|im_end|>', "\n\n\n", "\t\n\t\n"];
        } else {
            $this->stopTokens = null;
        }
    }
}
