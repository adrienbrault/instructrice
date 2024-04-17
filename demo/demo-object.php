<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use AdrienBrault\Instructrice\Attribute\Instruction;
use AdrienBrault\Instructrice\InstructriceFactory;
use AdrienBrault\Instructrice\LLM\FireworksFactory;
use AdrienBrault\Instructrice\LLM\GroqFactory;
use AdrienBrault\Instructrice\LLM\MistralFactory;
use AdrienBrault\Instructrice\LLM\OllamaFactory;
use AdrienBrault\Instructrice\LLM\OpenAiFactory;
use AdrienBrault\Instructrice\LLM\TogetherFactory;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

$input = new ArgvInput();
$output = new ConsoleOutput($input->hasParameterOption('-v', true) ? ConsoleOutput::VERBOSITY_DEBUG : ConsoleOutput::VERBOSITY_NORMAL);

$logger = createConsoleLogger($output);

$llmRegistry = [
    'OpenAI GPT-4' => fn () => (new OpenAiFactory(logger: $logger))->gpt4(),
    'OpenAI GPT-3.5' => fn () => (new OpenAiFactory(logger: $logger))->gpt35(),
    'Ollama Hermes 2 Pro' => fn () => (new OllamaFactory(logger: $logger))->hermes2pro(),
    'Ollama DolphinCoder 7B' => fn () => (new OllamaFactory(logger: $logger))->dolphincoder7B('q5_K_M'),
    'Ollama Command R' => fn () => (new OllamaFactory(logger: $logger))->commandR('q5_K_M'),
    'Ollama Command R+' => fn () => (new OllamaFactory(logger: $logger))->commandRPlus(),
    'Mistral Small' => fn () => (new MistralFactory(logger: $logger))->mistralSmall(),
    'Mistral Large' => fn () => (new MistralFactory(logger: $logger))->mistralLarge(),
    'Fireworks Firefunction V1' => fn () => (new FireworksFactory(logger: $logger))->firefunctionV1(),
    'Fireworks Mixtral' => fn () => (new FireworksFactory(logger: $logger))->mixtral(),
    'Fireworks Big Mixtral' => fn () => (new FireworksFactory(logger: $logger))->bigMixtral(),
    'Fireworks DBRX' => fn () => (new FireworksFactory(logger: $logger))->dbrx(),
    'Fireworks Hermes 2 Pro' => fn () => (new FireworksFactory(logger: $logger))->hermes2pro(),
    'Groq Mixtral' => fn () => (new GroqFactory(logger: $logger))->mixtral(),
    'Groq Gemma 7B' => fn () => (new GroqFactory(logger: $logger))->gemma7b(),
    'Together Mixtral' => fn () => (new TogetherFactory(logger: $logger))->mixtral(),
    'Together Mistral 7B' => fn () => (new TogetherFactory(logger: $logger))->mistral7B(),
];

$questionSection = $output->section();
$questionHelper = new QuestionHelper();
$llmToUse = $questionHelper->ask($input, $questionSection, new ChoiceQuestion(
    'Which LLM do you want to use?',
    array_keys($llmRegistry),
    0,
));
$questionSection->clear();

class Interest
{
    public ?string $name = null;

    #[Instruction(description: 'A set of keywords to to learn more about this interest. Write in French.')]
    public ?string $searchQueryToLearnMore = null;
}
class Person
{
    public ?string $name = null;
    public ?string $biography = null;
    /**
     * @var list<Interest>
     */
    public array $interests = [];
}

$instructrice = InstructriceFactory::create(
    llm: $llmRegistry[$llmToUse](),
    logger: $logger,
);

$persons = $instructrice->deserializeList(
    context: 'DAVID HEINEMEIER HANSSON aka @DHH, david cramer aka @zeeg',
    type: Person::class,
    onChunk: InstructriceFactory::createOnChunkDump($output),
);

function createConsoleLogger(OutputInterface $output): LoggerInterface
{
    return new Logger('instructrice', [
        new ConsoleHandler($output),
    ]);
}
