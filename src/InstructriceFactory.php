<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice;

use AdrienBrault\Instructrice\LLM\LLMInterface;
use AdrienBrault\Instructrice\LLM\OllamaFactory;
use ApiPlatform\JsonSchema\Metadata\Property\Factory\SchemaPropertyMetadataFactory;
use ApiPlatform\JsonSchema\SchemaFactory;
use ApiPlatform\Metadata\Property\Factory\AttributePropertyMetadataFactory;
use ApiPlatform\Metadata\Property\Factory\DefaultPropertyMetadataFactory;
use ApiPlatform\Metadata\Property\Factory\PropertyInfoPropertyMetadataFactory;
use ApiPlatform\Metadata\Property\Factory\PropertyInfoPropertyNameCollectionFactory;
use ApiPlatform\Metadata\Resource\Factory\AttributesResourceMetadataCollectionFactory;
use ApiPlatform\Metadata\Resource\Factory\AttributesResourceNameCollectionFactory;
use ApiPlatform\Metadata\ResourceClassResolver;
use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;
use Gioni06\Gpt3Tokenizer\Gpt3TokenizerConfig;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
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
        $llm ??= (new OllamaFactory(logger: $logger))->hermes2pro();

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

    public static function createOnChunkDump(ConsoleOutputInterface $output): callable
    {
        $dumpSection = $output->section();

        $cloner = new VarCloner();
        $cloner->addCasters(ReflectionCaster::UNSET_CLOSURE_FILE_INFO);
        $dumper = new \Symfony\Component\VarDumper\Dumper\CliDumper(function (string $line, int $depth, string $indentPad) use ($dumpSection): void {
            if ($depth > 0) {
                $line = str_repeat($indentPad, $depth) . $line;
            }
            $dumpSection->writeln($line);
        });
        $dumper->setColors(true);
        \Symfony\Component\VarDumper\VarDumper::setHandler(function ($var, ?string $label = null) use ($cloner, $dumper) {
            $var = $cloner->cloneVar($var);

            if ($label !== null) {
                $var = $var->withContext([
                    'label' => $label,
                ]);
            }

            $dumper->dump($var);
        });

        return function (array $data, float $tokensPerSecond) use ($dumpSection) {
            $dumpSection->clear();
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
            new SchemaPropertyMetadataFactory(
                $resourceClassResolver,
                new AttributePropertyMetadataFactory(
                    new PropertyInfoPropertyMetadataFactory(
                        $propertyInfo,
                        new DefaultPropertyMetadataFactory()
                    )
                ),
            ),
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
