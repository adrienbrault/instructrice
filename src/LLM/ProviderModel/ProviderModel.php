<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\ProviderModel;

use AdrienBrault\Instructrice\LLM\Cost;
use AdrienBrault\Instructrice\LLM\LLMConfig;

interface ProviderModel
{
    public function getApiKeyEnvVar(): ?string;

    public function getContextWindow(): int;

    public function getMaxTokens(): ?int;

    public function getCost(): Cost;

    public function getLabel(bool $prefixed = true): string;

    public function getDocUrl(): string;

    public function createConfig(string $apiKey): LLMConfig;
}
