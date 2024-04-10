<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\Tests;

use AdrienBrault\Instructrice\Instructrice;
use AdrienBrault\Instructrice\InstructriceFactory;
use AdrienBrault\Instructrice\LLM\LLMInterface;
use Limenius\Liform\LiformInterface;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Responses\Meta\MetaInformation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

#[CoversClass(Instructrice::class)]
class InstructriceTest extends TestCase
{
    public function testRequiresLiform(): void
    {
        $instructrice = new Instructrice(
            Forms::createFormFactory(),
            $this->createStub(LiformInterface::class),
            $this->createStub(LLMInterface::class),
            new NullLogger()
        );

        $this->expectExceptionMessage('The form must have the Liform extension registered.');

        $form = $instructrice->fillForm(
            context: 'context',
            newForm: fn (FormFactoryInterface $ff) => $ff->createBuilder()->getForm(),
        );
    }

    public function test(): void
    {
        $llm = $this->createMock(LLMInterface::class);
        $llm
            ->method('get')
            ->willReturn([
                'name' => 'John',
            ]);
        $liform = $this->createMock(LiformInterface::class);
        $liform
            ->method('transform')
            ->willReturn($fakeSchema = [
                'type' => 'object',
                'properties' => [
                    'name' => [
                        'type' => 'string',
                    ],
                ],
            ]);

        $instructrice = new Instructrice(
            InstructriceFactory::createFormFactory(),
            $liform,
            $llm,
            new NullLogger()
        );

        $form = $instructrice->fillForm(
            context: 'context',
            newForm: fn (FormFactoryInterface $ff) => $ff->createBuilder()
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
}
