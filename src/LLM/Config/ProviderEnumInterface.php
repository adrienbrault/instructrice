<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Config;

interface ProviderEnumInterface
{
    public function createConfig(): ?LLMConfig;
}
