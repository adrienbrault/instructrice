# Instructrice

A PHP library to make structured data extraction as easy and accessible as possible.

Features and design choices:
- Flexible JSON-Schema options
  - Automatic generation for classes using [api-platform/json-schema][api_platform_json_schema]
  - Automatic generation for [PSL][psl]\\[Type][psl_type]
  - Provide your own schema
- [symfony/serializer][sf_serializer] integration to deserialize LLMs outputs
- Streaming by default. Partial JSON parsing/deserialization.
- A set of pre-configured LLMs with the best settings. Just set your API keys and try the different models. No need to know about json mode, function calling, etc.

## Installation and Usage

```console
$ composer require adrienbrault/instructrice:@dev
```

```php
use AdrienBrault\Instructrice\InstructriceFactory;
use AdrienBrault\Instructrice\LLM\Factory;
use AdrienBrault\Instructrice\Attribute\Instruction;

class Character
{
    #[Instruction('Just the first name.')]
    public string $name;
    
    #[Instruction('If applicable, the military rank.')]
    public ?string $rank = null;
}

$instructrice = InstructriceFactory::create(
    llm: (new Factory\Ollama())->hermes2pro()
);

$characters = $instructrice->deserializeList(
    'Colonel Jack O\'Neil walks into a bar and meets Major Samanta Carter. They call Teal\'c to join them.',
    Character::class
);
```

## Supported providers and models

| Provider                          | Model                                              |
|-----------------------------------|----------------------------------------------------|
| [OpenAI][openai_pricing]          | [gpt-3.5-turbo][openai_gpt35t]                     |
| [OpenAI][openai_pricing]          | [gpt-4-turbo][openai_gpt4t]                        |
| [Ollama][ollama]                  | [adrienbrault/nous-hermes2pro][ollama_h2p]         |
| [Ollama][ollama]                  | [command-r][ollama_command_r]                      |
| [Ollama][ollama]                  | [command-r-plus][ollama_command_r_plus]            |
| [Mistral][mistral_pricing]        | [open-mixtral-8x7b][mistral_models]                |
| [Mistral][mistral_pricing]        | [open-mixtral-8x22b][mistral_models]               |
| [Mistral][mistral_pricing]        | [mistral-large][mistral_models]                    |
| [Fireworks AI][fireworks_pricing] | [firefunction-v1][fireworks_models]                |
| [Fireworks AI][fireworks_pricing] | [mixtral-8x7b-instruct][fireworks_models]          |
| [Fireworks AI][fireworks_pricing] | [mixtral-8x22b-instruct-preview][fireworks_models] |
| [Fireworks AI][fireworks_pricing] | [dbrx-instruct][fireworks_models]                  |
| [Fireworks AI][fireworks_pricing] | [hermes-2-pro-mistral-7b][fireworks_models]        |
| [Groq][groq_pricing]              | [mixtral-8x7b-32768][groq_models]                  |
| [Groq][groq_pricing]              | [gemma-7b-it][groq_models]                         |
| [Together AI][together_pricing]   | [Mixtral-8x7B-Instruct-v0.1][together_models]      |
| [Together AI][together_pricing]   | [Mistral-7B-Instruct-v0.1][together_models]        |
| [Together AI][together_pricing]   | [CodeLlama-34b-Instruct][together_models]          |

## Acknowledgements

Obviously inspired by [instructor-php][instructor-php] and [instructor][instructor-python].

## Notes/Ideas

Things to look into:
- [Unstructured][unstructured_docker]
- [Llama Parse][llama_parse]
- [EMLs][eml]
- [jina-ai/reader][jina_reader]

[DSPy][dspy] is very interesting. There are great ideas to be inspired by.

Ideally this library is good to prototype with, but can support more advanced extraction workflows
with few shot examples, some sort of eval system, generating samples/output like DSPy, etc

Would be cool to have a CLI, that accepts a FQCN and a context.
```
instructrice get "App\Entity\Customer" "$(cat some_email_body.md)" 
```

Autosave all input/schema/output in sqlite db. Like [llm][llm_logging]?
Leverage that to test examples, add few shots, evals?

Use this lib to generate a table of provider/model prices by scraping!

[liform]: https://github.com/Limenius/Liform
[instructor-php]: https://github.com/cognesy/instructor-php/
[instructor-python]: https://python.useinstructor.com
[sf_form]: https://symfony.com/doc/current/components/form.html
[sf_serializer]: https://symfony.com/doc/current/components/serializer.html
[unstructured_docker]: https://unstructured-io.github.io/unstructured/installation/docker.html
[llama_parse]: https://github.com/run-llama/llama_parse
[eml]: https://en.wikipedia.org/wiki/Email#Filename_extensions
[dspy]: https://github.com/stanfordnlp/dspy
[jina_reader]: https://github.com/jina-ai/reader
[psl]: https://github.com/azjezz/psl
[psl_type]: https://github.com/azjezz/psl/blob/next/docs/component/type.md
[api_platform_json_schema]: https://github.com/api-platform/json-schema
[llm_logging]: https://llm.datasette.io/en/stable/logging.html
[openai_pricing]: https://openai.com/pricing
[openai_gpt4t]: https://platform.openai.com/docs/models/gpt-4-turbo-and-gpt-4
[openai_gpt35t]: https://platform.openai.com/docs/models/gpt-3-5-turbo
[ollama]: https://ollama.com
[ollama_h2p]: https://ollama.com/adrienbrault/nous-hermes2pro
[ollama_command_r]: https://ollama.com/library/command-r
[ollama_command_r_plus]: https://ollama.com/library/command-r-plus
[mistral_pricing]: https://mistral.ai/technology/#pricing
[mistral_models]: https://docs.mistral.ai/getting-started/models/
[fireworks_pricing]: https://fireworks.ai/pricing
[fireworks_models]: https://fireworks.ai/models
[groq_pricing]: https://wow.groq.com
[groq_models]: https://console.groq.com/docs/models
[together_pricing]: https://www.together.ai/pricing
[together_models]: https://docs.together.ai/docs/inference-models
