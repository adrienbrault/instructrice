<?php

declare(strict_types=1);
use AdrienBrault\Instructrice\Attribute\Instruction;
use AdrienBrault\Instructrice\Instructrice;
use AdrienBrault\Instructrice\InstructriceFactory;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class Interest
{
    public ?string $name = null;

    #[Instruction('A set of keywords to to learn more about this interest. Write in French.')]
    public ?string $searchQueryToLearnMore = null;
}
class Person
{
    public ?string $name = null;
    public ?string $biography = null;
    /**
     * @var list<Interest>
     */
    public array $interests = [];
}

$demo = require __DIR__ . '/bootstrap.php';
$demo(function (Instructrice $instructrice, ?string $context, ConsoleOutputInterface $output) {
    $persons = $instructrice->list(
        type: Person::class,
        context: $context ?? 'DAVID HEINEMEIER HANSSON aka @DHH, david cramer aka @zeeg',
        options: [
            'all_required' => true,
        ],
        onChunk: InstructriceFactory::createOnChunkDump($output->section()),
    );
});

/*
array:2 [
  0 => Person^ {#291
    +name: "David Heinemeier Hansson"
    +biography: null
    +interests: array:1 [
      0 => Interest^ {#277
        +name: "Ruby on Rails"
        +searchQueryToLearnMore: "ruby on rails créer un site web"
      }
    ]
  }
  1 => Person^ {#293
    +name: "David Cramer"
    +biography: null
    +interests: array:1 [
      0 => Interest^ {#289
        +name: "Sentry"
        +searchQueryToLearnMore: "sentry error tracking tool"
      }
    ]
  }
]
*/
