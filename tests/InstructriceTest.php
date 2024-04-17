<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\Tests;

use AdrienBrault\Instructrice\Instructrice;
use AdrienBrault\Instructrice\LLM\LLMInterface;
use AdrienBrault\Instructrice\Tests\Fixtures\Person;
use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;
use Gioni06\Gpt3Tokenizer\Gpt3TokenizerConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Serializer\Serializer;

#[CoversClass(Instructrice::class)]
class InstructriceTest extends TestCase
{
    public function testDeserializeList(): void
    {
        $llm = $this->createMock(LLMInterface::class);
        $llm
            ->method('get')
            ->willReturn([
                'list' => [
                    'name' => 'John',
                ],
            ]);
        $schemaFactory = $this->createMock(SchemaFactoryInterface::class);
        $schemaFactory
            ->method('buildSchema')
            ->willReturn($schema = new Schema());
        $serializer = $this->createMock(Serializer::class);
        $serializer
            ->method('denormalize')
            ->with(
                [
                    'name' => 'John',
                ],
                Person::class . '[]'
            )
            ->willReturn($deserializedList = [new Person('John')]);

        $instructrice = new Instructrice(
            $llm,
            new NullLogger(),
            new Gpt3Tokenizer(new Gpt3TokenizerConfig()),
            $schemaFactory,
            $serializer,
        );

        $list = $instructrice->deserializeList(
            'context',
            Person::class,
        );

        $this->assertSame($deserializedList, $list);
    }
}
