<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

enum OpenAiToolStrategy
{
    case ANY;
    case AUTO;
    case FUNCTION;
}
