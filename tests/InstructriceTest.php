<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\Tests;

use AdrienBrault\Instructrice\Instructrice;
use AdrienBrault\Instructrice\LLM\LLMInterface;
use AdrienBrault\Instructrice\SchemaFactory;
use AdrienBrault\Instructrice\Tests\Fixtures\Person;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Serializer\Serializer;

#[CoversClass(Instructrice::class)]
class InstructriceTest extends TestCase
{
    public function testDeserializeList(): void
    {
        $schema = [
            'type' => 'some_type',
        ];

        $llm = $this->createMock(LLMInterface::class);
        $llm
            ->method('get')
            ->with(
                [
                    'type' => 'object',
                    'properties' => [
                        'list' => [
                            'type' => 'array',
                            'items' => $schema,
                        ],
                    ],
                    'required' => ['list'],
                ],
                'context',
            )
            ->willReturn([
                'list' => [
                    'name' => 'John',
                ],
            ]);
        $schemaFactory = $this->createMock(SchemaFactory::class);
        $schemaFactory
            ->method('createSchema')
            ->willReturn($schema);
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
            $schemaFactory,
            $serializer,
        );

        $list = $instructrice->getList(
            'context',
            Person::class,
        );

        $this->assertSame($deserializedList, $list);
    }
}
