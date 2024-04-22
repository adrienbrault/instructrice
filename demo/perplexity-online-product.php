<?php

declare(strict_types=1);
use AdrienBrault\Instructrice\Attribute\Instruction;
use AdrienBrault\Instructrice\Instructrice;
use AdrienBrault\Instructrice\InstructriceFactory;
use AdrienBrault\Instructrice\LLM\ProviderModel\Perplexity;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class Product
{
    #[Instruction('The URL to the product page.')]
    public string $url;

    #[Instruction('The name of the product.')]
    public string $name;

    #[Instruction('The price of the product.')]
    public float $price;

    #[Instruction('The currency of the price.')]
    public string $currency;
}

$demo = require __DIR__ . '/bootstrap.php';
$demo(function (Instructrice $instructrice, ?string $context, ConsoleOutputInterface $output) {
    if ($context === null) {
        $context = 'Find meta current VR headset prices.';
        dump('Context: ' . $context);
    }

    $instructrice->getList(
        Product::class,
        $context,
        onChunk: InstructriceFactory::createOnChunkDump($output->section()),
    );
}, Perplexity::SONAR_MEDIUM_ONLINE);
