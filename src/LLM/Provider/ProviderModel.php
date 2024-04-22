<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Provider;

use AdrienBrault\Instructrice\LLM\LLMConfig;

interface ProviderModel
{
    public function getApiKeyEnvVar(): ?string;

    public function createConfig(string $apiKey): LLMConfig;
}
