<?php

declare(strict_types=1);
use AdrienBrault\Instructrice\Instructrice;
use AdrienBrault\Instructrice\InstructriceFactory;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

use function Psl\Type\shape;
use function Psl\Type\string;

$demo = require __DIR__ . '/bootstrap.php';
$demo(function (Instructrice $instructrice, ?string $context, ConsoleOutputInterface $output) {
    $type = shape([
        'name' => string(),
        'bio' => string(),
    ]);

    $persons = $instructrice->list(
        type: $type,
        context: $context ?? 'DAVID HEINEMEIER HANSSON aka @DHH, david cramer aka @zeeg',
        options: [
            'all_required' => true,
        ],
        onChunk: InstructriceFactory::createOnChunkDump($output->section()),
    );
});

/*
array:2 [
  0 => array:2 [
    "name" => "David Heinemeier Hansson"
    "bio" => "also known as @DHH"
  ]
  1 => array:2 [
    "name" => "David Cramer"
    "bio" => "also known as @zeeg"
  ]
]
*/
