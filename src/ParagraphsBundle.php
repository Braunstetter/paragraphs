<?php

namespace Braunstetter\Paragraphs;

use Braunstetter\Paragraphs\DependencyInjection\ParagraphsBundleExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ParagraphsBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new ParagraphsBundleExtension();
    }

}