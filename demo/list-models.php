<?php

declare(strict_types=1);
use AdrienBrault\Instructrice\Attribute\Instruction;
use AdrienBrault\Instructrice\Instructrice;
use AdrienBrault\Instructrice\InstructriceFactory;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class Model
{
    #[Instruction('The model name like "GPT 3.5 Turbo". Omit price.')]
    public string $name;

    #[Instruction('The model id for the api, like gpt-3.5-turbo-1234 or meta/llama3-8b')]
    public ?string $slug = null;

    #[Instruction('The context length/window.')]
    public ?int $context = null;

    #[Instruction('$ per million tokens')]
    public ?float $promptCost = null;

    #[Instruction('$ per million tokens')]
    public ?float $completionCost = null;
}

/*
php demo/list-models.php <(curl -s https://r.jina.ai/https://r.jina.ai/openrouter.ai/docs)
php demo/list-models.php <(curl -s https://r.jina.ai/https://deepinfra.com/models/text-generation)
php demo/list-models.php <(curl -s https://r.jina.ai/https://fireworks.ai/models)
php demo/list-models.php <(curl -s https://r.jina.ai/https://docs.mistral.ai/getting-started/models/)
php demo/list-models.php <(curl -s https://r.jina.ai/https://docs.together.ai/docs/inference-models)
*/

$demo = require __DIR__ . '/bootstrap.php';
$demo(function (Instructrice $instructrice, ?string $context, ConsoleOutputInterface $output) {
    $instructrice->getList(
        Model::class,
        $context,
        'Extract information present in the context about ALL the language models. Omit values you arent sure about.',
        [
            'truncate_automatically' => true,
            'all_required' => true,
        ],
        onChunk: InstructriceFactory::createOnChunkDump($output->section(), false),
    );
});
