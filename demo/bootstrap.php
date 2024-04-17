<?php

declare(strict_types=1);
use AdrienBrault\Instructrice\InstructriceFactory;
use AdrienBrault\Instructrice\LLM\Factory\Anthropic;
use AdrienBrault\Instructrice\LLM\Factory\Fireworks;
use AdrienBrault\Instructrice\LLM\Factory\Groq;
use AdrienBrault\Instructrice\LLM\Factory\Mistral;
use AdrienBrault\Instructrice\LLM\Factory\Ollama;
use AdrienBrault\Instructrice\LLM\Factory\OpenAi;
use AdrienBrault\Instructrice\LLM\Factory\Together;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

require __DIR__ . '/../vendor/autoload.php';

function createConsoleLogger(OutputInterface $output): LoggerInterface
{
    return new Logger('instructrice', [
        new ConsoleHandler($output),
    ]);
}

return function (callable $do) {
    $input = new ArgvInput();
    $output = new ConsoleOutput($input->hasParameterOption('-v', true) ? ConsoleOutput::VERBOSITY_DEBUG : ConsoleOutput::VERBOSITY_NORMAL);

    $logger = createConsoleLogger($output);

    $llmRegistry = [
        'OpenAI GPT-4' => fn () => (new OpenAi(logger: $logger))->gpt4(),
        'OpenAI GPT-3.5' => fn () => (new OpenAi(logger: $logger))->gpt35(),
        'Anthropic Haiku' => fn () => (new Anthropic(logger: $logger))->haiku(),
        'Anthropic Sonnet' => fn () => (new Anthropic(logger: $logger))->sonnet(),
        'Anthropic Opus' => fn () => (new Anthropic(logger: $logger))->opus(),
        'Ollama Hermes 2 Pro 7B' => fn () => (new Ollama(logger: $logger))->hermes2pro(),
        'Ollama DolphinCoder 7B' => fn () => (new Ollama(logger: $logger))->dolphincoder7('q5_K_M'),
        'Ollama Command R 35B' => fn () => (new Ollama(logger: $logger))->commandR('q5_K_M'),
        'Ollama Command R+ 104B' => fn () => (new Ollama(logger: $logger))->commandRPlus(),
        'Mistral Mixtral 8x7B' => fn () => (new Mistral(logger: $logger))->mixtral7(),
        'Mistral Mixtral 8x22B' => fn () => (new Mistral(logger: $logger))->mixtral22(),
        'Mistral Large' => fn () => (new Mistral(logger: $logger))->mistralLarge(),
        'Fireworks Firefunction V1' => fn () => (new Fireworks(logger: $logger))->firefunctionV1(),
        'Fireworks Mixtral 8x7B' => fn () => (new Fireworks(logger: $logger))->mixtral7(),
        'Fireworks Mixtral 8x22B' => fn () => (new Fireworks(logger: $logger))->mixtral22(),
        'Fireworks DBRX 132B' => fn () => (new Fireworks(logger: $logger))->dbrx(),
        'Fireworks Hermes 2 Pro 7B' => fn () => (new Fireworks(logger: $logger))->hermes2pro(),
        'Groq Mixtral 8x7B' => fn () => (new Groq(logger: $logger))->mixtral(),
        'Groq Gemma 7B' => fn () => (new Groq(logger: $logger))->gemma7(),
        'Together Mixtral 8x7B' => fn () => (new Together(logger: $logger))->mixtral7(),
        'Together Mistral 7B' => fn () => (new Together(logger: $logger))->mistral7(),
    ];

    $questionSection = $output->section();
    $questionHelper = new QuestionHelper();
    $llmToUse = $questionHelper->ask($input, $questionSection, new ChoiceQuestion(
        'Which LLM do you want to use?',
        array_keys($llmRegistry),
        0,
    ));
    $questionSection->clear();
    $output->writeln(sprintf('Using LLM: <info>%s</info>', $llmToUse));
    $output->writeln('');

    $instructrice = InstructriceFactory::create(
        llm: $llmRegistry[$llmToUse](),
        logger: $logger,
    );

    $do($instructrice, $output);
};
