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
php demo/list-models.php <(curl -s https://r.jina.ai/https://openrouter.ai/docs)
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

/*
php demo/list-models.php <(curl -s https://r.jina.ai/https://openrouter.ai/docs)

array:6 [
  0 => Model^ {#260
    +name: "Lynn: Llama 3 Soliloquy 8B"
    +slug: "lynn/soliloquy-l3"
    +context: 24576
    +promptCost: 0.0
    +completionCost: 0.0
  }
  1 => Model^ {#263
    +name: "Nous: Capybara 7B (free)"
    +slug: "nousresearch/nous-capybara-7b:free"
    +context: 4096
    +promptCost: 0.0
    +completionCost: 0.0
  }
  2 => Model^ {#293
    +name: "Mistral 7B Instruct (free)"
    +slug: "mistralai/mistral-7b-instruct:free"
    +context: 32768
    +promptCost: 0.0
    +completionCost: 0.0
  }
  3 => Model^ {#241
    +name: "OpenChat 3.5 (free)"
    +slug: "openchat/openchat-7b:free"
    +context: 8192
    +promptCost: 0.0
    +completionCost: 0.0
  }
  4 => Model^ {#244
    +name: "MythoMist 7B (free)"
    +slug: "gryphe/mythomist-7b:free"
    +context: 32768
    +promptCost: 0.0
    +completionCost: 0.0
  }
  5 => Model^ {#304
    +name: "Toppy M 7B (free)"
    +slug: null
    +context: null
    +promptCost: null
    +completionCost: null
  }
]
*/
