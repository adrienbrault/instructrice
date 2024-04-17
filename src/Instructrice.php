<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice;

use AdrienBrault\Instructrice\LLM\LLMInterface;
use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ArrayObject;
use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use Psl\Type\TypeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

use function Psl\Type\vec;
use function Psl\Vec\filter;

/**
 * @phpstan-type InstructriceOptions array{
 *     all_required?: bool
 * }
 */
class Instructrice
{
    public function __construct(
        private readonly LLMInterface $llm,
        private readonly LoggerInterface $logger,
        private readonly Gpt3Tokenizer $gp3Tokenizer,
        private readonly SchemaFactoryInterface $schemaFactory,
        public readonly SerializerInterface&DenormalizerInterface $serializer,
    ) {
    }

    /**
     * @template T
     *
     * @param class-string<T>|TypeInterface<T> $type
     * @param callable(array<T>, float): void  $onChunk
     * @param InstructriceOptions              $options
     *
     * @return list<T>
     */
    public function deserializeList(
        string $context,
        string|TypeInterface $type,
        array $options = [],
        ?callable $onChunk = null,
    ): array {
        $schema = [
            'type' => 'object',
            'properties' => [
                'list' => [
                    'type' => 'array',
                    'items' => $this->createSchema($type, $options),
                ],
            ],
            'required' => ['list'],
        ];

        $llmOnChunk = null;
        if ($onChunk !== null) {
            $t0 = microtime(true);
            $llmOnChunk = function (mixed $data, string $rawData) use ($type, $onChunk, $t0) {
                try {
                    $denormalized = $this->denormalizeList($data, $type);
                } catch (Throwable $e) {
                    $this->logger->info('Failed to denormalize list', [
                        'rawData' => $rawData,
                        'data' => $data,
                        'type' => $type,
                        'error' => $e,
                    ]);

                    return; // Ignore, final denormalize should fail if so bad
                }

                if ($denormalized === null) {
                    return;
                }

                // For models not using the GPT tokenizer, this won't be accurate
                // However this makes comparing the speed of different models better
                // than using different tokenizers
                $tokensPerSecond = $this->gp3Tokenizer->count($rawData) / (microtime(true) - $t0);

                $onChunk($denormalized, $tokensPerSecond);
            };
        }

        $data = $this->llm->get(
            $schema,
            $context,
            [],
            null,
            $llmOnChunk,
        );

        return $this->denormalizeList($data, $type) ?? [];
    }

    /**
     * @template T
     *
     * @param class-string<T>|TypeInterface<T> $type
     *
     * @return list<T>|null
     */
    private function denormalizeList(mixed $data, string|TypeInterface $type): ?array
    {
        if (! \is_array($data)) {
            return null;
        }

        $list = $data['list'] ?? null;

        if ($list === null) {
            return null;
        }

        if ($type instanceof TypeInterface) {
            return vec($type)->coerce($list);
        }

        return $this->serializer->denormalize(
            $list,
            sprintf('%s[]', $type),
            'json'
        );
    }

    private function mapSchema(mixed $node, Schema $schema, bool $makeAllRequired): mixed
    {
        if ($node instanceof ArrayObject) {
            $node = $node->getArrayCopy();
        }

        if (\is_array($node)) {
            if (\array_key_exists('$ref', $node)) {
                $ref = $node['$ref'];
                if (str_starts_with((string) $ref, '#/definitions/')) {
                    $ref = substr((string) $ref, \strlen('#/definitions/'));
                    $node = $schema->getDefinitions()[$ref];
                    if ($node instanceof ArrayObject) {
                        $node = $node->getArrayCopy();
                    }

                    return $this->mapSchema($node, $schema, $makeAllRequired);
                }
            }

            unset($node['deprecated']);
            if (($node['description'] ?? null) === '') {
                unset($node['description']);
            }

            if ($makeAllRequired
                && \is_array($node['properties'] ?? null)
            ) {
                $properties = $node['properties'];
                $required = [
                    ...($node['required'] ?? []),
                    ...array_keys($properties),
                ];
                $node['required'] = $required;
            }
            if ($makeAllRequired
                && \is_array($node['type'] ?? null)
            ) {
                $node['type'] = array_diff($node['type'], ['null']);

                if (\count($node['type']) === 1) {
                    $node['type'] = $node['type'][0];
                }
            }
            if ($makeAllRequired
                && \is_array($node['anyOf'] ?? null)
            ) {
                $node['anyOf'] = filter(
                    $node['anyOf'],
                    fn ($item) => $item !== [
                        'type' => 'null',
                    ]
                );

                if (\count($node['anyOf']) === 1) {
                    return $this->mapSchema($node['anyOf'][0], $schema, $makeAllRequired);
                }
            }

            return array_map(
                fn ($value) => $this->mapSchema($value, $schema, $makeAllRequired),
                $node,
            );
        }

        return $node;
    }

    /**
     * @template T
     *
     * @param TypeNode|TypeInterface<T>|class-string<T> $type
     * @param InstructriceOptions                       $options
     */
    public function createSchema(TypeNode|TypeInterface|string $type, array $options): mixed
    {
        if (\is_string($type)) {
            $schema = $this->schemaFactory->buildSchema($type);

            return $this->mapSchema(
                $schema->getArrayCopy(),
                $schema,
                $options['all_required'] ?? true
            );
        }

        if ($type instanceof TypeNode) {
            $phpstanType = $type;
        } else {
            $phpstanType = (new TypeParser())->parse(
                new TokenIterator((new Lexer())->tokenize($type->toString()))
            );
        }

        if ($phpstanType instanceof ArrayShapeNode) {
            $schema = [
                'type' => 'object',
                'properties' => [],
            ];

            foreach ($phpstanType->items as $item) {
                if ($item->keyName === null) {
                    continue;
                }

                $schema['properties'][(string) $item->keyName] = $this->createSchema($item->valueType, $options);

                if (! $item->optional) {
                    $schema['required'][] = (string) $item->keyName;
                }
            }

            return $schema;
        }

        \assert($phpstanType instanceof IdentifierTypeNode);

        // todo support more complex types
        return [
            'type' => $phpstanType->name,
        ];
    }
}
