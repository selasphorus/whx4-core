<?php

declare(strict_types=1);

namespace atc\WXC\Query;

use atc\WXC\Query\QueryHelpers;

final class TaxQueryBuilder
{
    public static function build(array $spec): array
    {
        $relation = QueryHelpers::normalizeRelation($spec['relation'] ?? 'AND');
        $clauses = $spec['clauses'] ?? [];
        $out = [];

        foreach ($clauses as $c) {
            $built = self::makeClause($c);
            if ($built) $out[] = $built;
        }

        return $out ? array_merge(['relation' => $relation], $out) : [];
    }

    private static function makeClause(array $c): ?array
    {
        if (!QueryHelpers::requireFields($c, ['taxonomy', 'terms'])) return null;

        $terms = QueryHelpers::sanitizeList(QueryHelpers::toList($c['terms']));
        if ($terms === []) return null;

        return [
            'taxonomy'         => (string)$c['taxonomy'],
            'field'            => self::normalizeTaxField($c['field'] ?? 'slug'),
            'terms'            => $terms,
            'operator'         => self::normalizeTaxOperator($c['operator'] ?? 'IN'),
            'include_children' => isset($c['include_children']) ? (bool)$c['include_children'] : true,
        ];
    }

    private static function normalizeTaxField(string $field): string
    {
        $token = strtoupper(trim($field));
        $map = [
            'TERM_ID'          => 'term_id',
            'SLUG'             => 'slug',
            'NAME'             => 'name',
            'TERM_TAXONOMY_ID' => 'term_taxonomy_id',
        ];
        return $map[$token] ?? 'slug';
    }

    private static function normalizeTaxOperator(string $operator): string
    {
        $token = strtoupper(trim($operator));
        return in_array($token, ['IN','NOT IN','AND','EXISTS','NOT EXISTS'], true) ? $token : 'IN';
    }

}
