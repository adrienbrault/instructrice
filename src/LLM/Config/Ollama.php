<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Config;

use AdrienBrault\Instructrice\LLM\OpenAiJsonStrategy;

use function Psl\Json\encode;

enum Ollama: string implements ProviderEnumInterface
{
    case HERMES2PRO = 'adrienbrault/nous-hermes2pro:';
    case DOLPHINCODER7 = 'dolphincoder:7b-starcoder2-';
    case DOLPHINCODER15 = 'dolphincoder:15b-starcoder2-';
    case STABLELM2_16 = 'stablelm2:1.6b-chat-';
    case COMMANDR = 'command-r:35b-v0.1-';
    case COMMANDRPLUS = 'command-r-plus:104b-';

    public function createConfig(?string $version = null): ?LLMConfig
    {
        $config = match ($this) {
            self::HERMES2PRO => [
                'contextWindow' => 8000,
                'label' => 'Nous Hermes 2 Pro',
                'strategy' => OpenAiJsonStrategy::JSON,
                'defaultVersion' => 'q4_K_M',
            ],
            self::DOLPHINCODER7 => [
                'contextWindow' => 4000,
                'label' => 'DolphinCoder 7B',
                'strategy' => OpenAiJsonStrategy::JSON,
                'defaultVersion' => 'q4_K_M',
            ],
            self::DOLPHINCODER15 => [
                'contextWindow' => 4000,
                'label' => 'DolphinCoder 15B',
                'strategy' => OpenAiJsonStrategy::JSON,
                'defaultVersion' => 'q4_K_M',
            ],
            self::STABLELM2_16 => [
                'contextWindow' => 4000,
                'label' => 'StableLM2 1.6B',
                'strategy' => OpenAiJsonStrategy::JSON,
                'defaultVersion' => 'q8_0',
            ],
            self::COMMANDR => [
                'contextWindow' => 128000,
                'label' => 'CommandR 35B',
                'strategy' => null,
                'systemPrompt' => $this->getCommandRSystem(),
                'defaultVersion' => 'q4_K_M',
            ],
            self::COMMANDRPLUS => [
                'contextWindow' => 128000,
                'label' => 'CommandR+ 104B',
                'strategy' => null,
                'systemPrompt' => $this->getCommandRSystem(),
                'defaultVersion' => 'q2_K_M',
            ],
        };

        $ollamaHost = getenv('OLLAMA_HOST') ?: 'http://localhost:11434';
        if (! str_starts_with($ollamaHost, 'http')) {
            $ollamaHost = 'http://' . $ollamaHost;
        }

        return new LLMConfig(
            $ollamaHost . '/v1/chat/completions',
            $this->value . ($version ?? $config['defaultVersion']),
            'Ollama - ' . $config['label'],
            null,
            $config['contextWindow'],
            null,
            $config['strategy'],
            $config['systemPrompt'] ?? null,
            Cost::create(0),
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
