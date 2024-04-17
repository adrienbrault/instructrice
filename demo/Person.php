<?php

declare(strict_types=1);

namespace Demo;

use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class Person
{
    #[NotBlank]
    #[Regex(
        '/ /',
        message: 'Include both the first and last name.'
    )]
    #[Regex(
        '/@/',
        message: 'Only include the first and last name.',
        match: false,
    )]
    #[Regex(
        '/^([A-Z][^A-Z]* ?)+$/',
        message: 'Format like this: First Last',
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

    /**
     * @var list<Interest>|null
     */
    public array $interests = [];
}
