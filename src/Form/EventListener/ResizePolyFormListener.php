<?php

namespace Braunstetter\Paragraphs\Form\EventListener;

use Braunstetter\Paragraphs\Form\Type\ParagraphsType;
use Braunstetter\Paragraphs\Service\ParagraphHelper;
use Doctrine\Common\Util\ClassUtils;
use InvalidArgumentException;
use JetBrains\PhpStorm\Pure;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Extension\Core\EventListener\ResizeFormListener;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class ResizePolyFormListener extends ResizeFormListener
{
    /**
     * Stores an array of Types with the Type name as the key.
     */
    protected array $typeMap = array();

    /**
     * Stores an array of types with the Data Class as the key.
     */
    protected array $classMap = array();

    /**
     * Name of the hidden field identifying the type.
     */
    protected string $typeFieldName;

    /**
     * Name of the index field on the given entity.
     */
    protected ?string $indexProperty;

    /**
     * Property Accessor.
     *
     * @var PropertyAccessor
     */
    protected PropertyAccessor $propertyAccessor;

    protected mixed $useTypesOptions;

    /**
     * @param array<FormInterface> $prototypes
     * @param array $options
     * @param bool $allowAdd
     * @param bool $allowDelete
     * @param string $typeFieldName
     * @param null $indexProperty
     * @param bool $useTypesOptions
     */
    public function __construct(array $prototypes, array $options = array(), bool $allowAdd = true, bool $allowDelete = false, $typeFieldName = '_type', $indexProperty = null, bool $useTypesOptions = false)
    {
        $this->typeFieldName = $typeFieldName;
        $this->indexProperty = $indexProperty;
        $this->useTypesOptions = $useTypesOptions;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $defaultType = null;

        foreach ($prototypes as $prototype) {
            $modelClass = $prototype->getConfig()->getOption('data_class');

            /** @var ParagraphsType $type */
            $type = $prototype->getConfig()->getType()->getInnerType();

            ParagraphHelper::addTypeField($prototype, $modelClass);
            $typeHandle = $prototype->get('_type')->getData();

            if (null === $defaultType) {
                $defaultType = $type;
            }

            $this->typeMap[$typeHandle] = get_class($type);
            $this->classMap[$modelClass] = get_class($type);
        }

        parent::__construct(get_class($defaultType), $options, $allowAdd, $allowDelete);
    }

    /**
     * Returns the form type for the supplied object. If a specific
     * form type is not found, it will return the default form type.
     */
    #[Pure] protected function getTypeForObject($object): string
    {
        $class = get_class($object);
        $class = ClassUtils::getRealClass($class);
        $type = $this->type;

        if (array_key_exists($class, $this->classMap)) {
            $type = $this->classMap[$class];
        }

        return $type;
    }

    /**
     * Checks the form data for a hidden _type field that indicates
     * the form type to use to process the data.
     *
     * @param array $data
     *
     * @return string|FormTypeInterface
     *
     * @throws InvalidArgumentException when _type is not present or is invalid
     */
    protected function getTypeForData(array $data): FormTypeInterface|string
    {
        if (!array_key_exists($this->typeFieldName, $data) || !array_key_exists($data[$this->typeFieldName], $this->typeMap)) {
            throw new InvalidArgumentException('Unable to determine the Type for given data');
        }


        return $this->typeMap[$data[$this->typeFieldName]];
    }

    protected function getOptionsForType($type)
    {
        if ($this->useTypesOptions === true) {
            return $this->options[$type] ?? [];
        } else {
            return $this->options;
        }
    }

    public function preSetData(FormEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        if (null === $data) {
            $data = array();
        }

        if (!is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($data, 'array or (\Traversable and \ArrayAccess)');
        }

        // First remove all rows
        foreach ($form as $name => $child) {
            $form->remove($name);
        }

        // Then add all rows again in the correct order for the incoming data
        foreach ($data as $name => $value) {
            $type = $this->getTypeForObject($value);
            $options = $this->getOptionsForType($type);

            $form->add($name, $type, array_replace(['property_path' => '[' . $name . ']'], array_replace($options, ['block_prefix' => '_paragraph'])));

            // Make sure the _type field is set on every row
            !$form->get($name)->has('_type') && ParagraphHelper::addTypeField($form->get($name), $value);
        }
    }

    public function preBind(FormEvent $event)
    {
        $this->preSubmit($event);
    }

    public function preSubmit(FormEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        if (null === $data || '' === $data) {
            $data = array();
        }

        if (!is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($data, 'array or (\Traversable and \ArrayAccess)');
        }

        // Remove all empty rows
        if ($this->allowDelete) {
            foreach ($form as $name => $child) {
                if (!isset($data[$name])) {
                    $form->remove($name);
                }
            }
        }

        // Add all additional rows
        if ($this->allowAdd) {
            foreach ($data as $name => $value) {
                if (!$form->has($name)) {
                    $type = $this->getTypeForData($value);
                    $form->add($name, $type, array_replace(array(
                        'property_path' => '[' . $name . ']',
                    ), $this->getOptionsForType($type)));
                }

                // Make sure the _type field is set on every row
                !$form->get($name)->has('_type') && ParagraphHelper::addTypeField($form->get($name), $form->get($name)->getConfig()->getDataClass());
            }
        }

    }
}