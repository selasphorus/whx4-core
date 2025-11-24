<?php

declare(strict_types=1);

namespace atc\WXC\Query;

use atc\WXC\Utils\Text;

final class QueryHelpers
{
    /**
     * Normalize root relation to 'AND' or 'OR' (anything else => 'AND').
     *
     * @param 'AND'|'OR'|string $relation
     * @return 'AND'|'OR'
     */
    public static function normalizeRelation(string $relation): string
    {
        $r = strtoupper(trim($relation));
        return $r === 'OR' ? 'OR' : 'AND';
    }
    /*
    public static function normalizeRelation(?string $relation, array $allowed = ['AND','OR'], string $default = 'AND'): string
    {
        $token = strtoupper(trim((string)$relation));
        return in_array($token, $allowed, true) ? $token : $default;
    }
    */

    /**
     * Require the presence of specific keys in the spec (null allowed).
     *
     * @param array<string,mixed> $spec
     * @param list<string>        $fields
     */
    public static function requireFields(array $spec, array $fields): bool
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $spec)) {
                return false;
            }
        }
        return true;
    }

    /** Scalar→list, array→list, null→[]; preserves order */
    public static function toList($value): array
    {
        if ($value === null) return [];
        return is_array($value) ? array_values($value) : [$value];
    }

    /** Drop empties if desired and reindex */
    public static function sanitizeList(array $values, bool $dropEmpty = true): array
    {
        if ($dropEmpty) {
            $values = array_filter($values, static function($v) {
                return $v !== null && $v !== '';
            });
        }
        return array_values($values);
    }
}
