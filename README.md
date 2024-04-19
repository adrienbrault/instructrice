# Instructrice

A PHP library to make structured data extraction as easy and accessible as possible.

Features and design choices:
- Flexible JSON-Schema options
  - Automatic generation for classes using [api-platform/json-schema][api_platform_json_schema]
  - Automatic generation for [PSL][psl]\\[Type][psl_type]
  - Provide your own schema
- [symfony/serializer][sf_serializer] integration to deserialize LLMs outputs
- Streaming first/only. Partial JSON parsing/deserialization.
- A set of pre-configured LLMs with the best settings. Just set your API keys and try the different models. No need to know about json mode, function calling, etc.

## Installation and Usage

```console
$ composer require adrienbrault/instructrice:@dev
```

```php
use AdrienBrault\Instructrice\InstructriceFactory;
use AdrienBrault\Instructrice\LLM\ProviderModel\Ollama;

$instructrice = InstructriceFactory::create(
    llm: Ollama::HERMES2PRO
);
```

### List of object

```php
use AdrienBrault\Instructrice\Attribute\Instruction;

class Character
{
    #[Instruction('Just the first name.')]
    public string $name;
    
    #[Instruction('If applicable, the military rank.')]
    public ?string $rank = null;
}

$characters = $instructrice->getList(
    Character::class,
    'Colonel Jack O\'Neil walks into a bar and meets Major Samanta Carter. They call Teal\'c to join them.',
);

assert(count($characters) === 3);
assert($character[0] instanceof Character);
```

### Object

```php
$character = $instructrice->get(
    Character::class,
    'Colonel Jack O\'Neil.',
);

assert($character instanceof Character);
```

### Dynamic Schema

```php
$label = $instructrice->get(
    [
        'type' => 'string',
        'enum' => ['positive', 'neutral', 'negative'],
    ],
    'Amazing great cool nice',
    'Sentiment analysis',
);

assert($label === 'positive');
```

https://github.com/adrienbrault/instructrice/assets/611271/da69281d-ac56-4135-b2ef-c5e306a56de2

## Supported providers

| Provider                          | API Key Environment Variable       | ProviderModel                                    | API Key Creation URL                          |
|-----------------------------------|------------------------------------|--------------------------------------------------|-----------------------------------------------|
| [Ollama][ollama]                  | Default. You can set `OLLAMA_HOST` | [Ollama](src/LLM/ProviderModel/Ollama.php)       |                                               |
| [OpenAI][openai_pricing]          | `OPENAI_API_KEY`                   | [OpenAi](src/LLM/ProviderModel/OpenAi.php)       | [API Key Management][openai_apikey_create]    |
| [Anthropic][anthropic_pricing]    | `ANTHROPIC_API_KEY`                | [Anthropic](src/LLM/ProviderModel/Anthropic.php) | [API Key Management][anthropic_apikey_create] |
| [Mistral][mistral_pricing]        | `MISTRAL_API_KEY`                  | [Mistral](src/LLM/ProviderModel/Mistral.php)     | [API Key Management][mistral_apikey_create]   |
| [Fireworks AI][fireworks_pricing] | `FIREWORKS_API_KEY`                | [Fireworks](src/LLM/ProviderModel/Fireworks.php) | [API Key Management][fireworks_apikey_create] |
| [Groq][groq_pricing]              | `GROQ_API_KEY`                     | [Groq](src/LLM/ProviderModel/Groq.php)           | [API Key Management][groq_apikey_create]      |
| [Together AI][together_pricing]   | `TOGETHER_API_KEY`                 | [Together](src/LLM/ProviderModel/Together.php)   | [API Key Management][together_apikey_create]  |
| [Deepinfra][deepinfra_pricing]    | `DEEPINFRA_API_KEY`                | [Deepinfra](src/LLM/ProviderModel/Deepinfra.php) | [API Key Management][deepinfra_apikey_create] |

You can find the list of supported models within each ProviderModel.

## Supported models

### Open Weights

| Model            | License                          | Ollama | Mistral | Fireworks | Groq | Together | Deepinfra |
|------------------|----------------------------------|--------|---------|-----------|------|----------|-----------|
| Mistral 7B       | [Apache 2.0][apache2]            |        | X       |           |      | X        |           |
| Mixtral 8x7B     | [Apache 2.0][apache2]            |        | X       |           | X    | X        |           |
| Mixtral 8x22B    | [Apache 2.0][apache2]            |        | X       |           |      | X        | X         |
| WizardLM 2 7B    | [Apache 2.0][apache2]            |        |         |           |      |          | X         |
| WizardLM 2 8x22B | [Apache 2.0][apache2]            |        |         |           |      | X        | X         |
| Hermes 2 Pro     | [Apache 2.0][apache2]            | X      |         | X         |      |          |           |
| FireFunction V1  | [Apache 2.0][apache2]            |        |         | X         |      |          |           |
| Llama3 8B        | [Llama 3][llama3_license]        |        |         | X         | X    | X        | X         |
| Llama3 70B       | [Llama 3][llama3_license]        |        |         | X         | X    | X        | X         |
| Gemma 7B         | Gemma                            |        |         |           | X    |          |           |
| DBRX             | [Databricks OML][databricks_oml] |        |         | X         |      | X        | X         |
| Command R        | [CC-BY-NC][cc_nc]                | X      |         |           |      |          |           |
| Command R+       | [CC-BY-NC][cc_nc]                | X      |         |           |      |          |           |

### Proprietary

| Model           | OpenAI | Anthropic | Mistral |
|-----------------|--------|-----------|---------|
| Mixtral Large   |        |           | X       |
| GPT-4 Turbo     | X      |           |         |
| GPT-3.5 Turbo   | X      |           |         |
| Claude 3 Haiku  |        | X         |         |
| Claude 3 Sonnet |        | X         |         |
| Claude 3 Opus   |        | X         |         |

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
[deepinfra_pricing]: https://deepinfra.com/pricing
[deepinfra_mixtral]: https://deepinfra.com/mistralai/Mixtral-8x22B-Instruct-v0.1
[deepinfra_wizardlm2_22]: https://deepinfra.com/microsoft/WizardLM-2-8x22B
[deepinfra_wizardlm2_7]: https://deepinfra.com/microsoft/WizardLM-2-8x7B
[deepinfra_dbrx]: https://deepinfra.com/databricks/dbrx-instruct
[openai_apikey_create]: https://platform.openai.com/api-keys
[anthropic_apikey_create]: https://console.anthropic.com/settings/keys
[mistral_apikey_create]: https://console.mistral.ai/api-keys/
[fireworks_apikey_create]: https://fireworks.ai/api-keys
[groq_apikey_create]: https://console.groq.com/keys
[together_apikey_create]: https://api.together.xyz/settings/api-keys
[deepinfra_apikey_create]: https://deepinfra.com/dash/api_keys
[databricks_oml]: https://www.databricks.com/legal/open-model-license
[llama3_license]: https://github.com/meta-llama/llama3/blob/main/LICENSE
[apache2]: https://www.apache.org/licenses/LICENSE-2.0
[cc_nc]: https://en.wikipedia.org/wiki/Creative_Commons_NonCommercial_license
