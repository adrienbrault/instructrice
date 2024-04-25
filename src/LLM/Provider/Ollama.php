<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Provider;

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
    case LLAMA3_70B_DOLPHIN = 'dolphin-llama3:8b-v2.9-';
    case PHI3_38_128K = 'herald/phi3-128k';

    public function getApiKeyEnvVar(): ?string
    {
        return null; // always enable
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
            self::LLAMA3_70B => 'q4_0',
            self::PHI3_38_128K => '',
            default => 'q4_K_M',
        };

        $ollamaHost = getenv('OLLAMA_HOST') ?: 'http://localhost:11434';
        if (! str_starts_with($ollamaHost, 'http')) {
            $ollamaHost = 'http://' . $ollamaHost;
        }

        return new LLMConfig(
            $ollamaHost . '/v1/chat/completions',
            $this->value . $defaultVersion,
            match ($this) {
                self::HERMES2PRO => 8000,
                self::DOLPHINCODER7 => 4000,
                self::DOLPHINCODER15 => 4000,
                self::STABLELM2_16 => 4000,
                self::COMMANDR => 128000,
                self::COMMANDRPLUS => 128000,
                self::PHI3_38_128K => 128000,
                self::LLAMA3_8B, self::LLAMA3_70B_DOLPHIN, self::LLAMA3_70B => 8000,
            },
            match ($this) {
                self::HERMES2PRO => 'Nous Hermes 2 Pro',
                self::DOLPHINCODER7 => 'DolphinCoder 7B',
                self::DOLPHINCODER15 => 'DolphinCoder 15B',
                self::STABLELM2_16 => 'StableLM2 1.6B',
                self::COMMANDR => 'CommandR 35B',
                self::COMMANDRPLUS => 'CommandR+ 104B',
                self::LLAMA3_8B => 'Llama3 8B',
                self::LLAMA3_70B => 'Llama3 70B',
                self::LLAMA3_70B_DOLPHIN => 'Llama3 8B Dolphin 2.9',
                self::PHI3_38_128K => 'Phi-3-Mini-128K',
            },
            'Ollama',
            Cost::create(0),
            $strategy,
            $systemPrompt,
            stopTokens: match ($this) {
                self::LLAMA3_8B, self::LLAMA3_70B => ["```\n\n", '<|im_end|>', '<|eot_id|>', "\t\n\t\n"],
                default => null,
            }
        );
    }

    /**
     * @return callable(mixed, string): string
     */
    private function getCommandRSystem()
    {
        return function ($schema, string $prompt): string {
            $encodedSchema = encode($schema);

            return <<<PROMPT
                You are a helpful assistant that answers in JSON.

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
                {$prompt}
                PROMPT;
        };
    }
}
