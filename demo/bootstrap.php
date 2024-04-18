<?php

declare(strict_types=1);

use AdrienBrault\Instructrice\InstructriceFactory;
use AdrienBrault\Instructrice\LLM\Config\Anthropic;
use AdrienBrault\Instructrice\LLM\Config\LLMConfig;
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

    $openAiCompatibleLLMFactory = InstructriceFactory::createOpenAiCompatibleLLMFactory();
    $llmRegistry = [];
    foreach ($openAiCompatibleLLMFactory->createAvailable() as $llmConfig) {
        $llmRegistry[$llmConfig->label] = $llmConfig;
    }
    $anthropicFactory = new Anthropic(logger: $logger);

    $llmRegistry['Anthropic - Haiku'] = fn () => $anthropicFactory->haiku();
    $llmRegistry['Anthropic - Sonnet'] = fn () => $anthropicFactory->sonnet();
    $llmRegistry['Anthropic - Opus'] = fn () => $anthropicFactory->opus();

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

    $llmConfig = $llmRegistry[$llmToUse];
    if ($llmConfig instanceof LLMConfig) {
        $llm = $openAiCompatibleLLMFactory->create($llmConfig);
    } else {
        $llm = $llmConfig();
    }

    $instructrice = InstructriceFactory::create(
        llm: $llm,
        logger: $logger,
    );

    $do($instructrice, $output);
};
