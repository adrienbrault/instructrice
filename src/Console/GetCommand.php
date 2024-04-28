<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\Console;

use AdrienBrault\Instructrice\Instructrice;
use AdrienBrault\Instructrice\LLM\LLMChunk;
use AdrienBrault\Instructrice\LLM\LLMFactory;
use GuzzleHttp\Client;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

use function Psl\Dict\filter_keys;
use function Psl\IO\input_handle;
use function Psl\Iter\contains;
use function Psl\Iter\first;
use function Psl\Iter\first_key;
use function Psl\Json\decode;
use function Psl\Json\encode;
use function Psl\Regex\matches;
use function Psl\Regex\replace;

class GetCommand extends Command
{
    public function __construct(
        private readonly Instructrice $instructrice,
        private readonly LLMFactory $llmFactory,
        private readonly Client $http,
        private readonly VarCloner $cloner,
        private readonly SerializerInterface&NormalizerInterface $serializer,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('get')
            ->addArgument(
                'type',
                InputArgument::OPTIONAL,
                'The schema to use. Accepts: path to a yaml/json file, php FQCN.'
            )
            ->addArgument(
                'context',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'The context to use'
            )
            ->addOption(
                'prompt',
                'p',
                InputArgument::OPTIONAL,
                'The prompt to use',
                Instructrice::DEFAULT_PROMPT
            )
            ->addOption(
                'llm',
                'm',
                InputOption::VALUE_OPTIONAL,
                'The LLM to use. It is kind of fuzzy, you can match "OpenAI - GPT-4 Turbo" with --llm oai-4t'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'The output format to use. Accepts: yaml, json',
            )
            ->addOption('eval', 'e', InputOption::VALUE_OPTIONAL, 'Run an eval')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'Extract a list')
            ->addOption('all-required', null, InputOption::VALUE_NONE, 'Require all fields to be present in the output')
            ->addOption('dont-truncate-automatically', null, InputOption::VALUE_NONE, 'Dont truncate the output automatically')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $headSection = null;
        if (posix_isatty(\STDOUT) && $output instanceof ConsoleOutput) {
            $headSection = $output->section();
        }

        [$llm, $llmLabel] = $this->getLLM($input, $output);
        $type = $this->getType($input);
        $schema = null;

        // remove non whitelist properties, that arent standard json schema properties valid at the root
        if (\is_array($type)) {
            $schema = $type;
            $type = filter_keys(
                $type,
                fn ($key) => contains(['type', 'items', 'properties', 'required', 'anyOf', 'description'], $key)
            );
        }

        $evalOption = $input->getOption('eval');
        $eval = null;
        if (\is_string($evalOption)) {
            if (! \is_array($schema)) {
                throw new InvalidArgumentException('You must provide a schema to run an eval.');
            }

            $eval = $schema['evals'][$evalOption] ?? null;
            if ($eval === null) {
                throw new InvalidArgumentException(sprintf('The eval "%s" does not exist.', $evalOption));
            }

            $context = $eval['context'];
        } else {
            $context = $this->getContext($input, $headSection);
        }

        $prompt = $input->getOption('prompt');

        $chunkSection = null;
        if ($headSection !== null) {
            $headSection->writeln('Using LLM: ' . $llmLabel);
            if ($headSection->isVerbose()) {
                $headSection->writeln('Type: ' . encode($type));
                $headSection->writeln('Prompt: ' . $prompt);
                $headSection->writeln('Context: ' . $context);
            }
            $headSection->writeln('');

            if ($output instanceof ConsoleOutput) {
                $chunkSection = $output->section();
            }
        }

        $options = [
            'all_required' => $input->getOption('all-required') === true,
            'truncate_automatically' => $input->getOption('dont-truncate-automatically') !== true,
        ];

        if ($input->getOption('list') || ($eval['list'] ?? false)) {
            $result = $this->instructrice->list(
                type: $type,
                context: $context,
                prompt: $prompt,
                llm: $llm,
                options: $options,
                onChunk: $this->getOnChunk($input, $chunkSection, $eval)
            );
        } else {
            $result = $this->instructrice->get(
                type: $type,
                context: $context,
                prompt: $prompt,
                llm: $llm,
                options: $options,
                onChunk: $this->getOnChunk($input, $chunkSection, $eval)
            );
        }

        $chunkSection->clear();
        $headSection?->clear();

        if ($eval !== null) {
            $this->runEval($eval['result'], $result);
        }

        $format = $input->getOption('format');
        if (! posix_isatty(\STDOUT)) {
            $format ??= 'json';
        }

        $resultData = $this->serializer->normalize($result, 'json');
        if ($format === 'json') {
            $output->writeln(
                $this->serializer->serialize($result, 'json', [
                    'json_encode_options' => \JSON_PRETTY_PRINT,
                ])
            );
        } else {
            $output->writeln(
                Yaml::dump(
                    $resultData,
                    inline: 10,
                    indent: 2,
                    flags: Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE
                )
            );
        }

        if (\is_array($type) && \is_string($schema['path']) && str_ends_with($schema['path'], '.yaml')) {
            // append the result to the yaml file
            $name = Ulid::generate();
            $append = Yaml::dump(
                [
                    $name => [
                        'model' => $llmLabel,
                        'list' => $input->getOption('list') === true,
                        'context' => $context,
                        'result' => $resultData,
                    ],
                ],
                inline: 10,
                indent: 2,
                flags: Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE
            );

            // Works for now :troll:. I don't want to lose comments in the YAML
            $append = preg_replace('/^/m', '  ', $append);

            file_put_contents($schema['path'], $append, \FILE_APPEND);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: mixed, 1: string}
     */
    private function getLLM(InputInterface $input, OutputInterface $output): array
    {
        $llm = $input->getOption('llm');

        if (\is_string($llm)) {
            // I want oat4 to match openai gpt-4-turbo
            // So I will explode the string and generate a regex like this:
            // /o.*a.*t.*4/
            $pattern = '#' . implode('.*', str_split(replace($llm, '#[^a-z0-9]#i', ''))) . '#i';

            $matches = filter_keys(
                $this->llmFactory->getAvailableProviderModels(),
                fn (string $label) => matches($label, $pattern)
            );

            if (\count($matches) > 1) {
                // display all matches, asking to try again with a more specific name
                $output->writeln('<error>Multiple matches found:</error>');
                foreach ($matches as $label => $match) {
                    $output->writeln(' - ' . $label);
                }
                $output->writeln('');
            }

            $llm = first($matches);
            $llmLabel = first_key($matches);
        }

        return [$llm, $llmLabel];
    }

    private function getOnChunk(InputInterface $input, ConsoleSectionOutput $output, ?array $eval)
    {
        if (! posix_isatty(\STDOUT)) {
            return null;
        }

        $cliDumper = new CliDumper(function (string $line, int $depth, string $indentPad) use ($output): void {
            if ($depth > 0) {
                $line = str_repeat($indentPad, $depth) . $line;
            }
            $output->writeln($line);
        });
        $cliDumper->setColors(true);
        $display = function ($var, ?string $label = null) use ($cliDumper) {
            $var = $this->cloner->cloneVar($var);

            if ($label !== null) {
                $var = $var->withContext([
                    'label' => $label,
                ]);
            }

            $cliDumper->dump($var);
        };

        $lastPropertyPath = '';
        $renderOnEveryUpdate = true;

        return function (mixed $data, LLMChunk $chunk) use ($output, &$lastPropertyPath, $renderOnEveryUpdate, $display, $eval) {
            if (! $renderOnEveryUpdate) {
                $propertyPath = $chunk->propertyPath;
                if ($lastPropertyPath === $propertyPath) {
                    return;
                }

                $lastPropertyPath = $propertyPath;
            }

            if (! $output->isVeryVerbose()) {
                $output->clear();
            }

            $display($data);
            $display($chunk->propertyPath);
            $display(sprintf(
                '[Prompt: %d tokens - %s] -> [TTFT: %s] -> [Completion: %d tokens - %s - %.1f tokens/s] -> [Total: %d tokens - %s]',
                $chunk->promptTokens,
                $chunk->getFormattedCost(),
                $chunk->getTimeToFirstToken()->forHumans(),
                $chunk->completionTokens,
                $chunk->getFormattedCompletionCost(),
                $chunk->getTokensPerSecond(),
                $chunk->getTokens(),
                $chunk->getFormattedCost()
            ));

            if ($eval !== null) {
                $similarity = $this->gpt4MadeUpSimilarity($eval['result'], $data);

                $display(
                    sprintf('Eval similarity score: %.1f%%', $similarity * 100)
                );
            }
        };
    }

    private function getContext(InputInterface $input, ?ConsoleSectionOutput $output): mixed
    {
        $inputHandler = input_handle();
        $context = $inputHandler->tryRead(max_bytes: 1024 * 1024);

        if ($context === '') {
            $context = $input->getArgument('context') ?? [];

            if ($context === [] || ! \is_array($context)) {
                throw new InvalidArgumentException('You must provide a context, through stdin or as an argument.');
            }

            $context = implode(' ', $context);
        }

        if (\is_string($context) && file_exists($context)) {
            if (mb_check_encoding($context, 'UTF-8') === false) {
                throw new InvalidArgumentException('The context must be a valid UTF-8 string.');
            }

            $context = file_get_contents($context);
        }

        if (preg_match('/^https?:\/\//', $context)) {
            if ($output !== null) {
                $output->writeln(sprintf(
                    'Using r.jina.ai to scrape and convert %s to markdown.',
                    $context
                ));
            }

            $context = $this->http->get('https://r.jina.ai/' . $context)->getBody()->getContents();
            $context = replace($context, '#[(]([^)]{0,200})[^)]*[)]#', '($1...)');
            $context = replace($context, '#[(]data:[^)]+[)]#', '(...)');
            if ($output !== null) {
                $output->writeln('Done.');
            }
        }

        return $context;
    }

    private function getType(InputInterface $input): mixed
    {
        $type = $input->getArgument('type');

        if (\is_string($type) && file_exists($type)) {
            $path = null;
            if (matches($type, '#\.(yaml|yml)$#i')) {
                if (! class_exists(Parser::class)) {
                    throw new RuntimeException('The symfony/yaml package is required to read yaml files.');
                }

                $path = $type;
                $type = (new Parser())->parseFile($type);
            } elseif (str_ends_with($type, '.json')) {
                $path = $type;
                $type = decode(file_get_contents($type));
            }

            if ($path !== null) {
                $type['path'] = $path;
            }

            // handle/remove/transform custom instructrice properties like:
            // prompt: 'The prompt to use'
            // few shot examples: [ input -> output ]
            // evals: [ input -> output ]
            // logs: [ input -> output ]

            // So when you run instructrice, it adds each input/output to logs
            // Then as a user, you can cut that, put it within evals, and define exactly the output you expect for that input
            // You can then run instructrice evals something
            // If you end up collecting a set of good quality input/output pairs, consider moving some to the few shot examples
            // this should improve the accuracy at the cost of more prompt tokens
        }
        if (\is_string($type)) {
            $type = replace($type, '#/#i', '\\');
        }

        return $type;
    }

    private function runEval(mixed $expected, mixed $result): void
    {
        // look into https://github.com/Geo3ngel/JSON-Similarity-comparitor/tree/master
        // Normalize the eval result and extracted result to JSON
        $similarity = $this->gpt4MadeUpSimilarity($expected, $result);

        dump(
            sprintf('Eval similarity score: %.1f%%', $similarity * 100)
        );
    }

    public function recursiveKsort(&$array)
    {
        if (! \is_array($array)) {
            return;
        }
        foreach ($array as &$value) {
            if (\is_array($value)) {
                $this->recursiveKsort($value);
            }
        }
        ksort($array);
    }

    // Flatten the arrays to strings to compare them
    public function arrayFlatten($array)
    {
        if (! \is_array($array)) {
            return [$array];
        }
        $result = [];
        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $subArray = $this->arrayFlatten($value);
                foreach ($subArray as $subKey => $subValue) {
                    $result[$key . '.' . $subKey] = $subValue;
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function gpt4MadeUpSimilarity(mixed $input1, mixed $input2): float
    {
        // Normalize arrays by sorting by keys
        $this->recursiveKsort($input1);
        $this->recursiveKsort($input2);

        $flat1 = $this->arrayFlatten($input1);
        $flat2 = $this->arrayFlatten($input2);

        if (($flat1 === null && $flat2 !== null) || ($flat1 !== null && $flat2 === null)) {
            return 0;
        }

        // Calculate similarity score
        $allKeys = array_unique(array_merge(array_keys($flat1), array_keys($flat2)));
        $totalSimilarity = 0;
        $totalPossible = \count($allKeys);

        foreach ($allKeys as $key) {
            if (\array_key_exists($key, $flat1) && \array_key_exists($key, $flat2)) {
                if ($flat1[$key] === $flat2[$key]) {
                    ++$totalSimilarity; // Full point for exact match
                } elseif (\is_string($flat1[$key]) && \is_string($flat2[$key])) {
                    $similarityPercent = 0;

                    // todo maybe consider embedding distance/cosine thing?

                    similar_text($flat1[$key], $flat2[$key], $similarityPercent);
                    $totalSimilarity += $similarityPercent / 100; // Add fractional similarity for strings
                }
            }
        }

        // Calculate score
        $score = $totalPossible > 0 ? $totalSimilarity / $totalPossible : 0;

        return $score;
    }
}
