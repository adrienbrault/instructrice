<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice;

use AdrienBrault\Instructrice\LLM\LLMInterface;
use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;
use Psl\Type\TypeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

use function Psl\Type\vec;

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
        private readonly SchemaFactory $schemaFactory,
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
        $listPropertyName = 'list';

        $schema = $this->schemaFactory->createListSchema(
            $type,
            $options['all_required'] ?? false,
            $listPropertyName
        );

        $llmOnChunk = null;
        if ($onChunk !== null) {
            $t0 = microtime(true);
            $llmOnChunk = function (mixed $data, string $rawData) use ($type, $listPropertyName, $onChunk, $t0) {
                try {
                    $denormalized = $this->denormalizeList($data, $type, $listPropertyName);
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
            $llmOnChunk,
        );

        return $this->denormalizeList($data, $type, $listPropertyName) ?? [];
    }

    /**
     * @template T
     *
     * @param class-string<T>|TypeInterface<T> $type
     *
     * @return list<T>|null
     */
    private function denormalizeList(mixed $data, string|TypeInterface $type, string $listPropertyName): ?array
    {
        if (! \is_array($data)) {
            return null;
        }

        $list = $data[$listPropertyName] ?? null;

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
}
