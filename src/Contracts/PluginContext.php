<?php

namespace atc\WXC\Contracts;

use atc\WXC\Contracts\ModuleInterface;

interface PluginContext
{
    public function getSettingsManager();
    public function modulesBooted(): bool;
    public function getActiveModules(): array;
    public function getActivePostTypes(): array;
    //
    public function getModule(string $key): ?ModuleInterface;
}
