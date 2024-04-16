<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

interface LLMInterface
{
    /**
     * @param array<array-key, mixed> $schema
     * @param array<array-key, mixed> $errors
     * @param null|callable(mixed, string): void $onChunk
     */
    public function get(
        array $schema,
        string $context,
        array $errors = [],
        mixed $errorsData = null,
        ?callable $onChunk = null,
    ): mixed;
}
