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

class Instructrice
{
    public const OPT_ALL_REQUIRED = 'all_required';

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
     * @param array<self::OPT_*, mixed> $options
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
            $options[self::OPT_ALL_REQUIRED] ?? true
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
                $list = $data['list'] ?? null;

                if ($list === null) {
                    return;
                }

                try {
                    $denormalized = $this->serializer->denormalize(
                        $list,
                        sprintf('%s[]', $type),
                        'json'
                    );
                } catch (\Throwable $e) {
                    $this->logger->warning('Failed to denormalize list', [
                        'type' => $list,
                        'error' => $e,
                    ]);

                    return; // Ignore, final denormalize should fail if so bad
                }

                $onChunk(
                    $denormalized,
                    $this->gp3Tokenizer->count($rawData) / (microtime(true) - $t0),
                );
            };
        }

        $data = $this->llm->get(
            $schema,
            $context,
            [],
            null,
            $llmOnChunk,
        );

        return $this->serializer->denormalize(
            $data['list'],
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
                }
            }

            unset($node['deprecated']);
            if ($node['description'] ?? null === '') {
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
                    $node = $this->mapSchema($node['anyOf'][0], $schema, $makeAllRequired);
                }
            }

            return \Psl\Dict\map(
                $node,
                fn ($value) => $this->mapSchema($value, $schema, $makeAllRequired)
            );
        }

        return $node;
    }
}
