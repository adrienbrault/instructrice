<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

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
     * @param class-string<LLMInterface>      $llmClass
     */
    public function __construct(
        public readonly string $uri,
        public readonly string $model,
        public readonly int $contextWindow,
        public readonly string $label,
        public readonly ?string $provider = null,
        public readonly Cost $cost = new Cost(0, 0),
        public readonly OpenAiToolStrategy|OpenAiJsonStrategy|null $strategy = null,
        public $systemPrompt = null,
        public readonly array $headers = [],
        public readonly ?int $maxTokens = null,
        public readonly ?string $docUrl = null,
        array|false|null $stopTokens = null,
        public readonly ?string $llmClass = null,
    ) {
        if ($stopTokens !== false) {
            $this->stopTokens = $stopTokens ?? ["```\n\n", '<|im_end|>', "\n\n\n", "\t\n\t\n"];
        } else {
            $this->stopTokens = null;
        }
    }

    public function getLabel(bool $withProvider = true): string
    {
        return $withProvider && $this->provider !== null ? $this->provider . ' - ' . $this->label : $this->label;
    }
}
