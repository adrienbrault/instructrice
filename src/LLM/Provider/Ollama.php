<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Provider;

use AdrienBrault\Instructrice\LLM\LLMConfig;
use AdrienBrault\Instructrice\LLM\OpenAiJsonStrategy;

use function Psl\Json\encode;
use function Psl\Regex\first_match;
use function Psl\Type\int;
use function Psl\Type\shape;
use function Psl\Type\string;

enum Ollama: string implements ProviderModel
{
    case HERMES2PRO_MISTRAL_7B = 'adrienbrault/nous-hermes2pro:';
    case HERMES2PRO_LLAMA3_8B = 'adrienbrault/nous-hermes2pro-llama3-8b:';
    case HERMES2THETA_LLAMA3_8B = 'adrienbrault/nous-hermes2theta-llama3-8b:';
    case PHI3_MINI_4K = 'phi3:3.8b-mini-4k-instruct-';
    case PHI3_MINI_128K = 'phi3:3.8b-mini-128k-instruct-';
    case PHI3_MEDIUM_4K = 'phi3:14b-medium-4k-instruct-';
    case PHI3_MEDIUM_128K = 'phi3:14b-medium-128k-instruct-';
    case MISTRAL_7B = 'mistral:7b-instruct-v0.3-';
    case MIXTRAL_8x7B = 'mixtral:8x7b-instruct-v0.1-';
    case DOLPHINCODER7 = 'dolphincoder:7b-starcoder2-';
    case DOLPHINCODER15 = 'dolphincoder:15b-starcoder2-';
    case STABLELM2_16 = 'stablelm2:1.6b-chat-';
    case COMMANDR = 'command-r:35b-v0.1-';
    case COMMANDRPLUS = 'command-r-plus:104b-';
    case LLAMA3_8B = 'llama3:8b-instruct-';
    case LLAMA3_70B = 'llama3:70b-instruct-';
    case LLAMA3_70B_DOLPHIN = 'dolphin-llama3:8b-v2.9-';
    case LLAMA31_8B = 'llama3.1:8b-instruct-';
    case LLAMA31_70B = 'llama3.1:70b-instruct-';
    case LLAMA31_405B = 'llama3.1:405b-instruct-';
    case QWEN2_05B = 'qwen2:0.5b-instruct-';
    case QWEN2_15B = 'qwen2:1.5b-instruct-';
    case QWEN2_7B = 'qwen2:7b-instruct-';
    case QWEN2_72B = 'qwen2:72b-instruct-';

    public function getApiKeyEnvVar(): ?string
    {
        return null; // always enable
    }

    /**
     * If you need to customize something that is not part of the arguments, instantiate the LLMConfig yourself!
     */
    public static function create(
        string $model,
        ?int $contextLength = null,
        ?string $label = null,
        ?OpenAiJsonStrategy $strategy = OpenAiJsonStrategy::JSON,
    ): LLMConfig {
        if ($contextLength === null) {
            $matchType = shape([
                0 => string(),
                'digits' => int(),
            ]);
            $kDigits = first_match($model, '/\D(?P<digits>\d{1,3})k/', $matchType)['digits'] ?? 8;

            $contextLength = $kDigits * 1000;
        }

        return new LLMConfig(
            self::getURI(),
            $model,
            $contextLength,
            $label ?? $model,
            'Ollama',
            strategy: $strategy,
        );
    }

    public function createConfig(string $apiKey): LLMConfig
    {
        $strategy = match ($this) {
            self::COMMANDR, self::COMMANDRPLUS => null,
            self::HERMES2PRO_LLAMA3_8B, self::HERMES2THETA_LLAMA3_8B => null, // does not work with json mode
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
            self::PHI3_MINI_4K, self::PHI3_MINI_128K => 'q5_K_M',
            self::PHI3_MEDIUM_4K, self::PHI3_MEDIUM_128K => 'q5_K_M',
            self::QWEN2_72B => 'q4_K_S',
            default => 'q4_K_M',
        };

        $contextLength = match ($this) {
            self::HERMES2PRO_MISTRAL_7B => 8000,
            self::HERMES2PRO_LLAMA3_8B, self::HERMES2THETA_LLAMA3_8B => 8000,
            self::DOLPHINCODER7 => 4000,
            self::DOLPHINCODER15 => 4000,
            self::STABLELM2_16 => 4000,
            self::COMMANDR => 128000,
            self::COMMANDRPLUS => 128000,
            self::PHI3_MINI_4K, self::PHI3_MEDIUM_4K => 4000,
            self::PHI3_MINI_128K, self::PHI3_MEDIUM_128K => 128000,
            self::MISTRAL_7B => 32000,
            self::MIXTRAL_8x7B => 32000,
            self::LLAMA3_8B, self::LLAMA3_70B_DOLPHIN, self::LLAMA3_70B => 8000,
            self::LLAMA31_8B, self::LLAMA31_70B, self::LLAMA31_405B => 128000,
            self::QWEN2_7B, self::QWEN2_72B => 128000,
            self::QWEN2_05B, self::QWEN2_15B => 32000,
        };
        $label = match ($this) {
            self::HERMES2PRO_MISTRAL_7B => 'Nous Hermes 2 Pro Mistral 7B',
            self::HERMES2PRO_LLAMA3_8B => 'Nous Hermes 2 Pro Llama3 8B',
            self::HERMES2THETA_LLAMA3_8B => 'Nous Hermes 2 Theta Llama3 8B',
            self::PHI3_MINI_4K => 'Phi-3 Mini 4K',
            self::PHI3_MINI_128K => 'Phi-3 Mini 128K',
            self::PHI3_MEDIUM_4K => 'Phi-3 Medium 4K',
            self::PHI3_MEDIUM_128K => 'Phi-3 Medium 128K',
            self::MISTRAL_7B => 'Mistral 7B',
            self::MIXTRAL_8x7B => 'Mixtral 8x7B',
            self::DOLPHINCODER7 => 'DolphinCoder 7B',
            self::DOLPHINCODER15 => 'DolphinCoder 15B',
            self::STABLELM2_16 => 'StableLM2 1.6B',
            self::COMMANDR => 'CommandR 35B',
            self::COMMANDRPLUS => 'CommandR+ 104B',
            self::LLAMA3_8B => 'Llama3 8B',
            self::LLAMA3_70B => 'Llama3 70B',
            self::LLAMA3_70B_DOLPHIN => 'Llama3 8B Dolphin 2.9',
            self::LLAMA31_8B => 'Llama 3.1 8B',
            self::LLAMA31_70B => 'Llama 3.1 70B',
            self::LLAMA31_405B => 'Llama 3.1 405B',
            self::QWEN2_05B => 'Qwen2 0.5B',
            self::QWEN2_15B => 'Qwen2 1.5B',
            self::QWEN2_7B => 'Qwen2 7B',
            self::QWEN2_72B => 'Qwen2 72B',
        };
        $stopTokens = match ($this) {
            self::LLAMA3_8B, self::LLAMA3_70B => ["```\n\n", '<|im_end|>', '<|eot_id|>', "\t\n\t\n"],
            default => null,
        };

        return new LLMConfig(
            self::getURI(),
            $this->value . $defaultVersion,
            $contextLength,
            $label,
            'Ollama',
            strategy: $strategy,
            systemPrompt: $systemPrompt,
            stopTokens: $stopTokens
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

    public static function getURI(?string $host = null): string
    {
        $host ??= getenv('OLLAMA_HOST') ?: 'http://localhost:11434';

        if (! str_starts_with($host, 'http')) {
            $host = 'http://' . $host;
        }

        return $host . '/v1/chat/completions';
    }
}
