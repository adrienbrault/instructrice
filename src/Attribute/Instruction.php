<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Instruction
{
    public function __construct(
        public readonly ?string $description = null,
    ) {
    }
}
