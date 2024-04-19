<?php

declare(strict_types=1);
use AdrienBrault\Instructrice\Instructrice;
use AdrienBrault\Instructrice\InstructriceFactory;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

use function Psl\Vec\map;

$demo = require __DIR__ . '/bootstrap.php';
$demo(function (Instructrice $instructrice, ConsoleOutputInterface $output) {
    $contexts = [
        'Amazing',
        'Meh',
        'Horrible',
    ];

    $labels = map(
        $contexts,
        fn (string $context) => $instructrice->get(
            type: [
                'type' => 'string',
                'enum' => ['positive', 'neutral', 'negative'],
            ],
            context: $context,
            instructions: 'Sentiment analysis',
            onChunk: InstructriceFactory::createOnChunkDump($output->section()),
        )
    );

    dump(array_combine($contexts, $labels));
});
