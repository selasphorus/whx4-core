<?php

namespace atc\BhWP\Core;

use LogicException;
use atc\BhWP\Core\Contracts\PluginContext;

final class BhWP
{
    private static ?PluginContext $ctx = null;

    public static function setContext(PluginContext $ctx): void
    {
        self::$ctx = $ctx;
    }

    public static function ctx(): PluginContext
    {
        if (self::$ctx === null) {
            throw new LogicException('PluginContext not set. Call BhWP::setContext() during plugin boot.');
        }
        return self::$ctx;
    }

    private function __construct() {}
}
