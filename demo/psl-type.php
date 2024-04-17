<?php

declare(strict_types=1);
use AdrienBrault\Instructrice\Instructrice;
use AdrienBrault\Instructrice\InstructriceFactory;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

use function Psl\Type\shape;
use function Psl\Type\string;

$demo = require __DIR__ . '/bootstrap.php';
$demo(function (Instructrice $instructrice, ConsoleOutputInterface $output) {
    $type = shape([
        'name' => string(),
        'bio' => string(),
    ]);

    $persons = $instructrice->deserializeList(
        context: 'DAVID HEINEMEIER HANSSON aka @DHH, david cramer aka @zeeg',
        type: $type,
        onChunk: InstructriceFactory::createOnChunkDump($output->section()),
    );
});
