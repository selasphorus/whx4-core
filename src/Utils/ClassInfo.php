<?php

namespace atc\WXC\Utils;

final class ClassInfo
{
    private function __construct() {}

    public static function getModuleKey(string|object $class): ?string
    {
        $fqcn = is_object($class) ? $class::class : $class;

        if (preg_match('#\\\\Modules\\\\([^\\\\]+)\\\\#', $fqcn, $m)) {
            return strtolower($m[1]); // e.g., 'supernatural'
        }

        return null;
    }

    public static function getViewNamespace(string|object $class): ?string
    {
        return self::getModuleKey($class);
    }
}
