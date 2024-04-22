<?php

declare(strict_types=1);
use AdrienBrault\Instructrice\Instructrice;
use AdrienBrault\Instructrice\InstructriceFactory;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

$demo = require __DIR__ . '/bootstrap.php';
$demo(function (Instructrice $instructrice, ?string $context, ConsoleOutputInterface $output) {
    $instructrice->get(
        Schema::object()
            ->properties(
                Schema::string('name'),
                Schema::string('dateOfBirth')->format(Schema::FORMAT_DATE_TIME),
                Schema::boolean('isAlive'),
                Schema::array('significantContributions')
                    ->items(Schema::string()),
            )
            ->toArray(),
        'Most impact scientists in history.',
        onChunk: InstructriceFactory::createOnChunkDump($output->section()),
    );
});

/*
array:4 [
  "name" => "Marie Curie"
  "dateOfBirth" => "1867-11-07"
  "isAlive" => false
  "significantContributions" => array:3 [
    0 => "Pioneered radioactivity research"
    1 => "First woman to win a Nobel Prize"
    2 => "First person to win two Nobel Prizes in different fields (Physics and Chemistry)"
  ]
]
*/
