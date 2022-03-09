<?php

namespace Braunstetter\Paragraphs\Form\Type;

use Braunstetter\Helper\Arr;
use Braunstetter\Paragraphs\Form\EventListener\ResizePolyFormListener;
use Closure;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Exception\InvalidConfigurationException;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\String\UnicodeString;

class ParagraphsType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $prototypes = $this->buildPrototypes($builder, $options);
        if ($options['allow_add'] && $options['prototype']) {
            $builder->setAttribute('prototypes', $prototypes);
        }

        $useTypesOptions = !empty($options['types_options']);

        $builder->addEventSubscriber(new ResizePolyFormListener(
            $prototypes,
            $useTypesOptions === true ? $options['types_options'] : $options['options'],
            $options['allow_add'],
            $options['allow_delete'],
            $options['type_name'],
            $options['index_property'],
            $useTypesOptions
        ));

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var FormInterface $item */
            foreach ($event->getForm() as $childForm) {
                $childForm->add('position', HiddenType::class);
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            /** @var FormInterface $item */
            foreach ($event->getForm() as $childForm) {
                $childForm->add('position', HiddenType::class);
            }
        });
    }

    /**
     * Builds prototypes for each of the form types used for the collection.
     */
    protected function buildPrototypes(FormBuilderInterface $builder, array $options): array
    {
        $prototypes = array();
        $useTypesOptions = !empty($options['types_options']);

        foreach ($options['types'] as $key => $type) {
            if ($type instanceof FormTypeInterface) {
                @trigger_error(sprintf('Passing type instances to PolyCollection is deprecated since version 1.0.5 and will not be supported in 2.0. Use the fully-qualified type class name instead (%s).', get_class($type)), E_USER_DEPRECATED);
            }

            $typeOptions = $options['options'];
            if ($useTypesOptions) {
                $typeOptions = [];
                if (isset($options['types_options'][$type])) {
                    $typeOptions = $options['types_options'][$type];
                }
            }

            $typeOptions = array_replace($typeOptions, ['block_prefix' => '_paragraph']);

            $prototype = $this->buildPrototype(
                $builder,
                $options['prototype_name'],
                $type,
                $typeOptions
            );

            if (array_key_exists($key, $prototypes)) {
                throw new InvalidConfigurationException(sprintf(
                    'Each type of row in a polycollection must have a unique key. (Found "%s" in both %s and %s)',
                    $key,
                    get_class($prototypes[$key]->getConfig()->getType()->getInnerType()),
                    get_class($prototype->getType()->getInnerType())
                ));
            }

            if ($options['sortable'] && !$prototype->has($options['sortable_field'])) {
                $this->addPosition($prototype, $options['sortable_field']);
            }


            $prototypes[$key] = $prototype->getForm();

        }

        return $prototypes;
    }

    /**
     * Builds an individual prototype.
     */
    protected function buildPrototype(FormBuilderInterface $builder, string $name, FormTypeInterface|string $type, array $options): FormBuilderInterface
    {
        return $builder->create($name, $type, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['allow_add'] = $options['allow_add'];
        $view->vars['allow_delete'] = $options['allow_delete'];
        $view->vars['sortable'] = $options['sortable'];

        if ($form->getConfig()->hasAttribute('prototypes')) {
            $view->vars['prototypes'] = array_map(function (FormInterface $prototype) use ($view) {
                return $prototype->createView($view);
            }, $form->getConfig()->getAttribute('prototypes'));
        }

        $view->vars['row_attr'] = Arr::attachToAttrArray($view->vars['row_attr'], [
            'data-controller' => 'braunstetter--paragraphs--paragraphs'
        ]);

        $view->vars['attr'] = Arr::attachToAttrArray($view->vars['attr'], [
            'data-braunstetter--paragraphs--paragraphs-target' => 'fieldContainer',
            'class' => 'paragraphs'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        if ($form->getConfig()->hasAttribute('prototypes')) {
            $multiparts = array_filter(
                $view->vars['prototypes'],
                function (FormView $prototype) {
                    return $prototype->vars['multipart'];
                }
            );

            if ($multiparts) {
                $view->vars['multipart'] = true;
            }
        }

        if ($options['sortable']) {
            $accessor = PropertyAccess::createPropertyAccessor();
            usort($view->children, function (FormView $a, FormView $b) use ($accessor, $options) {
                return $accessor->getValue($a->vars['data'], $options['sortable_field']) <=> $accessor->getValue($b->vars['data'], $options['sortable_field']);
            });
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return '_paragraphs';
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'allow_add' => false,
            'allow_delete' => false,
            'prototype' => true,
            'prototype_name' => '__name__',
            'type_name' => '_type',
            'options' => [],
            'types_options' => [],
            'index_property' => null,
            'sortable' => true,
            'sortable_field' => 'position'
        ));

        $resolver->setRequired(['types']);

        $resolver->setAllowedTypes('types', 'array');
        $resolver->setAllowedTypes('sortable', 'boolean');
        $resolver->setAllowedTypes('sortable_field', 'string');

        $resolver->setNormalizer('options', $this->getOptionsNormalizer());
        $resolver->setNormalizer('types_options', $this->getTypesOptionsNormalizer());
    }

    private function getOptionsNormalizer(): Closure
    {
        return function (Options $options, $value) {
            $value['block_name'] = 'entry';

            return $value;
        };
    }

    private function getTypesOptionsNormalizer(): Closure
    {
        return function (Options $options, $value) {
            foreach ($options['types'] as $type) {
                if (isset($value[$type])) {
                    $value[$type]['block_name'] = 'entry';
                }
            }

            return $value;
        };
    }

    /**
     * @throws ReflectionException
     */
    public function getHandle(string $fqcn): string
    {
        $shortName = (new ReflectionClass($fqcn))->getShortName();

        return (new UnicodeString($shortName))
            ->trimSuffix('Type')
            ->snake()
            ->toString();
    }

    private function addPosition(FormBuilderInterface $form, string $sortable_field)
    {
        $form->add($sortable_field, HiddenType::class);
    }
}
