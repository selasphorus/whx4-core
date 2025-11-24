<?php

declare(strict_types=1);

namespace atc\WXC\Http;

/**
 * UrlParamBridge
 *
 * Reads a handler's canonical URL-parameter spec (allowedUrlParams)
 * and converts $_GET (or a provided source) into normalized PostQuery args.
 *
 * Mapping supported -- WIP!:
 * - ['arg' => 'scope'] → sets $out['scope'] = '...'
 * - ['arg' => 'ttype'] → sets $out['ttype'] = '...'
 * - ['tax' => 'transaction_type', 'field' => 'slug'] → $out['tax']['transaction_type'] = [...] -- this assuming we make this a taxonomy instead of a custom field - TBD
 *
 * Notes:
 * - The bridge does NOT run WP_Query. It only normalizes inputs.
 * - Precedence (URL vs shortcode atts) can be applied via merge().
 */
final class UrlParamBridge
{
    /**
     * Collect from $_GET by default.
     */
    //UrlParamBridge::collect(string $targetHandlerClass, array $source = $_GET, ?array $only = null): array
    /*
    Looks up ::allowedUrlParams() on the target handler, sanitizes source values, returns normalized inputs ready for PostQuery 
    (e.g., ['scope' => '2024', 'tax' => ['transaction_type' => ['income']]]).
    */
    public static function collect(string $targetHandlerClass, ?array $only = null): array
    {
        //error_log( '=== UrlParamBridge::collect for targetHandlerClass: ' . $targetHandlerClass . ' (only: ' . $only . ') ===' );
        return self::fromSource($targetHandlerClass, $_GET, $only);
    }

    /**
     * Collect from a specific source array (e.g., for tests).
     */
    public static function fromSource(string $targetHandlerClass, array $source, ?array $only = null): array
    {
        $spec = method_exists($targetHandlerClass, 'allowedUrlParams')
            ? $targetHandlerClass::allowedUrlParams()
            : [];

        if ($only !== null) {
            $only = array_flip(array_map('strval', $only));
        }

        $out = [
            // PostQuery contracts:
            // 'scope' => 'this_week' | '2024' | ...
            // 'tax'   => [ taxonomy => [terms...] ]
        ];

        foreach ($spec as $param => $rules) {
            if ($only !== null && !isset($only[$param])) {
                continue;
            }

            $value = self::readParam($source, $param);
            if ($value === null) {
                continue;
            }

            $san = self::applySanitizer($rules['sanitize'] ?? null, $value);
            if (self::isEmptyValue($san)) {
                continue;
            }

            $map = $rules['map_to'] ?? [];
            if (isset($map['arg'])) {
                // Generic arg mapping (e.g., 'scope', 'orderby', etc.)
                $argKey = (string)$map['arg'];
                $out[$argKey] = $san;
            }

            if (isset($map['tax'])) {
                // Taxonomy mapping
                $tax = (string)$map['tax'];
                $vals = is_array($san) ? $san : [$san];
                if (!isset($out['tax'])) {
                    $out['tax'] = [];
                }
                if (!isset($out['tax'][$tax])) {
                    $out['tax'][$tax] = [];
                }
                $out['tax'][$tax] = array_values(array_unique(array_merge($out['tax'][$tax], $vals)));
            }
        }

        return $out;
    }

    /**
     * Merge normalized URL args into base shortcode/controller args,
     * honoring each param's 'override' flag from the handler spec.
     *
     * - If override=true and URL has a value, URL wins.
     * - If override=false, base wins (URL is ignored for that param).
     * - For 'tax', arrays are union-merged.
     */
    public static function merge(string $targetHandlerClass, array $baseArgs, array $urlArgs): array
    {
        $spec = method_exists($targetHandlerClass, 'allowedUrlParams')
            ? $targetHandlerClass::allowedUrlParams()
            : [];

        $final = $baseArgs;

        // Simple arg keys (e.g., 'scope', 'orderby', etc.)
        foreach ($spec as $param => $rules) {
            $map = $rules['map_to'] ?? [];
            $override = (bool)($rules['override'] ?? false);

            if (isset($map['arg'])) {
                $argKey = (string)$map['arg'];
                if (array_key_exists($argKey, $urlArgs)) {
                    if ($override || !array_key_exists($argKey, $final)) {
                        $final[$argKey] = $urlArgs[$argKey];
                    }
                }
            }
        }

        // Taxonomy merging
        if (isset($urlArgs['tax']) && is_array($urlArgs['tax']) && !empty($urlArgs['tax'])) {
            if (!isset($final['tax']) || !is_array($final['tax'])) {
                $final['tax'] = [];
            }
            foreach ($urlArgs['tax'] as $tax => $terms) {
                $terms = is_array($terms) ? $terms : [$terms];
                if (!isset($final['tax'][$tax])) {
                    $final['tax'][$tax] = [];
                }
                $final['tax'][$tax] = array_values(array_unique(array_merge($final['tax'][$tax], $terms)));
            }
        }

        return $final;
    }

    private static function readParam(array $source, string $name): mixed
    {
        // Support optional namespacing: 'rex_scope' alongside 'scope'.
        if (array_key_exists($name, $source)) {
            return $source[$name];
        }
        $ns = 'rex_' . $name;
        return $source[$ns] ?? null;
    }

    private static function applySanitizer(mixed $sanitizer, mixed $value): mixed
    {
        if (is_callable($sanitizer)) {
            return call_user_func($sanitizer, $value);
        }
        // Fallback: pass-through scalar or array
        return $value;
    }

    private static function isEmptyValue(mixed $v): bool
    {
        if ($v === null) {
            return true;
        }
        if (is_string($v)) {
            return $v === '';
        }
        if (is_array($v)) {
            return count($v) === 0;
        }
        return false;
    }
}
