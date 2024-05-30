<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

use AdrienBrault\Instructrice\Http\StreamingClientInterface;
use AdrienBrault\Instructrice\LLM\Client\AnthropicLLM;
use AdrienBrault\Instructrice\LLM\Client\GoogleLLM;
use AdrienBrault\Instructrice\LLM\Client\OpenAiLLM;
use AdrienBrault\Instructrice\LLM\Parser\JsonParser;
use AdrienBrault\Instructrice\LLM\Parser\ParserInterface;
use AdrienBrault\Instructrice\LLM\Provider\Anthropic;
use AdrienBrault\Instructrice\LLM\Provider\Anyscale;
use AdrienBrault\Instructrice\LLM\Provider\DeepInfra;
use AdrienBrault\Instructrice\LLM\Provider\Fireworks;
use AdrienBrault\Instructrice\LLM\Provider\Google;
use AdrienBrault\Instructrice\LLM\Provider\Groq;
use AdrienBrault\Instructrice\LLM\Provider\Mistral;
use AdrienBrault\Instructrice\LLM\Provider\OctoAI;
use AdrienBrault\Instructrice\LLM\Provider\Ollama;
use AdrienBrault\Instructrice\LLM\Provider\OpenAi;
use AdrienBrault\Instructrice\LLM\Provider\Perplexity;
use AdrienBrault\Instructrice\LLM\Provider\ProviderModel;
use AdrienBrault\Instructrice\LLM\Provider\Together;
use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;
use Gioni06\Gpt3Tokenizer\Gpt3TokenizerConfig;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function Psl\Vec\filter;

class LLMFactory
{
    /**
     * @param array<class-string<ProviderModel>, string> $apiKeys
     */
    public function __construct(
        private readonly StreamingClientInterface $client,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly array $apiKeys = [],
        private readonly Gpt3Tokenizer $tokenizer = new Gpt3Tokenizer(new Gpt3TokenizerConfig()),
        private readonly ParserInterface $parser = new JsonParser(),
    ) {
    }

    public function create(LLMConfig|ProviderModel $config): LLMInterface
    {
        if ($config instanceof ProviderModel) {
            $apiKey = $this->apiKeys[$config::class] ?? null;
            $apiKey ??= self::getProviderModelApiKey($config, true) ?? 'sk-xxx';
            $config = $config->createConfig($apiKey);
        }

        if (str_contains($config->uri, 'api.anthropic.com')) {
            return new AnthropicLLM(
                $this->client,
                $this->logger,
                $config,
                $this->parser,
            );
        }

        if (str_contains($config->uri, 'googleapis.com')) {
            return new GoogleLLM(
                $config,
                $this->client,
                $this->logger,
                $this->tokenizer,
                $this->parser,
            );
        }

        return new OpenAiLLM(
            $config,
            $this->client,
            $this->logger,
            $this->tokenizer,
            $this->parser,
        );
    }

    /**
     * @return list<ProviderModel>
     */
    public function getAvailableProviderModels(): array
    {
        $providerModels = [
            ...OpenAi::cases(),
            ...Ollama::cases(),
            ...Anthropic::cases(),
            ...Google::cases(),
            ...Mistral::cases(),
            ...Groq::cases(),
            ...Fireworks::cases(),
            ...Together::cases(),
            ...DeepInfra::cases(),
            ...Perplexity::cases(),
            ...Anyscale::cases(),
            ...OctoAI::cases(),
        ];

        return filter(
            $providerModels,
            self::isAvailable(...)
        );
    }

    public static function isAvailable(ProviderModel $config): bool
    {
        if ($config->getApiKeyEnvVar() === null) {
            return true;
        }

        return self::getProviderModelApiKey($config) !== null;
    }

    private static function getProviderModelApiKey(ProviderModel $providerModel, bool $throwWhenMissing = false): ?string
    {
        $apiKeyEnvVar = $providerModel->getApiKeyEnvVar();

        if ($apiKeyEnvVar === null) {
            return 'sk-xxx';
        }

        $apiKey = getenv($apiKeyEnvVar) ?: null;

        if ($apiKey !== null) {
            return $apiKey;
        }

        if ($throwWhenMissing) {
            throw new InvalidArgumentException(sprintf('Environment variable %s missing', $apiKeyEnvVar));
        }

        return null;
    }
}
