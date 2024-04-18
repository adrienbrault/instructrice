<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

enum OpenAiJsonStrategy
{
    case JSON;
    case JSON_WITH_SCHEMA;
}
