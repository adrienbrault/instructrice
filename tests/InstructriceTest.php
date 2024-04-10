<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\Tests;

use AdrienBrault\Instructrice\Instructrice;
use Limenius\Liform\Form\Extension\AddLiformExtension;
use Limenius\Liform\LiformInterface;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Responses\Meta\MetaInformation;
use OpenAI\Testing\ClientFake;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Validator\Validation;
use function Psl\Json\encode;

#[CoversClass(Instructrice::class)]
class InstructriceTest extends TestCase
{
    public function testRequiresLiform(): void
    {
        $openAiClient = new ClientFake();
        $liform = $this->createStub(LiformInterface::class);

        $instructrice = new Instructrice(
            $liform,
            $openAiClient,
            new NullLogger()
        );

        $formFactory = Forms::createFormFactory();

        $this->expectExceptionMessage('The form must have the Liform extension registered.');

        $form = $instructrice->fillForm(
            context: 'context',
            newForm: fn () => $formFactory->createBuilder()->getForm(),
        );
    }

    public function test(): void
    {
        $openAiClient = new ClientFake([
            $this->mockOpenAiResponse(
                encode([
                    'name' => 'John',
                ])
            ),
        ]);
        $liform = $this->createStub(LiformInterface::class);

        $instructrice = new Instructrice(
            $liform,
            $openAiClient,
            new NullLogger()
        );

        $formFactory = $this->createFormFactory([]);

        $form = $instructrice->fillForm(
            context: 'context',
            newForm: fn () => $formFactory->createBuilder()
                ->add('name', TextType::class)
                ->getForm(),
        );

        $this->assertSame('John', $form->get('name')->getData());
    }

    public function mockOpenAiResponse(
        string $content
    ): CreateResponse {
        return CreateResponse::from(
            [
                'id' => 'id',
                'object' => 'object',
                'created' => 0,
                'model' => 'model',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => $content,
                            'function_call' => null,
                            'tool_calls' => null,
                        ],
                        'finish_reason' => null,
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 4,
                    'completion_tokens' => 4,
                    'total_tokens' => 8,
                ],
            ],
            MetaInformation::from([
                'x-request-id' => ['x-request-id'],
                'openai-model' => ['openai-model'],
                'openai-organization' => ['openai-organization'],
                'openai-version' => ['openai-version'],
                'openai-processing-ms' => ['openai-processing-ms'],
                'x-ratelimit-limit-requests' => ['x-ratelimit-limit-requests'],
                'x-ratelimit-limit-tokens' => ['x-ratelimit-limit-tokens'],
                'x-ratelimit-remaining-requests' => ['x-ratelimit-remaining-requests'],
                'x-ratelimit-remaining-tokens' => ['x-ratelimit-remaining-tokens'],
                'x-ratelimit-reset' => ['x-ratelimit-reset'],
                'x-ratelimit-reset-requests' => ['x-ratelimit-reset-requests'],
                'x-ratelimit-reset-tokens' => ['x-ratelimit-reset-tokens'],
            ])
        );
    }

    /**
     * @param list<FormTypeInterface> $types
     */
    private function createFormFactory(array $types): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addTypeExtension(new AddLiformExtension())
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addTypes($types)
            ->getFormFactory();
    }
}
