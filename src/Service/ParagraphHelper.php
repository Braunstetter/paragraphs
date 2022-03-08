<?php

namespace Braunstetter\Paragraphs\Service;

use ReflectionClass;
use ReflectionException;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\String\UnicodeString;

class ParagraphHelper
{
    public static function getHandle(string|object $class): string
    {
        try {
            $reflectionClass = (new ReflectionClass($class));
        } catch (ReflectionException $e) {
            unset($e);
        }

        $shortName = isset($reflectionClass) ? $reflectionClass->getShortName() : 'Paragraph';

        return (new UnicodeString($shortName))
            ->trimSuffix('Type')
            ->snake()
            ->toString();
    }


    public static function addTypeField(FormInterface $form, string|object $class): FormInterface
    {
        return $form->add('_type', HiddenType::class, [
            'mapped' => false,
            'data' => static::getHandle($class)
        ]);
    }
}