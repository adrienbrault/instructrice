<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice;

use AdrienBrault\Instructrice\Attribute\Prompt;
use AdrienBrault\Instructrice\Http\GuzzleStreamingClient;
use AdrienBrault\Instructrice\Http\StreamingClientInterface;
use AdrienBrault\Instructrice\LLM\LLMChunk;
use AdrienBrault\Instructrice\LLM\LLMFactory;
use AdrienBrault\Instructrice\LLM\Provider\Ollama;
use AdrienBrault\Instructrice\LLM\Provider\ProviderModel;
use ApiPlatform\JsonSchema\Metadata\Property\Factory\SchemaPropertyMetadataFactory;
use ApiPlatform\JsonSchema\SchemaFactory as ApiPlatformSchemaFactory;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\AttributePropertyMetadataFactory;
use ApiPlatform\Metadata\Property\Factory\DefaultPropertyMetadataFactory;
use ApiPlatform\Metadata\Property\Factory\PropertyInfoPropertyMetadataFactory;
use ApiPlatform\Metadata\Property\Factory\PropertyInfoPropertyNameCollectionFactory;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\AttributesResourceMetadataCollectionFactory;
use ApiPlatform\Metadata\Resource\Factory\AttributesResourceNameCollectionFactory;
use ApiPlatform\Metadata\ResourceClassResolver;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use Symfony\Component\Console\Helper\Dumper;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\VarDumper\Caster\ReflectionCaster;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\VarDumper;

class InstructriceFactory
{
    /**
     * @param list<string>                                     $directories
     * @param array<class-string<ProviderModel>, string>       $apiKeys
     * @param (SerializerInterface&DenormalizerInterface)|null $serializer
     */
    public static function create(
        ProviderModel|null $defaultLlm = null,
        LoggerInterface $logger = new NullLogger(),
        ?LLMFactory $llmFactory = null,
        array $directories = [],
        array $apiKeys = [],
        ?PropertyInfoExtractor $propertyInfo = null,
        $serializer = null,
    ): Instructrice {
        $propertyInfo ??= self::createPropertyInfoExtractor();
        $serializer ??= self::createSerializer($propertyInfo);
        $llmFactory ??= self::createLLMFactory(apiKeys: $apiKeys);

        $schemaFactory = new SchemaFactory(
            self::createApiPlatformSchemaFactory($propertyInfo, $directories)
        );

        return new Instructrice(
            $defaultLlm ?? Ollama::HERMES2PRO,
            $llmFactory,
            $logger,
            $schemaFactory,
            $serializer
        );
    }

    /**
     * @param array<class-string<ProviderModel>, string> $apiKeys
     */
    public static function createLLMFactory(
        ?StreamingClientInterface $httpClient = null,
        LoggerInterface $logger = new NullLogger(),
        array $apiKeys = [],
    ): LLMFactory {
        $httpClient ??= new GuzzleStreamingClient(
            new Client(['logger' => $logger]),
            $logger
        );

        return new LLMFactory($httpClient, $logger, $apiKeys);
    }

    public static function createOnChunkDump(ConsoleSectionOutput $section, bool $renderOnEveryUpdate = true): callable
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

        $lastPropertyPath = '';

        return function (mixed $data, LLMChunk $chunk) use ($section, &$lastPropertyPath, $renderOnEveryUpdate) {
            if (! $renderOnEveryUpdate) {
                $propertyPath = $chunk->propertyPath;
                if ($lastPropertyPath === $propertyPath) {
                    return;
                }

                $lastPropertyPath = $propertyPath;
            }

            if (! $section->isVeryVerbose()) {
                $section->clear();
            }

            dump(
                $data,
                $chunk->propertyPath,
                sprintf(
                    '[Prompt: %d tokens - %s] -> [TTFT: %s] -> [Completion: %d tokens - %s - %.1f tokens/s] -> [Total: %d tokens - %s]',
                    $chunk->promptTokens,
                    $chunk->getFormattedCost(),
                    $chunk->getTimeToFirstToken()->forHumans(),
                    $chunk->completionTokens,
                    $chunk->getFormattedCompletionCost(),
                    $chunk->getTokensPerSecond(),
                    $chunk->getTokens(),
                    $chunk->getFormattedCost()
                )
            );
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
    public static function createApiPlatformSchemaFactory(PropertyInfoExtractor $propertyInfo, array $directories): ApiPlatformSchemaFactory
    {
        $resourceClassResolver = new ResourceClassResolver(
            new AttributesResourceNameCollectionFactory($directories)
        );

        return new ApiPlatformSchemaFactory(
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
                    $attributes = $reflectionProperty->getAttributes(Prompt::class);
                    foreach ($attributes as $attribute) {
                        $prompt = $attribute->newInstance();
                        \assert($prompt instanceof Prompt);
                        if ($prompt->description !== null) {
                            $apiProperty = $apiProperty->withSchema([
                                'description' => $prompt->description,
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
        $encoders = [
            new JsonEncoder(),
        ];
        if (class_exists(Dumper::class)) {
            $encoders[] = new YamlEncoder();
        }

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
            $encoders
        );
    }
}
