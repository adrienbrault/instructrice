<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice;

use AdrienBrault\Instructrice\Attribute\Instruction;
use AdrienBrault\Instructrice\Http\GuzzleStreamingClient;
use AdrienBrault\Instructrice\Http\StreamingClientInterface;
use AdrienBrault\Instructrice\LLM\LLMChunk;
use AdrienBrault\Instructrice\LLM\LLMConfig;
use AdrienBrault\Instructrice\LLM\LLMFactory;
use AdrienBrault\Instructrice\LLM\LLMInterface;
use AdrienBrault\Instructrice\LLM\Parser\JsonParser;
use AdrienBrault\Instructrice\LLM\Parser\ParserInterface;
use AdrienBrault\Instructrice\LLM\ProviderModel\Anthropic;
use AdrienBrault\Instructrice\LLM\ProviderModel\Deepinfra;
use AdrienBrault\Instructrice\LLM\ProviderModel\Fireworks;
use AdrienBrault\Instructrice\LLM\ProviderModel\Groq;
use AdrienBrault\Instructrice\LLM\ProviderModel\Mistral;
use AdrienBrault\Instructrice\LLM\ProviderModel\Ollama;
use AdrienBrault\Instructrice\LLM\ProviderModel\OpenAi;
use AdrienBrault\Instructrice\LLM\ProviderModel\ProviderModel;
use AdrienBrault\Instructrice\LLM\ProviderModel\Together;
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
use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;
use Gioni06\Gpt3Tokenizer\Gpt3TokenizerConfig;
use GuzzleHttp\Client;
use InvalidArgumentException;
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

use function Psl\Vec\filter;

class InstructriceFactory
{
    /**
     * @param list<string> $directories
     */
    public static function create(
        LLMInterface|LLMConfig|ProviderModel|null $llm = null,
        ?LoggerInterface $logger = null,
        array $directories = [],
        ?StreamingClientInterface $httpClient = null
    ): Instructrice {
        $logger ??= new NullLogger();
        $httpClient ??= new GuzzleStreamingClient(new Client(), $logger);

        if ($llm === null) {
            $llm = Ollama::HERMES2PRO->createConfig('sk-xxx');
        }

        if ($llm instanceof ProviderModel) {
            $apiKey = self::getProviderModelApiKey($llm, true) ?? 'sk-xxx';
            $llm = $llm->createConfig($apiKey);
        }

        if ($llm instanceof LLMConfig) {
            $llmFactory = self::createLLMFactory($httpClient, $logger);
            $llm = $llmFactory->create($llm);
        }

        $propertyInfo = self::createPropertyInfoExtractor();
        $schemaFactory = new SchemaFactory(
            self::createApiPlatformSchemaFactory($propertyInfo, $directories)
        );
        $serializer = self::createSerializer($propertyInfo);

        return new Instructrice(
            $llm,
            $logger,
            $schemaFactory,
            $serializer
        );
    }

    private static function getProviderModelApiKey(ProviderModel $providerModel, bool $throwWhenMissing = false): ?string
    {
        $apiKeyEnvVar = $providerModel->getApiKeyEnvVar();

        if ($apiKeyEnvVar === null) {
            return 'sk-xxx';
        }

        $apiKey = getenv($apiKeyEnvVar) ?: null;

        if ($apiKey !== null) {
            return $apiKey;
        }

        if ($throwWhenMissing) {
            throw new InvalidArgumentException(sprintf('In order to use %s please set the %s environment variable', $providerModel->getLabel(), $apiKeyEnvVar));
        }

        return null;
    }

    /**
     * @return list<ProviderModel>
     */
    public static function createAvailableProviderModels(): array
    {
        $providerModels = [
            ...OpenAi::cases(),
            ...Ollama::cases(),
            ...Anthropic::cases(),
            ...Mistral::cases(),
            ...Groq::cases(),
            ...Fireworks::cases(),
            ...Together::cases(),
            ...Deepinfra::cases(),
        ];

        return filter(
            $providerModels,
            fn (ProviderModel $providerModel): bool => self::getProviderModelApiKey($providerModel, false) !== null
        );
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

        return function (array $data, LLMChunk $profile) use ($section, &$lastPropertyPath, $renderOnEveryUpdate) {
            if (! $renderOnEveryUpdate) {
                $propertyPath = $profile->getDataLastPropertyPath();
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
                sprintf(
                    '[Prompt: %d tokens - %s] -> [TTFT: %s] -> [Completion: %d tokens - %s - %.1f tokens/s] -> [Total: %d tokens - %s]',
                    $profile->promptTokens,
                    $profile->getFormattedCost(),
                    $profile->getTimeToFirstToken()->forHumans(),
                    $profile->completionTokens,
                    $profile->getFormattedCompletionCost(),
                    $profile->getTokensPerSecond(),
                    $profile->getTokens(),
                    $profile->getFormattedCost()
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

    public static function createLLMFactory(
        ?StreamingClientInterface $httpClient = null,
        LoggerInterface $logger = new NullLogger(),
        Gpt3Tokenizer $tokenizer = new Gpt3Tokenizer(new Gpt3TokenizerConfig()),
        ParserInterface $parser = new JsonParser(),
    ): LLMFactory {
        $httpClient ??= new GuzzleStreamingClient(new Client(), $logger);

        return new LLMFactory($httpClient, $logger, $tokenizer, $parser);
    }
}
