<?php

namespace AdrienBrault\Instructrice\Tests;

use Limenius\Liform\Form\Extension\AddLiformExtension;
use Limenius\Liform\Resolver;
use Limenius\Liform\Liform;
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
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Validation;
use function Psl\Json\decode;
use function Psl\Json\encode;

class FirstTest extends TestCase
{
    public function test(): void
    {
        $peopleType = new class() extends AbstractType {
            public function buildForm(FormBuilderInterface $builder, array $options): void
            {
                $builder
                    ->add('name', TextType::class)
                    ->add('personality', TextareaType::class, [
                        'liform' => [
                            'description' => 'Describe what this person is like.',
                        ],
                        'constraints' => [
                            new Length(['min' => 75]),
                        ],
                    ])
                ;
            }
        };
        $form = $this->getForm($peopleType);

        $liform = $this->createLiform();

        $client = OpenAI::factory()
            ->withBaseUri(getenv('OLLAMA_HOST') . '/v1')
            ->make();

        $request = [
            'model' => 'adrienbrault/nous-hermes2pro:q4_K_M',
//            'model' => 'command-r:35b-v0.1-q5_K_M',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->getHermes2ProJsonPrompt(
                        json_encode($liform->transform($form))
                    )
                ],
                [
                    'role' => 'user',
                    'content' => 'Harry potter, emanuel macron',
                ],
            ],
            'response_format' => [
                'type' => 'json_object',
            ],
            'max_tokens' => 1000,
        ];
        $result = $client->chat()->create(dump($request));

        dump($result);
        $data = json_decode(
            $result->choices[0]->message->content,
            true,
            flags: JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        $form->submit($data);

        dump([
            'isvalid' => $form->isValid(),
            'errors' => $this->getFormSerializer()->normalize($form),
        ]);

        $this->assertFalse($form->isValid()); // expecting min length error

        $errors = $this->getFormSerializer()->normalize($form);

        $request['messages'][] = $result->choices[0]->message->toArray();
        $request['messages'][] = [
            'role' => 'user',
            'content' => sprintf(
                'Try again, fixing the following errors: <validator-errors>%s</validator-errors>',
                encode($errors)
            ),
        ];

        $result = $client->chat()->create(dump($request));

        dump($result);
        $data = json_decode(
            $result->choices[0]->message->content,
            true,
            flags: JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        dump($data);

        $form = $this->getForm($peopleType);
        $form->submit($data);

        dump([
            'isvalid' => $form->isValid(),
            'errors' => $this->getFormSerializer()->normalize($form),
        ]);

        $this->assertTrue($form->isValid()); // now it should have worked.
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

    /**
     * @return Translator
     */
    public function getTranslator(): Translator
    {
        return new Translator('en_US');
    }

    /**
     * @param AbstractType $peopleType
     * @return \Symfony\Component\Form\FormInterface
     */
    public function getForm(AbstractType $peopleType): \Symfony\Component\Form\FormInterface
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

    /**
     * @return Serializer
     */
    public function getFormSerializer(): Serializer
    {
        $encoders = array(new XmlEncoder(), new JsonEncoder());
        $normalizers = array(new FormErrorNormalizer($this->getTranslator()));

        $serializer = new Serializer($normalizers, $encoders);
        return $serializer;
    }
}
