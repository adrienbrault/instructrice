<?php

declare(strict_types=1);
use AdrienBrault\Instructrice\InstructriceFactory;
use AdrienBrault\Instructrice\LLM\ProviderModel\ProviderModel;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

use function Psl\Dict\reindex;

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

    $providerModels = reindex(
        InstructriceFactory::createAvailableProviderModels(),
        fn (ProviderModel $providerModel) => $providerModel->getLabel(),
    );

    $questionSection = $output->section();
    $questionHelper = new QuestionHelper();
    $llmToUse = $questionHelper->ask($input, $questionSection, new ChoiceQuestion(
        'Which LLM do you want to use?',
        array_keys($providerModels),
        0,
    ));
    $questionSection->clear();
    $output->writeln(sprintf('Using LLM: <info>%s</info>', $llmToUse));
    $output->writeln('');

    $instructrice = InstructriceFactory::create(
        llm: $providerModels[$llmToUse],
        logger: $logger,
    );

    $do($instructrice, $output);
};
