<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice;

use Limenius\Liform\LiformInterface;
use OpenAI;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;
use function Psl\Json\encode;
use function Psl\Regex\replace;
use function Psl\Vec\filter_nulls;
use function Psl\Vec\map;

class Instructrice
{
    public function __construct(
        private readonly LiformInterface $liform,
        private readonly OpenAI\Client $openAiClient,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param callable(): FormInterface $newForm
     * @param FormInterface|null        $form A previously submitted forms with validation errors
     */
    public function fillForm(
        string $context,
        callable $newForm,
        int $retries = 0,
        ?FormInterface $form = null
    ): FormInterface {
        $form ??= $newForm();

        $messages = [
            [
                'role' => 'system',
                'content' => $this->getHermes2ProJsonPrompt(
                    encode($this->liform->transform($form))
                ),
            ],
            [
                'role' => 'user',
                'content' => $context,
            ],
        ];

        if ($form->isSubmitted()
            && ! $form->isValid()
        ) {
            $messages[] = [
                'role' => 'assistant',
                'content' => encode($form->getViewData(), true), // hopefully this is good enough
            ];
            $messages[] = [
                'role' => 'user',
                'content' => sprintf(
                    'Try again, fixing the following errors: %s',
                    encode(
                        $this->formatErrors($form)
                    )
                ),
            ];
        }
        $request = [
            'model' => 'adrienbrault/nous-hermes2pro:q4_K_M',
            // 'model' => 'command-r:35b-v0.1-q5_K_M',
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

        if (! is_array($data)) {
            throw new \Exception('decoded non string/array, not good');
        }

        $form = $newForm();
        $form->submit($data);

        $this->logger->debug('Form state post submission', [
            'isvalid' => $form->isValid(),
            'errors' => ! $form->isValid() ? $this->formatErrors($form) : [],
        ]);

        if ($retries > 0
            && ! $form->isValid()
        ) {
            return $this->fillForm(
                context: $context,
                newForm: $newForm,
                retries: $retries - 1,
                form: $form,
            );
        }

        return $form;
    }

    /**
     * @return list<array{message: string, path: string}>
     */
    public function formatErrors(FormInterface $form): array
    {
        return filter_nulls(
            map(
                $form->getErrors(true),
                function (FormError|FormErrorIterator $error) {
                    if ($error instanceof FormErrorIterator) {
                        return null; // ignore for now
                    }

                    $cause = $error->getCause();
                    assert($cause instanceof ConstraintViolationInterface);

                    return [
                        'message' => $error->getMessage(),
                        'path' => replace(
                            $cause->getPropertyPath(),
                            '#([.]?children|[.]data$)#',
                            ''
                        ),
                    ];
                }
            )
        );
    }

    private function getHermes2ProJsonPrompt(string $schema): string
    {
        return <<<PROMPT
You are a helpful assistant that answers in JSON.
If the user intent is unclear, consider it a structured information extraction task.

Here's the json schema you must adhere to:
<schema>
{$schema}
</schema>
PROMPT;
    }
}
