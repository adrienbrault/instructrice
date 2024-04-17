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
| [Anthropic][anthropic_pricing]    | [Haiku][anthropic_models]                          |
| [Anthropic][anthropic_pricing]    | [Sonnet][anthropic_models]                         |
| [Anthropic][anthropic_pricing]    | [Opus][anthropic_models]                           |
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

> How is it different from instructor php?

Both libraries essentially do the same thing:
- Automatic schema generation from classes
- Multiple LLM/Providers abstraction/support
- Many strategies to extract data: function calling, json mode, etc
- Automatic deserialization/hydration
- Maybe validation/retries later for this lib.

However, I created yet another library because I wanted to do a lot of things differently:
- Make it Symfony/Adrien friendly/ready/first - instructor-php appears more for laravel. Navigating and understanding the instructor-php codebase hasn't been easy for me as I am not familiar with a lot of the patterns. I want dependency injection for everything, composition over inheritance/traits. I am not a fan of Saloon.
- PSR-3 logging and Guzzle+symfony/http-client support so that I can easily get all the debugging information in the symfony profiler.
- Streaming first/only. I think both UX and DX are quite a bit worse without streaming. Not supporting non-streaming to simplify things a bit.
- Preconfigured provider+llms, to not have to worry about:
  - Json mode, function calling, etc
  - The best prompt format to use
  - How to parse the response (cf ollama + hermes2pro)
  - Whether streaming works. For example, groq can only do streaming without json-mode/function calling.
- No messages. You just pass context, instruction.
  - That choice might be more helpful later: trying to support few-shots examples, evals, etc
- Easily be able to use my own json-schema as a plain array, or generated with a library like [goldspecdigital/oooas][oooas]. Use case in this video where a user is able to define the schema: <a target="_blank" href="https://github.com/adrienbrault/carotte/assets/611271/02d37186-f1e6-43bf-b7c0-5785d29779d5">video</a> 
- instructor-php uses docblocks comments as instructions. I prefer using a dedicated attribute, which I have tried here.

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
[oooas]: https://github.com/goldspecdigital/oooas
[anthropic_pricing]: https://www.anthropic.com/api
[anthropic_models]: https://docs.anthropic.com/claude/docs/models-overview
