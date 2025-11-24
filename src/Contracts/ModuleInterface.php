<?php

namespace atc\WXC\Contracts;

interface ModuleInterface
{
    public function getName(): string;
    public function getPostTypeHandlerClasses(): array;
}
