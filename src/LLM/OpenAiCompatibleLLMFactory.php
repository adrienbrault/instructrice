<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

use AdrienBrault\Instructrice\LLM\Config\Deepinfra;
use AdrienBrault\Instructrice\LLM\Config\Fireworks;
use AdrienBrault\Instructrice\LLM\Config\Groq;
use AdrienBrault\Instructrice\LLM\Config\LLMConfig;
use AdrienBrault\Instructrice\LLM\Config\Mistral;
use AdrienBrault\Instructrice\LLM\Config\Ollama;
use AdrienBrault\Instructrice\LLM\Config\OpenAi;
use AdrienBrault\Instructrice\LLM\Config\ProviderEnumInterface;
use AdrienBrault\Instructrice\LLM\Config\Together;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function Psl\Vec\filter_nulls;
use function Psl\Vec\map;

class OpenAiCompatibleLLMFactory
{
    public function __construct(
        private readonly ClientInterface $guzzleClient = new Client(),
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * @return list<LLMConfig>
     */
    public function createAvailable(): array
    {
        $unitEnums = [
            ...OpenAi::cases(),
            ...Ollama::cases(),
            ...Mistral::cases(),
            ...Groq::cases(),
            ...Fireworks::cases(),
            ...Together::cases(),
            ...Deepinfra::cases(),
        ];

        return filter_nulls(
            map(
                $unitEnums,
                fn (ProviderEnumInterface $unitEnum): ?LLMConfig => $unitEnum->createConfig()
            )
        );
    }

    public function create(LLMConfig $config): LLMInterface
    {
        return new OpenAiCompatibleLLM(
            $this->guzzleClient,
            $this->logger,
            $config
        );
    }
}
