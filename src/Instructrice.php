<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice;

use AdrienBrault\Instructrice\LLM\LLMInterface;
use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;
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
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
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
        private readonly Gpt3Tokenizer $gp3Tokenizer,
    ) {
    }

    /**
     * @param callable(FormFactoryInterface, float): FormInterface $newForm
     * @param FormInterface|null $form A previously submitted forms with validation errors
     */
    public function fillForm(
        string $context,
        callable $newForm,
        int $retries = 0,
        ?FormInterface $form = null,
        ?callable $onChunk = null,
    ): FormInterface {
        $form ??= $newForm($this->formFactory);

        $formattedErrors = $this->formatErrors($form);

        $schema = $this->liform->transform($form);
        unset($schema['title']);

        $llmOnChunk = null;
        $t0 = microtime(true);
        if ($onChunk !== null) {
            $llmOnChunk = function (mixed $data, string $rawData) use ($onChunk, $newForm, $t0) {
                $secondsElapsed = microtime(true) - $t0;

                $onChunk(
                    $this->createFormWithLLMData($data, $newForm),
                    $this->gp3Tokenizer->count($rawData) / $secondsElapsed,
                );
            };
        }
        try {
            $data = $this->llm->get(
                $schema,
                $context,
                $formattedErrors,
                $form->getData(),
                $llmOnChunk,
            );
        } catch (\Throwable $e) {
            dump($e, @$e->getResponse()->getBody()->getContents(true));
            throw $e;
        }

        $form = $this->createFormWithLLMData($data, $newForm);

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
            onChunk: $onChunk,
        );
    }

    /**
     * @param callable(FormBuilderInterface, float): FormInterface $newEntryForm
     */
    public function fillCollection(
        string $context,
        callable $newEntryForm,
        ?int $minEntries = null,
        array $entryOptions = [],
        int $retries = 0,
        ?callable $onChunk = null,
    ): FormInterface {
        $entryType = new class() extends AbstractType {
            /**
             * @var callable(FormBuilderInterface): FormInterface
             */
            public static $newEntryForm = null;

            public static array $entryOptions = [];

            public function buildForm(FormBuilderInterface $builder, array $options): void
            {
                call_user_func(static::$newEntryForm, $builder);
            }

            public function configureOptions(OptionsResolver $resolver)
            {
                $resolver->setDefaults(static::$entryOptions);
            }
        };
        $entryType::$newEntryForm = $newEntryForm;
        $entryType::$entryOptions = $entryOptions;

        $entryTypeName = get_class($entryType);
        $constraints = [];
        if ($minEntries !== null) {
            $constraints[] = new Count([
                'min' => $minEntries,
            ]);
        }

        return $this->fillForm(
            $context,
            fn (FormFactoryInterface $ff) => $ff->createBuilder()
                ->add('list', CollectionType::class, [
                    'entry_type' => $entryTypeName,
                    'entry_options' => $entryOptions,
                    'allow_add' => true,
                    'constraints' => $constraints,
                ])
                ->getForm(),
            $retries,
            null,
            $onChunk
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
                        '#(^data|[.]?children|[.]data$)#',
                        ''
                    );
                    $cleanPropertyPath = replace(
                        $cleanPropertyPath,
                        '#\[([a-z]\w*)\]#i',
                        '.$1'
                    );
                    $cleanPropertyPath = replace(
                        $cleanPropertyPath,
                        '#^[.]#',
                        ''
                    );

                    return [
                        'message' => $error->getMessage(),
                        'path' => $cleanPropertyPath,
                    ];
                }
            )
        );
    }

    /**
     * @return mixed
     */
    public function createFormWithLLMData(mixed $data, callable $newForm)
    {
        if (! is_array($data) && $data !== null && ! is_string($data)) {
            $this->logger->warning('LLM returned invalid data', [
                'data' => $data,
            ]);
            $data = [];
        }
        $form = $newForm($this->formFactory);
        $form->submit($data);

        return $form;
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
