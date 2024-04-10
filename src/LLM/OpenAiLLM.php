<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

use OpenAI\Contracts\ClientContract;
use Psr\Log\LoggerInterface;
use function Psl\Json\encode;

class OpenAiLLM implements LLMInterface
{
    /**
     * @param callable(mixed): string $systemPrompt
     */
    public function __construct(
        private readonly ClientContract $openAiClient,
        private readonly LoggerInterface $logger,
        private readonly string $model,
        private $systemPrompt,
        // 'command-r:35b-v0.1-q5_K_M',
    ) {
    }

    public function get(
        array $schema,
        string $context,
        array $errors = [],
        mixed $errorsData = null
    ): mixed {
        $messages = [
            [
                'role' => 'system',
                'content' => call_user_func($this->systemPrompt, $schema),
            ],
            [
                'role' => 'user',
                'content' => $context,
            ],
        ];

        if ($errors !== []) {
            $messages[] = [
                'role' => 'assistant',
                'content' => encode($errorsData),
            ];
            $messages[] = [
                'role' => 'user',
                'content' => sprintf(
                    'Try again, fixing the following errors: %s',
                    encode($errors)
                ),
            ];
        }

        $request = [
            'model' => $this->model,
            'messages' => $messages,
            'response_format' => [
                'type' => 'json_object',
            ],
            'max_tokens' => 4000,
        ];

        $this->logger->debug('OpenAI Request', $request);

        $result = $this->openAiClient->chat()->create($request);
        $content = $result->choices[0]->message->content;

        $this->logger->debug('OpenAI response message content', [
            'content' => $content,
        ]);

        $data = null;
        if ($content !== null) {
            $data = json_decode(
                $content,
                true,
                flags: JSON_PARTIAL_OUTPUT_ON_ERROR
            );
        }

        if (! is_array($data) && ! is_string($data)) {
            throw new \Exception('decoded non string/array, not good');
        }

        return $data;
    }
}
