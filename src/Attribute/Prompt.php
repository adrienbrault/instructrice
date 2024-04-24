<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Prompt
{
    public function __construct(
        public readonly ?string $description = null,
    ) {
    }
}
