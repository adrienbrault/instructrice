<?php

declare(strict_types=1);
use AdrienBrault\Instructrice\Instructrice;
use AdrienBrault\Instructrice\InstructriceFactory;
use AdrienBrault\Instructrice\LLM\ProviderModel\Perplexity;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class SocialMediaProfile
{
    public ?string $username = null;
    public ?string $displayName = null;
    public ?string $profileUrl = null;
}

$demo = require __DIR__ . '/bootstrap.php';
$demo(function (Instructrice $instructrice, ?string $context, ConsoleOutputInterface $output) {
    if ($context === null) {
        $context = 'aws twitter accounts';
        dump('Context: ' . $context);
    }

    $instructrice->getList(
        SocialMediaProfile::class,
        $context,
        onChunk: InstructriceFactory::createOnChunkDump($output->section()),
    );
}, Perplexity::SONAR_MEDIUM_ONLINE);
