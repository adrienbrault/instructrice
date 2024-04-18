<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Parser;

interface ParserInterface
{
    public function parse(?string $content): mixed;
}
