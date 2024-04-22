<?php

declare(strict_types=1);
use AdrienBrault\Instructrice\InstructriceFactory;
use AdrienBrault\Instructrice\LLM\ProviderModel\ProviderModel;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
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

return function (callable $do, ?ProviderModel $llm = null) {
    $inputDefinition = new InputDefinition([
        new InputArgument('context', InputArgument::OPTIONAL),
        new InputOption('verbose', 'v'),
    ]);

    $input = new ArgvInput(null, $inputDefinition);
    $context = $input->getArgument('context');
    if (is_string($context) && file_exists($context)) {
        $context = file_get_contents($context);
    }

    $output = new ConsoleOutput($input->hasParameterOption('-v', true) ? ConsoleOutput::VERBOSITY_DEBUG : ConsoleOutput::VERBOSITY_NORMAL);

    $logger = createConsoleLogger($output);

    if ($llm === null) {
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
        $llm = $providerModels[$llmToUse];
    } else {
        $llmToUse = $llm->getLabel();
    }

    $output->writeln(sprintf('Using LLM: <info>%s</info>', $llmToUse));
    $output->writeln('');

    $instructrice = InstructriceFactory::create(
        llm: $llm,
        logger: $logger,
    );

    $do($instructrice, $context, $output);
};
