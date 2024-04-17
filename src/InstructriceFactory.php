<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice;

use AdrienBrault\Instructrice\Attribute\Instruction;
use AdrienBrault\Instructrice\LLM\Factory\Ollama;
use AdrienBrault\Instructrice\LLM\LLMInterface;
use ApiPlatform\JsonSchema\Metadata\Property\Factory\SchemaPropertyMetadataFactory;
use ApiPlatform\JsonSchema\SchemaFactory;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\AttributePropertyMetadataFactory;
use ApiPlatform\Metadata\Property\Factory\DefaultPropertyMetadataFactory;
use ApiPlatform\Metadata\Property\Factory\PropertyInfoPropertyMetadataFactory;
use ApiPlatform\Metadata\Property\Factory\PropertyInfoPropertyNameCollectionFactory;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\AttributesResourceMetadataCollectionFactory;
use ApiPlatform\Metadata\Resource\Factory\AttributesResourceNameCollectionFactory;
use ApiPlatform\Metadata\ResourceClassResolver;
use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;
use Gioni06\Gpt3Tokenizer\Gpt3TokenizerConfig;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\VarDumper\Caster\ReflectionCaster;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\VarDumper;

class InstructriceFactory
{
    /**
     * @param list<string> $directories
     */
    public static function create(
        ?LLMInterface $llm = null,
        ?LoggerInterface $logger = null,
        array $directories = [],
    ): Instructrice {
        $logger ??= new NullLogger();
        $llm ??= (new Ollama(logger: $logger))->hermes2pro();

        $propertyInfo = self::createPropertyInfoExtractor();
        $schemaFactory = self::createSchemaFactory($propertyInfo, $directories);
        $serializer = self::createSerializer($propertyInfo);

        return new Instructrice(
            $llm,
            $logger,
            new Gpt3Tokenizer(new Gpt3TokenizerConfig()),
            $schemaFactory,
            $serializer
        );
    }

    public static function createOnChunkDump(ConsoleSectionOutput $section): callable
    {
        $cloner = new VarCloner();
        $cloner->addCasters(ReflectionCaster::UNSET_CLOSURE_FILE_INFO);
        $dumper = new CliDumper(function (string $line, int $depth, string $indentPad) use ($section): void {
            if ($depth > 0) {
                $line = str_repeat($indentPad, $depth) . $line;
            }
            $section->writeln($line);
        });
        $dumper->setColors(true);
        VarDumper::setHandler(function ($var, ?string $label = null) use ($cloner, $dumper) {
            $var = $cloner->cloneVar($var);

            if ($label !== null) {
                $var = $var->withContext([
                    'label' => $label,
                ]);
            }

            $dumper->dump($var);
        });

        return function (array $data, float $tokensPerSecond) use ($section) {
            $section->clear();
            dump($data, sprintf('%.1f tokens/s', $tokensPerSecond));
        };
    }

    public static function createPropertyInfoExtractor(): PropertyInfoExtractor
    {
        return new PropertyInfoExtractor(
            [$reflection = new ReflectionExtractor()],
            [new PhpStanExtractor(), $phpdoc = new PhpDocExtractor(), new ReflectionExtractor()],
            [$phpdoc],
            [$reflection],
            [$reflection],
        );
    }

    /**
     * @param list<string> $directories
     */
    public static function createSchemaFactory(PropertyInfoExtractor $propertyInfo, array $directories): SchemaFactory
    {
        $resourceClassResolver = new ResourceClassResolver(
            new AttributesResourceNameCollectionFactory($directories)
        );

        return new SchemaFactory(
            null,
            new AttributesResourceMetadataCollectionFactory(null),
            new PropertyInfoPropertyNameCollectionFactory($propertyInfo),
            new class(new SchemaPropertyMetadataFactory($resourceClassResolver, new AttributePropertyMetadataFactory(new PropertyInfoPropertyMetadataFactory($propertyInfo, new DefaultPropertyMetadataFactory())))) implements PropertyMetadataFactoryInterface {
                public function __construct(
                    private readonly ?PropertyMetadataFactoryInterface $decorated = null
                ) {
                }

                /**
                 * @param class-string            $resourceClass
                 * @param array<array-key, mixed> $options
                 */
                public function create(string $resourceClass, string $property, array $options = []): ApiProperty
                {
                    if ($this->decorated === null) {
                        $apiProperty = new ApiProperty();
                    } else {
                        $apiProperty = $this->decorated->create($resourceClass, $property, $options);
                    }

                    $reflectionClass = new ReflectionClass($resourceClass);

                    if (! $reflectionClass->hasProperty($property)) {
                        return $apiProperty;
                    }

                    $reflectionProperty = $reflectionClass->getProperty($property);
                    $attributes = $reflectionProperty->getAttributes(Instruction::class);
                    foreach ($attributes as $attribute) {
                        $instruction = $attribute->newInstance();
                        \assert($instruction instanceof Instruction);
                        if ($instruction->description !== null) {
                            $apiProperty = $apiProperty->withSchema([
                                'description' => $instruction->description,
                                ...($apiProperty->getSchema() ?? []),
                            ]);
                        }
                    }

                    return $apiProperty;
                }
            },
            null,
            $resourceClassResolver
        );
    }

    public static function createSerializer(PropertyInfoExtractor $propertyInfo): Serializer
    {
        return new Serializer(
            [
                new DateTimeNormalizer(),
                new BackedEnumNormalizer(),
                new PropertyNormalizer(
                    propertyTypeExtractor: $propertyInfo,
                ),
                new ObjectNormalizer(
                    propertyTypeExtractor: $propertyInfo,
                ),
                new GetSetMethodNormalizer(
                    propertyTypeExtractor: $propertyInfo,
                ),
                new ArrayDenormalizer(),
            ],
            [new JsonEncoder()]
        );
    }
}
