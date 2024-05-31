<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice;

use AdrienBrault\Instructrice\LLM\LLMChunk;
use AdrienBrault\Instructrice\LLM\LLMConfig;
use AdrienBrault\Instructrice\LLM\LLMFactory;
use AdrienBrault\Instructrice\LLM\LLMInterface;
use AdrienBrault\Instructrice\LLM\Provider\ProviderModel;
use Psl\Type\TypeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

use function Psl\Type\vec;

/**
 * @phpstan-type InstructriceOptions array{
 *     all_required?: bool,
 *     truncate_automatically?: bool,
 * }
 * @phpstan-type Schema array<string, mixed>
 */
class Instructrice
{
    public function __construct(
        private readonly ProviderModel|LLMConfig|string $defaultLlm,
        private readonly LLMFactory $llmFactory,
        private readonly LoggerInterface $logger,
        private readonly SchemaFactory $schemaFactory,
        private readonly SerializerInterface&DenormalizerInterface $serializer,
    ) {
    }

    /**
     * @template T
     *
     * @param Schema|TypeInterface<T>|class-string<T> $type
     * @param callable(mixed, LLMChunk): void         $onChunk
     * @param InstructriceOptions                     $options
     *
     * @return mixed|T|null
     */
    public function get(
        array|TypeInterface|string $type,
        string $context,
        ?string $prompt = null,
        array $options = [],
        ?callable $onChunk = null,
        LLMInterface|LLMConfig|ProviderModel|string|null $llm = null,
    ) {
        $denormalize = fn (mixed $data) => $data;
        $schema = $type;
        if (! \is_array($schema)) {
            $schema = $this->schemaFactory->createSchema(
                $schema,
                $options['all_required'] ?? false
            );
            $denormalize = fn (mixed $data) => $this->denormalize($data, $type);
        }

        return $this->getAndDenormalize(
            $denormalize,
            $schema,
            $context,
            $type,
            $prompt ?? 'Extract all relevant information',
            $options['truncate_automatically'] ?? false,
            $onChunk,
            $llm
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
    public function list(
        string|TypeInterface $type,
        string $context,
        ?string $prompt = null,
        array $options = [],
        ?callable $onChunk = null,
        LLMInterface|LLMConfig|ProviderModel|string|null $llm = null,
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

        return $this->getAndDenormalize(
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
            $prompt ?? 'Extract all relevant information',
            $options['truncate_automatically'] ?? false,
            $onChunk,
            $llm,
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
    private function getAndDenormalize(
        callable $denormalize,
        array $schema,
        string $context,
        string|TypeInterface|array $type,
        string $prompt,
        bool $truncateAutomatically = false,
        ?callable $onChunk = null,
        LLMInterface|LLMConfig|ProviderModel|string|null $llm = null,
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

        $llm ??= $this->defaultLlm;
        if (! $llm instanceof LLMInterface) {
            $llm = $this->llmFactory->create($llm);
        }

        $data = $llm->get(
            $schema,
            $context,
            $prompt,
            $truncateAutomatically,
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
