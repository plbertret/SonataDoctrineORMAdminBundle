<?php

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\DoctrineORMAdminBundle\Tests\Builder;

use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Admin\FieldDescriptionInterface;
use Sonata\AdminBundle\Form\Type\AdminType;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Sonata\AdminBundle\Form\Type\ModelHiddenType;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\AdminBundle\Model\ModelManagerInterface;
use Sonata\CoreBundle\Form\Type\CollectionType;
use Sonata\DoctrineORMAdminBundle\Builder\FormContractor;
use Sonata\DoctrineORMAdminBundle\Model\ModelManager;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * @author Sullivan Senechal <soullivaneuh@gmail.com>
 */
final class FormContractorTest extends TestCase
{
    /**
     * @var FormFactoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $formFactory;

    /**
     * @var FormContractor
     */
    private $formContractor;

    protected function setUp()
    {
        $this->formFactory = $this->createMock(FormFactoryInterface::class);

        $this->formContractor = new FormContractor($this->formFactory);
    }

    public function testGetFormBuilder()
    {
        $this->formFactory->expects($this->once())->method('createNamedBuilder')
            ->willReturn($this->createMock(FormBuilderInterface::class));

        $this->assertInstanceOf(
            FormBuilderInterface::class,
            $this->formContractor->getFormBuilder('test', ['foo' => 'bar'])
        );
    }

    public function testDefaultOptionsForSonataFormTypes()
    {
        $admin = $this->createMock(AdminInterface::class);
        $modelManager = $this->createMock(ModelManagerInterface::class);
        $modelClass = 'FooEntity';

        $admin->method('getModelManager')->willReturn($modelManager);
        $admin->method('getClass')->willReturn($modelClass);

        $fieldDescription = $this->createMock(FieldDescriptionInterface::class);
        $fieldDescription->method('getAdmin')->willReturn($admin);
        $fieldDescription->method('getTargetEntity')->willReturn($modelClass);
        $fieldDescription->method('getAssociationAdmin')->willReturn($admin);

        // NEXT_MAJOR: Use only FQCNs when dropping support for Symfony 2.8
        $modelTypes = [
            'sonata_type_model',
            'sonata_type_model_list',
            'sonata_type_model_hidden',
            'sonata_type_model_autocomplete',
            ModelType::class,
            ModelListType::class,
            ModelHiddenType::class,
            ModelAutocompleteType::class,
        ];
        $adminTypes = [
            'sonata_type_admin',
            AdminType::class,
        ];
        $collectionTypes = [
            'sonata_type_collection',
            CollectionType::class,
        ];

        // model types
        foreach ($modelTypes as $formType) {
            $options = $this->formContractor->getDefaultOptions($formType, $fieldDescription);
            $this->assertSame($fieldDescription, $options['sonata_field_description']);
            $this->assertSame($modelClass, $options['class']);
            $this->assertSame($modelManager, $options['model_manager']);
        }

        // admin type
        $fieldDescription->method('getMappingType')->willReturn(ClassMetadata::ONE_TO_ONE);
        foreach ($adminTypes as $formType) {
            $options = $this->formContractor->getDefaultOptions($formType, $fieldDescription);
            $this->assertSame($fieldDescription, $options['sonata_field_description']);
            $this->assertSame($modelClass, $options['data_class']);
            $this->assertFalse($options['btn_add']);
            $this->assertFalse($options['delete']);
        }

        // collection type
        $fieldDescription->method('getMappingType')->willReturn(ClassMetadata::ONE_TO_MANY);
        foreach ($collectionTypes as $formType) {
            $options = $this->formContractor->getDefaultOptions($formType, $fieldDescription);
            $this->assertSame($fieldDescription, $options['sonata_field_description']);
            $this->assertSame(AdminType::class, $options['type']);
            $this->assertTrue($options['modifiable']);
            $this->assertSame($fieldDescription, $options['type_options']['sonata_field_description']);
            $this->assertSame($modelClass, $options['type_options']['data_class']);
        }
    }

    public function testAdminClassAttachForNotMappedField()
    {
        // Given
        $modelManager = $this->createMock(ModelManager::class);
        $modelManager->method('hasMetadata')->willReturn(false);

        $admin = $this->createMock(AdminInterface::class);
        $admin->method('getModelManager')->willReturn($modelManager);

        $fieldDescription = $this->createMock(FieldDescriptionInterface::class);
        $fieldDescription->method('getMappingType')->willReturn('simple');
        $fieldDescription->method('getType')->willReturn('sonata_type_model_list');
        $fieldDescription->method('getOption')->with($this->logicalOr(
            $this->equalTo('edit'),
            $this->equalTo('admin_code')
        ))->willReturn('sonata.admin.code');

        // Then
        $admin
            ->expects($this->once())
            ->method('attachAdminClass')
            ->with($fieldDescription)
        ;

        // When
        $this->formContractor->fixFieldDescription($admin, $fieldDescription);
    }
}
