<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use AdrienBrault\Instructrice\InstructriceFactory;
use AdrienBrault\Instructrice\LLM\OllamaFactory;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

$logger = createConsoleLogger();
$instructrice = InstructriceFactory::create(
    logger: $logger,
    llm: (new OllamaFactory(logger: $logger))->hermes2pro(),
);

$form = $instructrice->fillCollection(
    context: 'Jason fried, david cramer',
    newEntryForm: fn (FormBuilderInterface $builder) => $builder
        ->add('name', TextType::class)
        ->add('biography', TextareaType::class, [
            'liform' => [
                'description' => 'Succintly describe the person\'s life.',
            ],
            'constraints' => [
                new Length([
                    'min' => 75,
                ]),
                new Regex(
                    '/ (et|de|pour|est|connu) /i',
                    message: 'The sentences must be written in french, not english.'
                ),
                new Regex(
                    '/DAMN/',
                    message: 'You must include "DAMN".',
                ),
            ],
        ]),
    retries: 3
);

dump('final result', $form->getData());

function createConsoleLogger(): LoggerInterface
{
    return new Logger('instructrice', [
        new ConsoleHandler(new ConsoleOutput(ConsoleOutput::VERBOSITY_DEBUG)),
    ]);
}
