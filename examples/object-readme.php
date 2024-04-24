<?php

declare(strict_types=1);
use AdrienBrault\Instructrice\Attribute\Prompt;
use AdrienBrault\Instructrice\Instructrice;
use AdrienBrault\Instructrice\InstructriceFactory;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class Character
{
    #[Prompt('Just the first name.')]
    public string $name;

    #[Prompt('If applicable, the military rank.')]
    public ?string $rank = null;
}

$demo = require __DIR__ . '/bootstrap.php';
$demo(function (Instructrice $instructrice, ?string $context, ConsoleOutputInterface $output) {
    $character = $instructrice->get(
        Character::class,
        'Colonel Jack O\'Neil walks into a bar and meets Major Samanta Carter. They call Teal\'c to join them.',
        options: [
            'all_required' => true,
        ],
        onChunk: InstructriceFactory::createOnChunkDump($output->section()),
    );
});

/*
Character^ {#294
  +name: "Jack"
  +rank: "Colonel"
}
*/
