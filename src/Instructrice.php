<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice;

use AdrienBrault\Instructrice\LLM\LLMInterface;
use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
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
     * @param class-string<T> $type
     * @param callable(array<T>, float): void $onChunk
     * @param InstructriceOptions $options
     * @return list<T>
     */
    public function deserializeList(
        string $context,
        string $type,
        array $options = [],
        ?callable $onChunk = null,
    ): array {
        $schema = $this->schemaFactory->buildSchema($type);
        $schema = $this->mapSchema(
            $schema->getArrayCopy(),
            $schema,
            $options['all_required'] ?? true
        );
        $schema = [
            'type' => 'object',
            'properties' => [
                'list' => [
                    'type' => 'array',
                    'items' => $schema,
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
                } catch (\Throwable $e) {
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
     * @param class-string<T> $type
     * @return null|list<T>
     */
    private function denormalizeList(mixed $data, string $type): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        $list = $data['list'] ?? null;

        if ($list === null) {
            return null;
        }

        return $this->serializer->denormalize(
            $list,
            sprintf('%s[]', $type),
            'json'
        );
    }

    private function mapSchema(mixed $node, Schema $schema, bool $makeAllRequired): mixed
    {
        if ($node instanceof \ArrayObject) {
            $node = $node->getArrayCopy();
        }

        if (is_array($node)) {
            if (array_key_exists('$ref', $node)) {
                $ref = $node['$ref'];
                if (str_starts_with($ref, '#/definitions/')) {
                    $ref = substr($ref, strlen('#/definitions/'));
                    $node = $schema->getDefinitions()[$ref];
                    if ($node instanceof \ArrayObject) {
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
                && is_array($node['properties'] ?? null)
            ) {
                $properties = $node['properties'];
                $required = [
                    ...($node['required'] ?? []),
                    ...array_keys($properties),
                ];
                $node['required'] = $required;
            }
            if ($makeAllRequired
                && is_array($node['type'] ?? null)
            ) {
                $node['type'] = array_diff($node['type'], ['null']);

                if (count($node['type']) === 1) {
                    $node['type'] = $node['type'][0];
                }
            }
            if ($makeAllRequired
                && is_array($node['anyOf'] ?? null)
            ) {
                $node['anyOf'] = filter(
                    $node['anyOf'],
                    fn ($item) => $item !== [
                        'type' => 'null',
                    ]
                );

                if (count($node['anyOf']) === 1) {
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
}
