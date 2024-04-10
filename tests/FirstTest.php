<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\Tests;

use Limenius\Liform\Form\Extension\AddLiformExtension;
use Limenius\Liform\Liform;
use Limenius\Liform\Resolver;
use Limenius\Liform\Serializer\Normalizer\FormErrorNormalizer;
use Limenius\Liform\Transformer\ArrayTransformer;
use Limenius\Liform\Transformer\CompoundTransformer;
use Limenius\Liform\Transformer\IntegerTransformer;
use Limenius\Liform\Transformer\StringTransformer;
use OpenAI;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;
use function Psl\Json\encode;
use function Psl\Regex\replace;
use function Psl\Vec\map;

class FirstTest extends TestCase
{
    public function test(): void
    {
        // This form could be mapped to a class/model, have its own type class etc
        $peopleType = new class() extends AbstractType {
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
        };

        $form = $this->handleFormLLMSubmit(
            context: 'Jason fried, david cramer',
            newForm: fn() => $this->getArrayForm($peopleType),
            retries: 3
        );

        dump('final result', $form->getData());

        $this->assertTrue($form->isValid());
    }

    public function getFormFactory(array $types): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addTypeExtension(new AddLiformExtension())
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addTypes($types)
            ->getFormFactory();
    }

    private function createLiform(): Liform
    {
        $translator = $this->getTranslator();

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

    private function getHermes2ProJsonPrompt(string $schema): string
    {
        return <<<PROMPT
You are a helpful assistant that answers in JSON.
If the user intent is unclear, consider it a structured information extraction task.

Here's the json schema you must adhere to:
<schema>
{$schema}
</schema>
PROMPT;
    }

    private function getTranslator(): Translator
    {
        return new Translator('en_US');
    }

    private function getArrayForm(AbstractType $peopleType): FormInterface
    {
        $formFactory = $this->getFormFactory([
            $peopleType,
        ]);

        $form = $formFactory
            ->createBuilder()
            ->add('people', CollectionType::class, [
                'entry_type' => get_class($peopleType),
                'allow_add' => true,
            ])
            ->getForm();
        return $form;
    }

    private function getFormSerializer(): Serializer
    {
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizers = [new FormErrorNormalizer($this->getTranslator())];

        $serializer = new Serializer($normalizers, $encoders);
        return $serializer;
    }

    /**
     * @param callable(): FormInterface $newForm
     */
    private function handleFormLLMSubmit(
        string $context,
        callable $newForm,
        int $retries = 0,
        ?FormInterface $form = null
    ): mixed {
        $form ??= $newForm();

        $client = OpenAI::factory()
            ->withBaseUri(getenv('OLLAMA_HOST') . '/v1')
            ->make();

        $liform = $this->createLiform();

        $messages = [
            [
                'role' => 'system',
                'content' => $this->getHermes2ProJsonPrompt(
                    json_encode($liform->transform($form))
                ),
            ],
            [
                'role' => 'user',
                'content' => $context,
            ],
        ];

        if ($form->isSubmitted()
            && !$form->isValid()
        ) {
            $messages[] = [
                'role' => 'assistant',
                'content' => encode($form->getViewData(), true), // hopefully this is good enough
            ];
            $messages[] = [
                'role' => 'user',
                'content' => sprintf(
                    'Try again, fixing the following errors: <validator-errors>%s</validator-errors>',
                    encode(
                        $this->formatErrors($form)
                    )
                ),
            ];
        }
        $request = [
            'model' => 'adrienbrault/nous-hermes2pro:q4_K_M',
            // 'model' => 'command-r:35b-v0.1-q5_K_M',
            'messages' => $messages,
            'response_format' => [
                'type' => 'json_object',
            ],
            'max_tokens' => 4000,
        ];
        dump('request', $request);
        $result = $client->chat()->create($request);

        $content = $result->choices[0]->message->content;

        dump('response content', $content);

        $data = json_decode(
            $content,
            true,
            flags: JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        $form = $newForm();
        $form->submit($data);

        dump('form state', [
            'isvalid' => $form->isValid(),
            'errors' => !$form->isValid() ? $this->formatErrors($form) : [],
        ]);

        if ($retries > 0
            && !$form->isValid()
        ) {
            return $this->handleFormLLMSubmit(
                context: $context,
                newForm: $newForm,
                retries: $retries - 1,
                form: $form,
            );
        }

        return $form;
    }

    /**
     * @param FormInterface $form
     * @return array
     */
    public function formatErrors(FormInterface $form): array
    {
        return map(
            $form->getErrors(true),
            function (FormError $error) {
                $cause = $error->getCause();
                assert($cause instanceof ConstraintViolationInterface);

                return [
                    'message' => $error->getMessage(),
                    'path' => replace(
                        $cause->getPropertyPath(),
                        '#([.]?children|[.]data$)#',
                        ''
                    ),
                ];
            }
        );
    }
}
