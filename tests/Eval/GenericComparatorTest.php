<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\Tests\Eval;

use AdrienBrault\Instructrice\Eval\GenericComparator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(GenericComparator::class)]
class GenericComparatorTest extends TestCase
{
    /**
     * @return iterable<array{mixed, mixed, float}>
     */
    public static function provideTestCases(): iterable
    {
        yield [null, null, 1];
        yield [1, null, 0];
        yield [0, 1, 0];
        yield ['Hello', 'Hello', 1];
        yield ['Hello World', 'Hello', 0.625];
        yield [
            [
                'firstName' => 'John',
                'lastName' => 'Doe',
            ],
            [
                'firstName' => 'Jonathan',
                'lastName' => 'Thiel',
            ],
            0.458,
        ];
        yield [
            [
                'events' => [
                    'wake up',
                    'breakfast',
                    'shower',
                    'work',
                    'lunch',
                    'work',
                    'dinner',
                    'sleep',
                ],
            ],
            [
                'events' => [
                    'sleep',
                    'dinner',
                    'work',
                    'lunch',
                    'work',
                    'breakfast',
                    'shower',
                    'wake up',
                ],
            ],
            0.23,
        ];
    }

    #[DataProvider('provideTestCases')]
    public function test(mixed $expected, mixed $actual, float $expectedScore): void
    {
        $comparator = new GenericComparator();
        $score = $comparator->scoreSimilarity($expected, $actual);

        $this->assertSame($expectedScore, $score);
    }
}
