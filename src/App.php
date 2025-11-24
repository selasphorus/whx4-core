<?php

namespace atc\WXC;

use LogicException;
use atc\WXC\Contracts\PluginContext;

final class App
{
    private static ?PluginContext $ctx = null;

    public static function setContext(PluginContext $ctx): void
    {
        self::$ctx = $ctx;
    }

    public static function ctx(): PluginContext
    {
        if (self::$ctx === null) {
            throw new LogicException('PluginContext not set. Call App::setContext() during plugin boot.');
        }
        return self::$ctx;
    }

    private function __construct() {}
}
