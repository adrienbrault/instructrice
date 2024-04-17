<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ArrayObject;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use Psl\Type\TypeInterface;

use function Psl\Vec\filter;

class SchemaFactory
{
    public function __construct(
        private readonly SchemaFactoryInterface $schemaFactory,
    ) {
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

            if (\is_array($node['type'] ?? null)
                && \in_array('null', $node['type'], true)
            ) {
                $node['type'] = array_diff($node['type'], ['null']);
                $node['nullable'] = true;

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
     *
     * @return array<string, mixed>
     */
    public function createListSchema(TypeNode|TypeInterface|string $type, bool $makeAllRequired, string $propertyName): array
    {
        return [
            'type' => 'object',
            'properties' => [
                $propertyName => [
                    'type' => 'array',
                    'items' => $this->createSchema($type, $makeAllRequired),
                ],
            ],
            'required' => [$propertyName],
        ];
    }

    /**
     * @template T
     *
     * @param TypeNode|TypeInterface<T>|class-string<T> $type
     */
    public function createSchema(TypeNode|TypeInterface|string $type, bool $makeAllRequired): mixed
    {
        if (\is_string($type)) {
            $schema = $this->schemaFactory->buildSchema($type);

            return $this->mapSchema(
                $schema->getArrayCopy(),
                $schema,
                $makeAllRequired
            );
        }

        if (! $type instanceof TypeNode) {
            $type = (new TypeParser())->parse(
                new TokenIterator((new Lexer())->tokenize($type->toString()))
            );
        }

        return $this->createSchemaFromPhpStanType($type, $makeAllRequired);
    }

    /**
     * @return array<string, mixed>
     */
    public function createSchemaFromPhpStanType(TypeNode $phpstanType, bool $makeAllRequired): array
    {
        if ($phpstanType instanceof ArrayShapeNode) {
            $schema = [
                'type' => 'object',
                'properties' => [],
            ];

            foreach ($phpstanType->items as $item) {
                if ($item->keyName === null) {
                    continue;
                }

                $schema['properties'][(string) $item->keyName] = $this->createSchema($item->valueType, $makeAllRequired);

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
