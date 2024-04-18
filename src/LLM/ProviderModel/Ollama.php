<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\ProviderModel;

use AdrienBrault\Instructrice\LLM\Cost;
use AdrienBrault\Instructrice\LLM\LLMConfig;
use AdrienBrault\Instructrice\LLM\OpenAiJsonStrategy;

use function Psl\Json\encode;

enum Ollama: string implements ProviderModel
{
    case HERMES2PRO = 'adrienbrault/nous-hermes2pro:';
    case DOLPHINCODER7 = 'dolphincoder:7b-starcoder2-';
    case DOLPHINCODER15 = 'dolphincoder:15b-starcoder2-';
    case STABLELM2_16 = 'stablelm2:1.6b-chat-';
    case COMMANDR = 'command-r:35b-v0.1-';
    case COMMANDRPLUS = 'command-r-plus:104b-';
    case LLAMA3_8B = 'llama3:8b-instruct-';
    case LLAMA3_70B = 'llama3:70b-instruct-';

    public function getApiKeyEnvVar(): ?string
    {
        return null; // always enable
    }

    public function getContextWindow(): int
    {
        return match ($this) {
            self::HERMES2PRO => 8000,
            self::DOLPHINCODER7 => 4000,
            self::DOLPHINCODER15 => 4000,
            self::STABLELM2_16 => 4000,
            self::COMMANDR => 128000,
            self::COMMANDRPLUS => 128000,
            self::LLAMA3_8B, self::LLAMA3_70B => 8000,
        };
    }

    public function getMaxTokens(): ?int
    {
        return null;
    }

    public function getCost(): Cost
    {
        return Cost::create(0);
    }

    public function getLabel(bool $prefixed = true): string
    {
        return ($prefixed ? 'Ollama - ' : '') . match ($this) {
            self::HERMES2PRO => 'Nous Hermes 2 Pro',
            self::DOLPHINCODER7 => 'DolphinCoder 7B',
            self::DOLPHINCODER15 => 'DolphinCoder 15B',
            self::STABLELM2_16 => 'StableLM2 1.6B',
            self::COMMANDR => 'CommandR 35B',
            self::COMMANDRPLUS => 'CommandR+ 104B',
            self::LLAMA3_8B => 'Llama3 8B',
            self::LLAMA3_70B => 'Llama3 70B',
        };
    }

    public function getDocUrl(): string
    {
        $path = $this->value;
        if (str_contains($path, '/')) {
            $path = 'library/' . $path;
        }

        return 'https://ollama.com/' . $path;
    }

    public function createConfig(string $apiKey): LLMConfig
    {
        $strategy = match ($this) {
            self::COMMANDR, self::COMMANDRPLUS => null,
            self::LLAMA3_8B => null, // json mode makes it slower
            default => OpenAiJsonStrategy::JSON,
        };
        $systemPrompt = match ($this) {
            self::COMMANDR, self::COMMANDRPLUS => $this->getCommandRSystem(),
            default => null,
        };
        $defaultVersion = match ($this) {
            self::COMMANDRPLUS => 'q2_K_M',
            self::STABLELM2_16 => 'q8_0',
            self::LLAMA3_8B => 'q4_K_M',
            self::LLAMA3_70B => 'q4_0',
            default => 'q4_K_M',
        };

        $ollamaHost = getenv('OLLAMA_HOST') ?: 'http://localhost:11434';
        if (! str_starts_with($ollamaHost, 'http')) {
            $ollamaHost = 'http://' . $ollamaHost;
        }

        return new LLMConfig(
            $this,
            $ollamaHost . '/v1/chat/completions',
            $this->value . $defaultVersion,
            $strategy,
            $systemPrompt
        );
    }

    /**
     * @return callable(mixed, string): string
     */
    private function getCommandRSystem()
    {
        return function ($schema, string $instructions): string {
            $encodedSchema = encode($schema);

            return <<<PROMPT
                You are a helpful assistant that answers in JSON.
                If the user intent is unclear, consider it a structured information extraction task.

                ## Available Tools

                A single tool is available with the following schema:
                ```json
                {$encodedSchema}
                ```

                Here is an example invocation:
                ```json
                {"firstProperty":...}
                ```

                Strictly follow the schema.

                ## Instructions
                {$instructions}
                PROMPT;
        };
    }
}
