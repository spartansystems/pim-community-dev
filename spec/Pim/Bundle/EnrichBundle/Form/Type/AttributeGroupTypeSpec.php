<?php

namespace spec\Pim\Bundle\EnrichBundle\Form\Type;

use Doctrine\ORM\EntityManager;
use PhpSpec\ObjectBehavior;
use Pim\Bundle\CatalogBundle\Repository\AssociationRepositoryInterface;
use Pim\Bundle\CatalogBundle\Repository\GroupRepositoryInterface;
use Pim\Bundle\CatalogBundle\Repository\ProductRepositoryInterface;
use Pim\Bundle\EnrichBundle\Form\Subscriber\DisableFieldSubscriber;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class AttributeGroupTypeSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith('Pim\Bundle\CatalogBundle\Entity\AttributeGroup');
    }

    function it_is_a_form_type()
    {
        $this->shouldBeAnInstanceOf('Symfony\Component\Form\AbstractType');
    }

    function it_has_a_name()
    {
        $this->getName()->shouldReturn('pim_enrich_attribute_group');
    }

    function it_builds_form(FormBuilderInterface $builder)
    {
        $builder->add('code')->willReturn($builder);
        $builder->add(
            'label',
            'pim_translatable_field',
            [
                'field'             => 'label',
                'translation_class' => 'Pim\\Bundle\\CatalogBundle\\Entity\\AttributeGroupTranslation',
                'entity_class'      => 'Pim\\Bundle\\CatalogBundle\\Entity\\AttributeGroup',
                'property_path'     => 'translations'
            ]
        )->willReturn($builder);

        $builder->add('sort_order', 'hidden')->willReturn($builder);
        $builder->addEventSubscriber(new DisableFieldSubscriber('code'))->shouldBeCalled();

        $this->buildForm($builder, []);
    }

    function it_does_not_map_the_fields_to_the_entity_by_default(OptionsResolverInterface $resolver)
    {
        $this->setDefaultOptions($resolver, []);

        $resolver->setDefaults(
            [
                'data_class' => 'Pim\Bundle\CatalogBundle\Entity\AttributeGroup',
            ]
        )->shouldHaveBeenCalled();
    }
}
