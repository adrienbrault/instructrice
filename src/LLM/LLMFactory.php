<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

use AdrienBrault\Instructrice\Http\StreamingClientInterface;
use AdrienBrault\Instructrice\LLM\Parser\JsonParser;
use AdrienBrault\Instructrice\LLM\Parser\ParserInterface;
use AdrienBrault\Instructrice\LLM\ProviderModel\Anthropic;
use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;
use Gioni06\Gpt3Tokenizer\Gpt3TokenizerConfig;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class LLMFactory
{
    public function __construct(
        private readonly StreamingClientInterface $client,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly Gpt3Tokenizer $tokenizer = new Gpt3Tokenizer(new Gpt3TokenizerConfig()),
        private readonly ParserInterface $parser = new JsonParser(),
    ) {
    }

    public function create(LLMConfig $config): LLMInterface
    {
        if ($config->providerModel instanceof Anthropic) {
            return new AnthropicLLM(
                $this->client,
                $this->logger,
                $config,
                $this->parser,
            );
        }

        return new OpenAiCompatibleLLM(
            $this->client,
            $this->logger,
            $config,
            $this->tokenizer,
            $this->parser,
        );
    }
}
