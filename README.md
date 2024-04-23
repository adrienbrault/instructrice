# Instructrice

Structured Data Extraction in PHP.

Features and design choices:
- Flexible JSON-Schema options
  - Automatic generation for classes using [api-platform/json-schema][api_platform_json_schema]
  - Automatic generation for [PSL][psl]\\[Type][psl_type]
  - Provide your own schema
- [symfony/serializer][sf_serializer] integration to deserialize LLMs outputs
- Streaming first/only.
- Correctly parses/deserializes incomplete JSON during streaming.
- A set of pre-configured LLMs with the best settings.
  - Just set your API keys and try the different models.
  - No need to think about the model name, json mode, function calling, etc.

## Installation and Usage

```console
$ composer require adrienbrault/instructrice:@dev
```

```php
use AdrienBrault\Instructrice\InstructriceFactory;
use AdrienBrault\Instructrice\LLM\Provider\Ollama;

$instructrice = InstructriceFactory::create(
    Ollama::HERMES2PRO,
    apiKeys: [ // Unless you inject keys here, api keys will be fetched from environment variables
        OpenAi::class => $openAiApiKey,
        Anthropic::class => $anthropicApiKey,
    ],
);
```

### List of object

```php
use AdrienBrault\Instructrice\Attribute\Instruction;

class Character
{
    #[Instruction('Just the first name.')]
    public string $name;
    public ?string $rank = null;
}

$characters = $instructrice->getList(
    Character::class,
    'Colonel Jack O\'Neil walks into a bar and meets Major Samanta Carter. They call Teal\'c to join them.',
);

array:3 [
  0 => Character^ {#225
    +name: "Jack"
    +rank: "Colonel"
  }
  1 => Character^ {#298
    +name: "Samanta"
    +rank: "Major"
  }
  2 => Character^ {#296
    +name: "Teal'c"
    +rank: null
  }
]
```

### Object

```php
$character = $instructrice->get(
    type: Character::class,
    context: 'Colonel Jack O\'Neil.',
);

Character^ {#294
  +name: "Jack"
  +rank: "Colonel"
}
```

### Dynamic Schema

```php
$label = $instructrice->get(
    type: [
        'type' => 'string',
        'enum' => ['positive', 'neutral', 'negative'],
    ],
    context: 'Amazing great cool nice',
    instructions: 'Sentiment analysis',
);

"positive"
```

You can also use third party json schema libraries like [goldspecdigital/oooas][oooas] to generate the schema:
- [demo/oooas.php](demo/oooas.php)

https://github.com/adrienbrault/instructrice/assets/611271/da69281d-ac56-4135-b2ef-c5e306a56de2

## Supported providers

| Provider                          | Environment Variables | Enum                                          | API Key Creation URL                           |
|-----------------------------------|-----------------------|-----------------------------------------------|------------------------------------------------|
| [Ollama][ollama]                  | `OLLAMA_HOST`         | [Ollama](src/LLM/Provider/Ollama.php)         |                                                |
| [OpenAI][openai_pricing]          | `OPENAI_API_KEY`      | [OpenAi](src/LLM/Provider/OpenAi.php)         | [API Key Management][openai_apikey_create]     |
| [Anthropic][anthropic_pricing]    | `ANTHROPIC_API_KEY`   | [Anthropic](src/LLM/Provider/Anthropic.php)   | [API Key Management][anthropic_apikey_create]  |
| [Mistral][mistral_pricing]        | `MISTRAL_API_KEY`     | [Mistral](src/LLM/Provider/Mistral.php)       | [API Key Management][mistral_apikey_create]    |
| [Fireworks AI][fireworks_pricing] | `FIREWORKS_API_KEY`   | [Fireworks](src/LLM/Provider/Fireworks.php)   | [API Key Management][fireworks_apikey_create]  |
| [Groq][groq_pricing]              | `GROQ_API_KEY`        | [Groq](src/LLM/Provider/Groq.php)             | [API Key Management][groq_apikey_create]       |
| [Together AI][together_pricing]   | `TOGETHER_API_KEY`    | [Together](src/LLM/Provider/Together.php)     | [API Key Management][together_apikey_create]   |
| [Deepinfra][deepinfra_pricing]    | `DEEPINFRA_API_KEY`   | [Deepinfra](src/LLM/Provider/Deepinfra.php)   | [API Key Management][deepinfra_apikey_create]  |
| [Perplexity][perplexity_pricing]  | `PERPLEXITY_API_KEY`  | [Perplexity](src/LLM/Provider/Perplexity.php) | [API Key Management][perplexity_apikey_create] |
| [Anyscale][anyscale_pricing]      | `ANYSCALE_API_KEY`    | [Anyscale](src/LLM/Provider/Anyscale.php)   | [API Key Management][anyscale_apikey_create]   |

The supported providers are Enums, which you can pass to the `llm` argument of `InstructriceFactory::create`:

```php
use AdrienBrault\Instructrice\InstructriceFactory;
use AdrienBrault\Instructrice\LLM\Provider\OpenAi;

$instructrice->get(
    ...,
    llm: OpenAi::GPT_4T, // API Key will be fetched from the environment variable
);
```

You can also use any OpenAI compatible api by passing an [LLMConfig](src/LLM/LLMConfig.php):

```php
use AdrienBrault\Instructrice\InstructriceFactory;
use AdrienBrault\Instructrice\LLM\LLMConfig;
use AdrienBrault\Instructrice\LLM\Cost;
use AdrienBrault\Instructrice\LLM\OpenAiLLM;
use AdrienBrault\Instructrice\LLM\OpenAiJsonStrategy;
use AdrienBrault\Instructrice\LLM\Provider\ProviderModel;
use AdrienBrault\Instructrice\Http\GuzzleStreamingClient;
use GuzzleHttp\Client;

$instructrice->get(
    ...,
    llm: new LLMConfig(
        uri: 'https://api.together.xyz/v1/chat/completions',
        model: 'meta-llama/Llama-3-70b-chat-hf',
        contextWindow: 8000,
        label: 'Llama 3 70B',
        provider: 'Together',
        cost: Cost::create(0.9),
        strategy: OpenAiJsonStrategy::JSON,
        headers: [
            'Authorization' => 'Bearer ' . $apiKey,
        ]
    ),
);
```

You may also implement [LLMInterface](src/LLM/LLMInterface.php).

## Supported models

Legend:
- 📄 Text
- 🧩 JSON
- 🚀 Function
- 💼 Commercial usage
  - ✅ Yes
  - ⚠️ Yes, but
  - ❌ Nope

### Open Weights

#### Foundation

|                          | 💼                   | ctx  | [Ollama][o_m] | [Mistral][m_m] | [Fireworks][f_m] | [Groq][g_m] | [Together][t_m] | [Deepinfra][d_m] | [Perplexity][p_m]  | Anyscale |
|--------------------------|----------------------|------|---------------|----------------|------------------|-------------|-----------------|------------------|--------------------|----------|
| [Mistral 7B][hf_m7b]     | [✅][apache2]         | 32k  |               | 🧩 68/s        |                  |             | 📄 98/s         |                  | 📄 88/s !ctx=16k!  | 🧩       |
| [Mixtral 8x7B][hf_mx7]   | [✅][apache2]         | 32k  |               | 🧩 44/s        | 🧩 237/s         | 📄 560/s    | 🚀 99/s         |                  | 📄 119/s !ctx=16k! | 🧩       |
| [Mixtral 8x22B][hf_mx22] | [✅][apache2]         | 65k  |               | 🧩 77/s        | 🧩 77/s          |             | 📄 52/s         | 🧩 40/s          | 📄 62/s !ctx=16k!  | 🧩       |
| [Llama3 8B][hf_l3_8]     | [⚠️][llama3_license] | 8k   | 📄            |                | 🧩 280/s         | 📄 270/s    | 📄 194/s        | 🧩 133/s         | 📄 121/s           | 🧩       |
| [Llama3 70B][hf_l3_70]   | [⚠️][llama3_license] | 8k   | 🧩            |                | 🧩 116/s         | 📄 800/s    | 📄 105/s        | 🧩 26/s          | 📄 42/s            | 🧩       |
| [Gemma 7B][hf_g7]        | ⚠️                   | 8k   |               |                |                  | 📄 800/s    | 📄 118/s        | 🧩 64/s          |                    | 🧩       |
| [DBRX][hf_dbrx]          | [⚠️][databricks_oml] | 32k  |               |                | 🧩 50/s          |             | 📄 72/s         | 🧩               |                    |          |
| [Command R][hf_cr]       | [❌][cc_nc]           | 128k | 📄            |                |                  |             |                 |                  |                    |          |
| [Command R+][hf_crp]     | [❌][cc_nc]           | 128k | 📄            |                |                  |             |                 |                  |                    |          |

Throughputs from https://artificialanalysis.ai/leaderboards/providers .

#### Fine Tune

|                          | 💼           | ctx  | Parent       | [Ollama][o_m] | [Fireworks][f_m] | [Together][t_m] | [Deepinfra][d_m] |
|--------------------------|--------------|------|--------------|---------------|------------------|-----------------|------------------|
| [Hermes 2 Pro][hf_h2p]   | [✅][apache2] |      | Mistral 7B   | 🧩            | 🧩               |                 |                  |
| [FireFunction V1][hf_ff] | [✅][apache2] |      | Mixtral 8x7B |               | 🚀               |                 |                  |
| WizardLM 2 7B            | [✅][apache2] |      | Mistral 7B   |               |                  |                 | 🧩               |
| WizardLM 2 8x22B         | [✅][apache2] |      | Mixtral 8x7B |               |                  | 📄              | 🧩               |
| [Capybara 34B][hf_capy]  | [✅][apache2] | 200k | Yi 34B       |               | 🧩               |                 |                  |

### Proprietary

| Model               | ctx  |         | 
|---------------------|------|---------|
| Mistral Large       | 32k  | ✅ 26/s  | 
| GPT-4 Turbo         | 128k | 🚀 24/s |  
| GPT-3.5 Turbo       | 16k  | 🚀 72/s |  
| Claude 3 Haiku      | 200k | 🆗 88/s |  
| Claude 3 Sonnet     | 200k | 🆗 59/s |  
| Claude 3 Opus       | 200k | 🆗 26/s |  
| Sonar Small Chat    | 16k  | 📄      |  
| Sonar Small Online  | 12k  | 📄      |  
| Sonar Medium Chat   | 16k  | 📄      |  
| Sonar Medium Online | 12k  | 📄      |  

Throughputs from https://artificialanalysis.ai/leaderboards/providers .

Automate updating these tables by scraping artificialanalysis.ai , along with chatboard arena elo.?
Would be a good use case / showcase of this library/cli? 

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
[openai_apikey_create]: https://platform.openai.com/api-keys
[ollama]: https://ollama.com
[ollama_h2p]: https://ollama.com/adrienbrault/nous-hermes2pro
[ollama_command_r]: https://ollama.com/library/command-r
[ollama_command_r_plus]: https://ollama.com/library/command-r-plus
[o_m]: https://ollama.com/library
[mistral_pricing]: https://mistral.ai/technology/#pricing
[m_m]: https://docs.mistral.ai/getting-started/models/
[mistral_apikey_create]: https://console.mistral.ai/api-keys/
[fireworks_pricing]: https://fireworks.ai/pricing
[f_m]: https://fireworks.ai/models
[fireworks_apikey_create]: https://fireworks.ai/api-keys
[groq_pricing]: https://wow.groq.com
[g_m]: https://console.groq.com/docs/models
[groq_apikey_create]: https://console.groq.com/keys
[together_pricing]: https://www.together.ai/pricing
[t_m]: https://docs.together.ai/docs/inference-models
[together_apikey_create]: https://api.together.xyz/settings/api-keys
[oooas]: https://github.com/goldspecdigital/oooas
[anthropic_pricing]: https://www.anthropic.com/api
[anthropic_m]: https://docs.anthropic.com/claude/docs/models-overview
[anthropic_apikey_create]: https://console.anthropic.com/settings/keys
[deepinfra_pricing]: https://deepinfra.com/pricing
[d_mixtral]: https://deepinfra.com/mistralai/Mixtral-8x22B-Instruct-v0.1
[d_m]: https://deepinfra.com/models/text-generation
[deepinfra_wizardlm2_22]: https://deepinfra.com/microsoft/WizardLM-2-8x22B
[deepinfra_wizardlm2_7]: https://deepinfra.com/microsoft/WizardLM-2-8x7B
[deepinfra_dbrx]: https://deepinfra.com/databricks/dbrx-instruct
[perplexity_pricing]: https://docs.perplexity.ai/docs/pricing
[p_m]: https://docs.perplexity.ai/docs/model-cards
[perplexity_apikey_create]: https://www.perplexity.ai/settings/api
[anyscale_pricing]: https://docs.endpoints.anyscale.com/pricing/
[anyscale_apikey_create]: https://app.endpoints.anyscale.com/credentials
[deepinfra_apikey_create]: https://deepinfra.com/dash/api_keys
[databricks_oml]: https://www.databricks.com/legal/open-model-license
[llama3_license]: https://github.com/meta-llama/llama3/blob/main/LICENSE
[apache2]: https://www.apache.org/licenses/LICENSE-2.0
[cc_nc]: https://en.wikipedia.org/wiki/Creative_Commons_NonCommercial_license
[hf_m7b]: https://huggingface.co/mistralai/Mistral-7B-Instruct-v0.2
[hf_h2p]: https://huggingface.co/NousResearch/Hermes-2-Pro-Mistral-7B
[hf_ff]: https://huggingface.co/fireworks-ai/firefunction-v1
[hf_mx22]: https://huggingface.co/mistralai/Mixtral-8x22B-Instruct-v0.1
[hf_mx7]: https://huggingface.co/mistralai/Mixtral-8x7B-Instruct-v0.1
[hf_l3_8]: https://huggingface.co/meta-llama/Meta-Llama-3-8B-Instruct
[hf_l3_70]: https://huggingface.co/meta-llama/Meta-Llama-3-70B-Instruct
[hf_g7]: https://huggingface.co/google/gemma-7b-it
[hf_dbrx]: https://huggingface.co/databricks/dbrx-instruct
[hf_crp]: https://huggingface.co/CohereForAI/c4ai-command-r-plus
[hf_cr]: https://huggingface.co/CohereForAI/c4ai-command-r
[hf_capy]: https://huggingface.co/NousResearch/Nous-Capybara-34B
