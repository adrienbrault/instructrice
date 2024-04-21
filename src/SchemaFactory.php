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

use function Psl\Regex\replace;
use function Psl\Vec\filter;

class SchemaFactory
{
    public function __construct(
        private readonly SchemaFactoryInterface $schemaFactory,
    ) {
    }

    /**
     * @param array<string, mixed>|ArrayObject<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private function mapSchema(array|ArrayObject $node, Schema $schema, bool $makeAllRequired): array
    {
        if ($node instanceof ArrayObject) {
            $node = $node->getArrayCopy();
        }
        if (! \is_array($node)) {
            return [];
        }

        $ref = $node['$ref'] ?? null;
        if (\is_string($ref)) {
            if (str_starts_with($ref, '#/definitions/')) {
                $ref = substr($ref, \strlen('#/definitions/'));
                $node = $schema->getDefinitions()[$ref];
                if ($node instanceof ArrayObject) {
                    $node = $node->getArrayCopy();
                }

                if (! \is_array($node)) {
                    return [];
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
            $required = $node['required'] ?? [];
            $required = \is_array($required) ? $required : [];
            $required = [
                ...$required,
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

            if (\count($node['anyOf']) === 1 && \is_array($node['anyOf'][0])) {
                return $this->mapSchema($node['anyOf'][0], $schema, $makeAllRequired);
            }
        }

        return array_map(
            function ($value) use ($schema, $makeAllRequired) {
                if (! \is_array($value) && ! $value instanceof ArrayObject) {
                    return $value;
                }

                return $this->mapSchema($value, $schema, $makeAllRequired);
            },
            $node,
        );
    }

    /**
     * @template T
     *
     * @param TypeNode|TypeInterface<T>|class-string<T> $type
     *
     * @return array<string, mixed>
     */
    public function createSchema(TypeNode|TypeInterface|string $type, bool $makeAllRequired): array
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
            $typeAsString = $type->toString();
            $typeAsString = replace($typeAsString, '#"#', "'");

            $type = (new TypeParser())->parse(
                new TokenIterator((new Lexer())->tokenize($typeAsString))
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
