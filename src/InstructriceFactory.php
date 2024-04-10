<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice;

use AdrienBrault\Instructrice\LLM\LLMInterface;
use AdrienBrault\Instructrice\LLM\OllamaFactory;
use Limenius\Liform\Form\Extension\AddLiformExtension;
use Limenius\Liform\Liform;
use Limenius\Liform\LiformInterface;
use Limenius\Liform\Resolver;
use Limenius\Liform\Transformer\ArrayTransformer;
use Limenius\Liform\Transformer\CompoundTransformer;
use Limenius\Liform\Transformer\IntegerTransformer;
use Limenius\Liform\Transformer\StringTransformer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

class InstructriceFactory
{
    /**
     * @param list<FormTypeInterface>|null $formFactoryTypes
     */
    public static function create(
        ?LLMInterface $llm = null,
        ?LoggerInterface $logger = null,
        ?FormFactoryInterface $formFactory = null,
        ?array $formFactoryTypes = null,
        ?LiformInterface $liform = null
    ): Instructrice {
        $logger ??= new NullLogger();
        $llm ??= (new OllamaFactory(logger: $logger))->hermes2pro();

        if ($formFactory === null) {
            $formFactory = self::createFormFactory($formFactoryTypes);
        }

        $liform ??= self::createLiform();

        return new Instructrice(
            $formFactory,
            $liform,
            $llm,
            $logger,
        );
    }

    /**
     * @param list<FormTypeInterface>|null $formFactoryTypes
     */
    public static function createFormFactory(?array $formFactoryTypes = null): FormFactoryInterface
    {
        $formFactoryBuilder = Forms::createFormFactoryBuilder()
            ->addTypeExtension(new AddLiformExtension())
            ->addExtension(new ValidatorExtension(Validation::createValidator()));

        if ($formFactoryTypes !== null) {
            $formFactoryBuilder->addTypes($formFactoryTypes);
        }

        return $formFactoryBuilder->getFormFactory();
    }

    private static function createLiform(?TranslatorInterface $translator = null): Liform
    {
        $translator ??= self::createTranslator();

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
