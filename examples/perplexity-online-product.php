<?php

declare(strict_types=1);
use AdrienBrault\Instructrice\Attribute\Prompt;
use AdrienBrault\Instructrice\Instructrice;
use AdrienBrault\Instructrice\InstructriceFactory;
use AdrienBrault\Instructrice\LLM\Provider\Perplexity;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class Product
{
    #[Prompt('The URL to the product page.')]
    public string $url;

    #[Prompt('The name of the product.')]
    public string $name;

    #[Prompt('The price of the product.')]
    public float $price;

    #[Prompt('The currency of the price.')]
    public string $currency;
}

$demo = require __DIR__ . '/bootstrap.php';
$demo(function (Instructrice $instructrice, ?string $context, ConsoleOutputInterface $output) {
    if ($context === null) {
        $context = 'Find meta current VR headset prices.';
        dump('Context: ' . $context);
    }

    $instructrice->list(
        Product::class,
        $context,
        onChunk: InstructriceFactory::createOnChunkDump($output->section()),
    );
}, Perplexity::SONAR_MEDIUM_ONLINE);
