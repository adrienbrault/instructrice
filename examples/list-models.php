<?php

declare(strict_types=1);
use AdrienBrault\Instructrice\Attribute\Prompt;
use AdrienBrault\Instructrice\Instructrice;
use AdrienBrault\Instructrice\InstructriceFactory;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class Model
{
    #[Prompt('The provider/software to run the LLM.')]
    public string $provider;

    #[Prompt('The model name like "GPT 3.5 Turbo".')]
    public string $name;

    #[Prompt('The context length/window, if 32k use 32000.')]
    public ?int $context = null;

    #[Prompt('One of text, json, function.')]
    public ?string $extractionStrategy = null;
}


$demo = require __DIR__ . '/bootstrap.php';
$demo(function (Instructrice $instructrice, ?string $context, ConsoleOutputInterface $output) {
    $instructrice->list(
        Model::class,
        $context ?? file_get_contents(__DIR__ . '/../README.md'),
        'Extract language models supported by instructrice.',
        [
            'truncate_automatically' => true,
            'all_required' => true,
        ],
        onChunk: InstructriceFactory::createOnChunkDump($output->section(), false),
    );
});

/*
php examples/list-models.php
*/
