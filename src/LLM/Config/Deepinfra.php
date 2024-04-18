<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Config;

use AdrienBrault\Instructrice\LLM\OpenAiJsonStrategy;
use GuzzleHttp\RequestOptions;

enum Deepinfra: string implements ProviderEnumInterface
{
    case MIXTRAL_22 = 'mistralai/Mixtral-8x22B-Instruct-v0.1';
    case WIZARDLM2_22 = 'microsoft/WizardLM-2-8x22B';
    case WIZARDLM2_7 = 'microsoft/WizardLM-2-7B';
    case DBRX = 'databricks/dbrx-instruct';

    public function createConfig(): ?LLMConfig
    {
        $config = match ($this) {
            self::MIXTRAL_22 => [
                'contextWindow' => 64000,
                'label' => 'Mixtral 8x22B',
                'ppm' => 0.65,
            ],
            self::WIZARDLM2_22 => [
                'contextWindow' => 64000,
                'label' => 'WizardLM 2 8x22B',
                'ppm' => 0.65,
            ],
            self::WIZARDLM2_7 => [
                'contextWindow' => 32000,
                'label' => 'WizardLM 2 7B',
                'ppm' => 0.1,
            ],
            self::DBRX => [
                'contextWindow' => 32000,
                'label' => 'DBRX',
                'ppm' => 0.6,
            ],
        };

        $apiKey = getenv('DEEPINFRA_API_KEY');

        if (! \is_string($apiKey) || $apiKey === '') {
            return null;
        }

        return new LLMConfig(
            'https://api.deepinfra.com/v1/openai/chat/completions',
            $this->value,
            'Deepinfra - ' . $config['label'],
            'https://deepinfra.com/' . $this->value,
            $config['contextWindow'],
            null,
            OpenAiJsonStrategy::JSON,
            null,
            Cost::create($config['ppm']),
            [
                RequestOptions::HEADERS => [
                    'Authorization' => 'Bearer ' . $apiKey,
                ],
            ]
        );
    }
}
