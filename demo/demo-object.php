<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

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
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

$input = new ArgvInput();
$output = new ConsoleOutput($input->hasParameterOption('-v', true) ? ConsoleOutput::VERBOSITY_DEBUG : ConsoleOutput::VERBOSITY_NORMAL);

$logger = createConsoleLogger($output);

$llmRegistry = [
    'OpenAI GPT-4' => fn () => (new OpenAiFactory(logger: $logger))->gpt4(),
    'OpenAI GPT-3.5' => fn () => (new OpenAiFactory(logger: $logger))->gpt35(),
    'Ollama Hermes 2 Pro' => fn () => (new OllamaFactory(logger: $logger))->hermes2pro(),
    'Ollama DolphinCoder 7B' => fn () => (new OllamaFactory(logger: $logger))->dolphincoder7B('Q5_K_M'),
    'Mistral Small' => fn () => (new MistralFactory(logger: $logger))->mistralSmall(),
    'Mistral Large' => fn () => (new MistralFactory(logger: $logger))->mistralLarge(),
    'Fireworks Firefunction V1' => fn () => (new FireworksFactory(logger: $logger))->firefunctionV1(),
    'Fireworks Mixtral' => fn () => (new FireworksFactory(logger: $logger))->mixtral(),
    'Groq Mixtral' => fn () => (new GroqFactory(logger: $logger))->mixtral(),
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

$instructrice = InstructriceFactory::create(
    llm: $llmRegistry[$llmToUse](),
    logger: $logger,
);

class Person
{
    #[NotBlank]
    #[Regex(
        '/ /',
        message: 'Please include both the first and last name.'
    )]
    public ?string $name = null;

    #[NotBlank]
    #[Length(min: 75)]
    #[Regex(
        '/ (et|de|pour|est|connu) /i',
        message: 'The sentences must be written in french, not english.'
    )]
    #[Regex(
        '/DAMN/',
        message: 'You must include "DAMN".',
    )]
    public ?string $biography = null;
}

$tableSection = $output->section();
$table = new Table($tableSection);
$table->setHeaderTitle(sprintf('LLM: %s', $llmToUse));
$table->setColumnMaxWidth(0, 20);
$table->setColumnMaxWidth(1, 50);
$table->setHeaders(['Name', 'Biography']);
$table->setColumnWidths([20, 50]);
$table->render();

$form = $instructrice->fillCollection(
    context: 'Jason fried, david cramer',
    entryOptions: [
        'data_class' => Person::class,
    ],
    newEntryForm: fn (FormBuilderInterface $builder) => $builder
        ->add('name')
        ->add('biography', null, [
            'liform' => [
                // todo fill this using custom attributes
                'description' => 'Succintly describe the person\'s life.',
            ],
        ]),
    minEntries: 2,
    retries: 3,
    onChunk: function (FormInterface $form, float $tokensPerSecond) use ($tableSection, $table) {
        $listData = $form->get('list')->getData();

        $table->setRows([]);
        $yo = function (FormInterface $form) {
            $value = $form->getData();
            if (! $form->isValid()) {
                $value = sprintf('<error>%s</error>', $value ?? 'null');
            }
            return $value;
        };
        foreach ($form->get('list') as $personForm) {
            $table->addRow([
                $yo($personForm->get('name')),
                $yo($personForm->get('biography')),
            ]);
        }

        //        $table->setRows(
        //            array_map(
        //                fn (Person $person) => [$person->name, $person->biography],
        //                $listData,
        //            ),
        //        );
        $table->setFooterTitle(sprintf('%.1f tokens/s', $tokensPerSecond));

        $tableSection->clear();
        $table->render();
    },
);

dump([
    'data' => $form->getData(),
    'errors' => (string) $form->getErrors(true),
]);

function createConsoleLogger(OutputInterface $output): LoggerInterface
{
    return new Logger('instructrice', [
        new ConsoleHandler($output),
    ]);
}
