<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice;

use Limenius\Liform\Liform;
use Limenius\Liform\Resolver;
use Limenius\Liform\Transformer\ArrayTransformer;
use Limenius\Liform\Transformer\CompoundTransformer;
use Limenius\Liform\Transformer\IntegerTransformer;
use Limenius\Liform\Transformer\StringTransformer;
use OpenAI;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

class InstructriceFactory
{
    public static function create(
        ?OpenAI\Client $openAiClient = null,
        ?LoggerInterface $logger = null
    ): Instructrice {
        if ($openAiClient === null) {
            $openAiClient = OpenAI::factory()
                ->withBaseUri(getenv('OLLAMA_HOST') . '/v1')
                ->make();
        }

        $translator = self::createTranslator();
        $liform = self::createLiform($translator);

        return new Instructrice(
            $liform,
            $openAiClient,
            $logger ?? new NullLogger()
        );
    }

    private static function createLiform(TranslatorInterface $translator): Liform
    {
        $resolver = new Resolver();
        $stringTransformer = new StringTransformer($translator);
        $integerTransformer = new IntegerTransformer($translator);
        $resolver->setTransformer('text', $stringTransformer);
        $resolver->setTransformer('textarea', $stringTransformer, 'textarea');
        $resolver->setTransformer('integer', $integerTransformer);
        $resolver->setTransformer('compound', new CompoundTransformer($translator, null, $resolver));
        $resolver->setTransformer('collection', new ArrayTransformer($translator, null, $resolver));

        return new Liform($resolver);
    }

    private static function createTranslator(): Translator
    {
        return new Translator('en_US');
    }
}
