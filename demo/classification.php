<?php

declare(strict_types=1);
use AdrienBrault\Instructrice\Instructrice;
use AdrienBrault\Instructrice\InstructriceFactory;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

$demo = require __DIR__ . '/bootstrap.php';
$demo(function (Instructrice $instructrice, ?string $context, ConsoleOutputInterface $output) {
    dump($instructrice->get(
        type: [
            'type' => 'string',
            'enum' => ['positive', 'neutral', 'negative'],
        ],
        context: $context,
        instructions: 'Sentiment analysis',
        onChunk: InstructriceFactory::createOnChunkDump($output->section()),
    ));
});

/*
php demo/classification.php 'LFG'

"positive"

php demo/classification.php 'decel'

"negative"

php demo/classification.php 'normy'

"neutral"
*/
