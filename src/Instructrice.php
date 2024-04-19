<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice;

use AdrienBrault\Instructrice\LLM\LLMChunk;
use AdrienBrault\Instructrice\LLM\LLMInterface;
use Psl\Type\TypeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

use function Psl\Type\vec;

/**
 * @phpstan-type InstructriceOptions array{
 *     all_required?: bool
 * }
 * @phpstan-type Schema array<string, mixed>
 */
class Instructrice
{
    public function __construct(
        private readonly LLMInterface $llm,
        private readonly LoggerInterface $logger,
        private readonly SchemaFactory $schemaFactory,
        private readonly SerializerInterface&DenormalizerInterface $serializer,
    ) {
    }

    /**
     * @param Schema|TypeInterface<mixed>     $type
     * @param callable(mixed, LLMChunk): void $onChunk
     * @param InstructriceOptions             $options
     */
    public function get(
        array|TypeInterface $type,
        string $context,
        ?string $instructions = null,
        array $options = [],
        ?callable $onChunk = null,
    ): mixed {
        $denormalize = fn (mixed $data) => $data;
        $schema = $type;
        if ($schema instanceof TypeInterface) {
            $schema = $this->schemaFactory->createSchema(
                $schema,
                $options['all_required'] ?? false
            );
            $denormalize = fn (mixed $data) => $this->denormalize($data, $type);
        }

        return $this->something(
            $denormalize,
            $schema,
            $context,
            $type,
            $instructions ?? 'Extract all relevant information',
            $onChunk,
        );
    }

    /**
     * @template T
     *
     * @param class-string<T>|TypeInterface<T>        $type
     * @param callable(array<T>|null, LLMChunk): void $onChunk
     * @param InstructriceOptions                     $options
     *
     * @return list<T>
     */
    public function getList(
        string $context,
        string|TypeInterface $type,
        ?string $instructions = null,
        array $options = [],
        ?callable $onChunk = null,
    ): array {
        $wrappedWithProperty = 'list';
        $schema = [
            'type' => 'object',
            'properties' => [
                $wrappedWithProperty => [
                    'type' => 'array',
                    'items' => $this->schemaFactory->createSchema(
                        $type,
                        $options['all_required'] ?? false
                    ),
                ],
            ],
            'required' => [$wrappedWithProperty],
        ];

        return $this->something(
            function (mixed $data) use ($wrappedWithProperty, $type) {
                if (\is_array($data)) {
                    $data = $data[$wrappedWithProperty] ?? $data;
                } else {
                    $data = [];
                }

                return $this->denormalizeList($data, $type);
            },
            $schema,
            $context,
            $type,
            $instructions ?? 'Extract all relevant information',
            $onChunk,
        ) ?? [];
    }

    /**
     * @template T
     * @template TType of string|TypeInterface|Schema
     *
     * @param callable(mixed): T               $denormalize
     * @param Schema                           $schema
     * @param TType                            $type
     * @param callable(T|null, LLMChunk): void $onChunk
     *
     * @return ?T
     */
    private function something(
        callable $denormalize,
        array $schema,
        string $context,
        string|TypeInterface|array $type,
        string $instructions,
        ?callable $onChunk = null,
    ): mixed {
        if (($schema['type'] ?? null) !== 'object') {
            $wrappedWithProperty = 'inner';
            $schema = [
                'type' => 'object',
                'properties' => [
                    $wrappedWithProperty => $schema,
                ],
                'required' => [$wrappedWithProperty],
            ];

            $originalDenormalize = $denormalize;
            $denormalize = function (mixed $data) use ($originalDenormalize, $wrappedWithProperty) {
                return $originalDenormalize($data)[$wrappedWithProperty] ?? null;
            };
        }

        $llmOnChunk = null;
        if ($onChunk !== null) {
            $llmOnChunk = function (mixed $data, LLMChunk $chunk) use ($type, $onChunk, $denormalize) {
                try {
                    $denormalized = $denormalize($chunk->data);
                } catch (Throwable $e) {
                    $this->logger->info('Failed to denormalize', [
                        'data' => $chunk->data,
                        'type' => $type,
                        'error' => $e,
                    ]);

                    return; // Ignore, final denormalize should fail if so bad
                }

                if ($denormalized === null) {
                    return;
                }

                $onChunk($denormalized, $chunk);
            };
        }

        $data = $this->llm->get(
            $schema,
            $context,
            $instructions,
            $llmOnChunk,
        );

        return $denormalize($data);
    }

    /**
     * @template T
     *
     * @param class-string<T>|TypeInterface<T>|Schema $type
     *
     * @return list<T>|null
     */
    private function denormalizeList(mixed $data, string|TypeInterface|array $type): ?array
    {
        if (! \is_array($data)) {
            return null;
        }

        if ($type instanceof TypeInterface) {
            return vec($type)->coerce($data);
        }

        if (\is_array($type)) {
            return $data;
        }

        return $this->serializer->denormalize(
            $data,
            sprintf('%s[]', $type),
            'json'
        );
    }

    /**
     * @template T
     *
     * @param class-string<T>|TypeInterface<T>|Schema $type
     *
     * @return T|null
     */
    private function denormalize(mixed $data, string|TypeInterface|array $type): mixed
    {
        if (\is_array($type)) {
            // json schema, do nothing here
            return $data;
        }

        if ($type instanceof TypeInterface) {
            return $type->coerce($data);
        }

        return $this->serializer->denormalize(
            $data,
            $type,
            'json'
        );
    }
}
