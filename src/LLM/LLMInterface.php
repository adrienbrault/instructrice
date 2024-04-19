<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

interface LLMInterface
{
    /**
     * @param array<string, mixed>                 $schema
     * @param callable(mixed, LLMChunk): void|null $onChunk
     */
    public function get(
        array $schema,
        string $context,
        string $instructions,
        ?callable $onChunk = null,
    ): mixed;
}
