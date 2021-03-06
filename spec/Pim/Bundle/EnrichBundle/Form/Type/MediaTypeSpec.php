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

class MediaTypeSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith('Pim\Bundle\CatalogBundle\Model\ProductMedia');
    }

    function it_is_a_form_type()
    {
        $this->shouldBeAnInstanceOf('Symfony\Component\Form\AbstractType');
    }

    function it_has_a_name()
    {
        $this->getName()->shouldReturn('pim_enrich_media');
    }

    function it_builds_form(FormBuilderInterface $builder)
    {
        $builder->add('file', 'file', ['required' => false])->willReturn($builder);
        $builder->add(
            'removed',
            'checkbox',
            [
                'required' => false,
                'label'    => 'Remove media',
            ]
        )->willReturn($builder);

        $builder->add('id', 'hidden')->willReturn($builder);
        $builder->add('copyFrom', 'hidden')->shouldBeCalled();

        $this->buildForm($builder, []);
    }

    function it_sets_default_options(OptionsResolverInterface $resolver)
    {
        $this->setDefaultOptions($resolver, []);

        $resolver->setDefaults(
            [
                'data_class' => 'Pim\Bundle\CatalogBundle\Model\ProductMedia',
            ]
        )->shouldHaveBeenCalled();
    }
}
