<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\Form\Type;

use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityExtendBundle\Form\Type\EnumValueType;
use Oro\Bundle\TranslationBundle\Translation\IdentityTranslator;
use Oro\Component\Testing\Unit\PreloadedExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Validator\Type\FormTypeValidatorExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Test\FormInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\CollectionValidator;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;
use Symfony\Component\Validator\Mapping\Loader\LoaderChain;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Validator\Validator\RecursiveValidator;

class EnumValueTypeTest extends TypeTestCase
{
    /** @var EnumValueType */
    private $type;

    /** @var ConstraintValidatorInterface[] */
    private $validators;

    protected function setUp(): void
    {
        $this->type = new EnumValueType($this->getConfigProvider());
        parent::setUp();
    }

    /**
     * {@inheritDoc}
     */
    protected function getExtensions()
    {
        $validator = new RecursiveValidator(
            new ExecutionContextFactory(new IdentityTranslator()),
            new LazyLoadingMetadataFactory(new LoaderChain([])),
            new ConstraintValidatorFactory()
        );

        return [
            new PreloadedExtension(
                [
                    EnumValueType::class => $this->type
                ],
                [
                    FormType::class => [
                        new FormTypeValidatorExtension($validator)
                    ]
                ]
            ),
            new ValidatorExtension($this->getValidator()),
        ];
    }

    /**
     * @dataProvider submitProvider
     */
    public function testSubmit(array $inputData, array $expectedData)
    {
        $form = $this->factory->create(EnumValueType::class);
        $form->submit($inputData['form']);

        $this->assertEquals($expectedData['valid'], $form->isValid());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitValidDataForNewEnumValue()
    {
        $formData = [
            'label'      => 'Value 1',
            'is_default' => true,
            'priority'   => 1
        ];

        $form = $this->factory->create(EnumValueType::class);
        $form->submit($formData);
        $this->assertTrue($form->isSynchronized());
        $this->assertEquals(
            [
                'id'         => null,
                'label'      => 'Value 1',
                'is_default' => true,
                'priority'   => '1'
            ],
            $form->getData()
        );

        $nameConstraints = $form->get('label')->getConfig()->getOption('constraints');
        $this->assertCount(2, $nameConstraints);

        $this->assertInstanceOf(
            NotBlank::class,
            $nameConstraints[0]
        );

        $this->assertInstanceOf(
            Length::class,
            $nameConstraints[1]
        );
        $this->assertEquals(255, $nameConstraints[1]->max);
    }

    public function testSubmitValidDataForExistingEnumValue()
    {
        $formData = [
            'id'         => 'val1',
            'label'      => 'Value 1',
            'is_default' => true,
            'priority'   => 1
        ];

        $form = $this->factory->create(EnumValueType::class);
        $form->submit($formData);
        $this->assertTrue($form->isSynchronized());
        $this->assertEquals(
            [
                'id'         => 'val1',
                'label'      => 'Value 1',
                'is_default' => true,
                'priority'   => '1'
            ],
            $form->getData()
        );

        $nameConstraints = $form->get('label')->getConfig()->getOption('constraints');
        $this->assertCount(2, $nameConstraints);
    }

    /**
     * @dataProvider allowDeleteProvider
     */
    public function testAllowDelete(array $inputData, array $expectedData)
    {
        $configProvider = $this->getConfigProvider(
            $inputData['has_config'],
            $inputData['enum_code'],
            $inputData['immutable']
        );

        $type = new EnumValueType($configProvider);
        $form = $this->factory->create(EnumValueType::class);
        $form->setParent($this->getConfiguredForm());

        $form->submit($inputData['form']);
        $view = new FormView();
        $type->buildView($view, $form, []);

        $this->assertArrayHasKey('allow_delete', $view->vars);
        $this->assertSame($expectedData['allow_delete'], $view->vars['allow_delete']);
    }

    public function allowDeleteProvider(): array
    {
        return [
            'no config' => [
                'input' => [
                    'form' => [
                        'id' => 'open',
                        'label' => 'Label',
                        'is_default' => true,
                        'priority' => 1,
                    ],
                    'has_config' => false,
                    'enum_code' => 'task_status',
                    'immutable' => ['open', 'close']
                ],
                'expected' => [
                    'allow_delete' => true,
                ],
            ],
            'no enum code' => [
                'input' => [
                    'form' => [
                        'id' => 'open',
                        'label' => 'Label',
                        'is_default' => true,
                        'priority' => 1,
                    ],
                    'has_config' => true,
                    'enum_code' => '',
                    'immutable' => ['open', 'close']
                ],
                'expected' => [
                    'allow_delete' => true,
                ],
            ],
            'immutable open' => [
                'input' => [
                    'form' => [
                        'id' => 'open',
                        'label' => 'Label',
                        'is_default' => true,
                        'priority' => 1,
                    ],
                    'has_config' => true,
                    'enum_code' => 'task_status',
                    'immutable' => ['open', 'close']
                ],
                'expected' => [
                    'allow_delete' => false,
                ],
            ],
            'immutable close' => [
                'input' => [
                    'form' => [
                        'id' => 'close',
                        'label' => 'Label',
                        'is_default' => true,
                        'priority' => 1,
                    ],
                    'has_config' => true,
                    'enum_code' => 'task_status',
                    'immutable' => ['open', 'close']
                ],
                'expected' => [
                    'allow_delete' => false,
                ],
            ],
            'allow delete' => [
                'input' => [
                    'form' => [
                        'id' => 'deletable',
                        'label' => 'Label',
                        'is_default' => true,
                        'priority' => 1,
                    ],
                    'has_config' => true,
                    'enum_code' => 'task_status',
                    'immutable' => ['open', 'close']
                ],
                'expected' => [
                    'allow_delete' => true,
                ],
            ],
        ];
    }

    public function submitProvider(): array
    {
        return [
            'valid data' => [
                'input' => [
                    'form' => [
                        'label' => 'Label1',
                        'is_default' => true,
                        'priority' => 1,
                    ],
                ],
                'expected' => [
                    'valid' => true,
                ],
            ],
            'empty label' => [
                'input' => [
                    'form' => [
                        'is_default' => true,
                        'priority' => 2,
                    ],
                ],
                'expected' => [
                    'valid' => false,
                ],
            ],
            'long label' => [
                'input' => [
                    'form' => [
                        'label' => str_repeat('l', 256),
                        'is_default' => true,
                        'priority' => 3,
                    ],
                ],
                'expected' => [
                    'valid' => false,
                ],
            ],
            'incorrect label and empty id' => [
                'input' => [
                    'form' => [
                        'label' => '!@#$',
                        'is_default' => true,
                        'priority' => 1,
                    ],
                ],
                'expected' => [
                    'valid' => false,
                ],
            ],
            'correct label and not empty id' => [
                'input' => [
                    'form' => [
                        'id' => 11,
                        'label' => '!@#$',
                        'is_default' => true,
                        'priority' => 1,
                    ],
                ],
                'expected' => [
                    'valid' => true,
                ],
            ],
        ];
    }

    private function getValidator(): RecursiveValidator
    {
        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects($this->any())
            ->method('loadClassMetadata')
            ->willReturnCallback(function (ClassMetadata $meta) {
                $this->loadMetadata($meta);
            });

        return new RecursiveValidator(
            new ExecutionContextFactory(new IdentityTranslator()),
            new LazyLoadingMetadataFactory($loader),
            $this->getConstraintValidatorFactory()
        );
    }

    private function loadMetadata(ClassMetadata $meta): void
    {
        $configFile = $this->getConfigFile($meta->name);
        if ($configFile) {
            $loader = new YamlFileLoader($configFile);
            $loader->loadClassMetadata($meta);
        }
    }

    private function getConstraintValidatorFactory(): ConstraintValidatorFactoryInterface
    {
        $factory = $this->createMock(ConstraintValidatorFactoryInterface::class);
        $factory->expects($this->any())
            ->method('getInstance')
            ->willReturnCallback(function (Constraint $constraint) {
                $className = $constraint->validatedBy();

                if (!isset($this->validators[$className]) || CollectionValidator::class === $className) {
                    $this->validators[$className] = new $className();
                }

                return $this->validators[$className];
            });

        return $factory;
    }

    private function getConfigFile(string $class): ?string
    {
        $path = $this->getBundleRootPath($class);
        if ($path) {
            $path .= '/Resources/config/validation.yml';
            if (!is_readable($path)) {
                $path = null;
            }
        }

        return $path;
    }

    private function getBundleRootPath(string $class): ?string
    {
        $refl = new \ReflectionClass($class);
        $path = null;
        $pos = strrpos($refl->getFileName(), 'Bundle');
        if (false !== $pos) {
            $path = substr($refl->getFileName(), 0, $pos) . 'Bundle';
        }

        return $path;
    }

    private function getConfigProvider(
        bool $hasConfig = false,
        string $enumCode = '',
        array $immutableCodes = []
    ): ConfigProvider {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->expects($this->any())
            ->method('hasConfigById')
            ->willReturn($hasConfig);

        if ($hasConfig) {
            $config = $this->createMock(Config::class);
            $config->expects($this->any())
                ->method('get')
                ->willReturnMap([
                    ['enum_code', false, null, $enumCode],
                    ['immutable_codes', false, [], $immutableCodes],
                ]);

            $configProvider->expects($this->any())
                ->method('getConfigById')
                ->willReturn($config);

            $configProvider->expects($this->any())
                ->method('getConfig')
                ->willReturn($config);
        }

        return $configProvider;
    }

    private function getConfiguredForm(): FormInterface
    {
        $configId = new FieldConfigId('enum', 'Test\Entity', 'status', 'enum');
        $formConfig = $this->createMock(FormConfigInterface::class);
        $formConfig->expects($this->once())
            ->method('getOption')
            ->willReturn($configId);

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())
            ->method('getConfig')
            ->willReturn($formConfig);

        return $form;
    }
}
