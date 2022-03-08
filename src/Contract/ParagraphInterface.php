<?php

namespace Braunstetter\Paragraphs\Contract;

interface ParagraphInterface
{
    public function getPosition(): ?int;

    public function setPosition(int $position): self;

    public function getHandle(): string;
}