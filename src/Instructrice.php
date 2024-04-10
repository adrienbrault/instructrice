<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice;

use AdrienBrault\Instructrice\LLM\LLMInterface;
use Limenius\Liform\Form\Extension\AddLiformExtension;
use Limenius\Liform\LiformInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;
use function Psl\Regex\replace;
use function Psl\Vec\filter_nulls;
use function Psl\Vec\map;

class Instructrice
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly LiformInterface $liform,
        private readonly LLMInterface $llm,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param callable(FormFactoryInterface): FormInterface $newForm
     * @param FormInterface|null $form A previously submitted forms with validation errors
     */
    public function fillForm(
        string $context,
        callable $newForm,
        int $retries = 0,
        ?FormInterface $form = null
    ): FormInterface {
        $form ??= $newForm($this->formFactory);

        $this->assertLiformExtensionRegistered($form);

        $formattedErrors = $this->formatErrors($form);

        $data = $this->llm->get(
            $this->liform->transform($form),
            $context,
            $formattedErrors,
            $form->getData()
        );

        if (! is_array($data) && $data !== null && ! is_string($data)) {
            $this->logger->warning('LLM returned invalid data', [
                'data' => $data,
            ]);
            $data = [];
        }
        $form = $newForm($this->formFactory);
        $form->submit($data);

        $this->logger->debug('Form state post submission', [
            'isvalid' => $form->isValid(),
            'errors' => ! $form->isValid() ? $formattedErrors : [],
        ]);

        if ($retries === 0
            || $form->isValid()
        ) {
            return $form;
        }

        // Retry, feeding back the errors
        return $this->fillForm(
            context: $context,
            newForm: $newForm,
            retries: $retries - 1,
            form: $form,
        );
    }

    /**
     * @param callable(FormBuilderInterface): FormInterface $newEntryForm
     * @param FormInterface|null $form A previously submitted forms with validation errors
     */
    public function fillCollection(
        string $context,
        callable $newEntryForm,
        int $retries = 0,
        ?FormInterface $form = null
    ): FormInterface {
        $entryType = new class() extends AbstractType {
            /**
             * @var callable(FormBuilderInterface): FormInterface
             */
            public static $newEntryForm = null;

            public function buildForm(FormBuilderInterface $builder, array $options): void
            {
                call_user_func(static::$newEntryForm, $builder);
            }
        };
        $entryType::$newEntryForm = $newEntryForm;

        $entryTypeName = get_class($entryType);

        return $this->fillForm(
            $context,
            fn (FormFactoryInterface $ff) => $ff->createBuilder()
                ->add('list', CollectionType::class, [
                    'entry_type' => $entryTypeName,
                    'allow_add' => true,
                ])
                ->getForm(),
            $retries,
            $form
        )->get('list');
    }

    /**
     * @return list<array{message: string, path: string}>
     */
    public function formatErrors(FormInterface $form): array
    {
        return filter_nulls(
            map(
                $form->getErrors(true),
                function (FormError|FormErrorIterator $error) {
                    if ($error instanceof FormErrorIterator) {
                        return null; // ignore for now
                    }

                    $cause = $error->getCause();
                    assert($cause instanceof ConstraintViolationInterface);

                    $cleanPropertyPath = replace(
                        $cause->getPropertyPath(),
                        '#([.]?children|[.]data$)#',
                        ''
                    );
                    $cleanPropertyPath = replace(
                        $cleanPropertyPath,
                        '#\[([a-z]\w*)\]#i',
                        '.$1'
                    );

                    return [
                        'message' => $error->getMessage(),
                        'path' => $cleanPropertyPath,
                    ];
                }
            )
        );
    }

    private function assertLiformExtensionRegistered(FormInterface $form): void
    {
        $typeExtensions = $form->getConfig()->getType()->getTypeExtensions();

        foreach ($typeExtensions as $typeExtension) {
            if ($typeExtension instanceof AddLiformExtension) {
                return;
            }
        }

        throw new \InvalidArgumentException(
            'The form must have the Liform extension registered.'
        );
    }
}
