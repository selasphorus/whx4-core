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
    
    /**
      * Get the unqualified class name.
      *
      * @param string|object $class FQCN or instance.
      * @return string Short name, e.g. 'Event' for atc\WHx4\Modules\Events\PostTypes\Event.
      */
     public static function getShortName(string|object $class): string
     {
         $fqcn = is_object($class) ? $class::class : $class;
         $pos  = strrpos($fqcn, '\\');
 
         return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
     }
}
