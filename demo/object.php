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
$demo(function (Instructrice $instructrice, ConsoleOutputInterface $output) {
    $persons = $instructrice->getList(
        context: 'DAVID HEINEMEIER HANSSON aka @DHH, david cramer aka @zeeg',
        type: Person::class,
        options: [
            'all_required' => true,
        ],
        onChunk: InstructriceFactory::createOnChunkDump($output->section()),
    );
});
