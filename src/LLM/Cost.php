<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

class Cost
{
    public function __construct(
        public readonly float $millionPromptTokensPrice,
        public readonly float $millionCompletionTokensPrice,
    ) {
    }

    public static function create(float $uniquePrice): self
    {
        return new self($uniquePrice, $uniquePrice);
    }
}
