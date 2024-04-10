<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use AdrienBrault\Instructrice\InstructriceFactory;
use Limenius\Liform\Form\Extension\AddLiformExtension;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Validation;

class PeopleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('biography', TextareaType::class, [
                'liform' => [
                    'description' => 'Succintly describe the person\'s life.',
                ],
                'constraints' => [
                    new Length([
                        'min' => 75,
                    ]),
                    new Regex(
                        '/ (et|de|pour|est|connu) /i',
                        message: 'The sentences must be written in french, not english.'
                    ),
                    new Regex(
                        '/DAMN/',
                        message: 'You must include "DAMN".',
                    ),
                ],
            ])
        ;
    }
}

$instructrice = InstructriceFactory::create(logger: createConsoleLogger());

$form = $instructrice->fillForm(
    context: 'Jason fried, david cramer',
    newForm: fn () => getArrayForm(new PeopleType()),
    retries: 3
);

dump('final result', $form->getData());

/**
 * @param list<FormTypeInterface> $types
 */
function createFormFactory(array $types): FormFactoryInterface
{
    return Forms::createFormFactoryBuilder()
        ->addTypeExtension(new AddLiformExtension())
        ->addExtension(new ValidatorExtension(Validation::createValidator()))
        ->addTypes($types)
        ->getFormFactory();
}

function getArrayForm(AbstractType $peopleType): FormInterface
{
    $formFactory = createFormFactory([
        $peopleType,
    ]);

    return $formFactory
        ->createBuilder()
        ->add('people', CollectionType::class, [
            'entry_type' => get_class($peopleType),
            'allow_add' => true,
        ])
        ->getForm();
}

function createConsoleLogger(): LoggerInterface
{
    return new Logger('instructrice', [
        new ConsoleHandler(new ConsoleOutput(ConsoleOutput::VERBOSITY_DEBUG)),
    ]);
}
