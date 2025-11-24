<?php

declare(strict_types=1);

namespace atc\WXC\Query;

use atc\WXC\Query\QueryHelpers;
use atc\WXC\Utils\DateHelper;

/**
 * MetaQueryBuilder
 *
 * Purpose (v1): Convert a normalized, framework-agnostic "meta spec" into a
 * WordPress-ready `meta_query` array. This class is intentionally stateless
 * and performs no WordPress calls—only array assembly.
 *
 * Scope: Simple flat groups only (root relation AND/OR). No nested groups
 * except for the special overlapRange helper, which returns its own grouped
 * subclauses (AND).
 *
 * Responsibility boundaries:
 * - Casting decisions (DATE vs DATETIME vs NUMERIC) are *provided* by callers.
 * - Value normalization (e.g., inclusive end date) is decided upstream (e.g., ScopedDateResolver).
 * - We only assemble arrays in the shape WP_Query expects.
 */
final class MetaQueryBuilder
{
    /**
     * Public entry point: build the `meta_query` array from a normalized spec.
     *
     * Spec format (MVP):
     * [
     *   'relation' => 'AND'|'OR',              // optional, defaults to 'AND'
     *   'clauses'  => [
     *     [ 'type' => 'equals'|'in'|'like'|'gte'|'lte'|'range'|'exists'|'notExists'|'overlapRange'|'custom', ... ],
     *     ...
     *   ]
     * ]
     *
     * @param array{relation?:'AND'|'OR',clauses?:list<array<string,mixed>>} $spec
     * @return array WordPress-ready meta_query array (or empty array if nothing valid).
     */
    public static function build(array $spec): array
    {
        error_log( '=== MetaQueryBuilder::build() ===' );
        error_log( 'spec: ' . print_r($spec, true) );

        $relation = QueryHelpers::normalizeRelation($spec['relation'] ?? 'AND');
        $clauses  = $spec['clauses'] ?? [];

        $built = [];
        foreach ($clauses as $clauseSpec) {
            error_log( 'clauseSpec: ' . print_r($clauseSpec, true) );
            $clause = self::makeClause($clauseSpec);
            if ($clause === null) {
                continue; // Skip invalid/unknown clauses silently (debug logging belongs elsewhere).
            }
            // overlapRange yields a grouped payload under __group
            $built[] = $clause['__group'] ?? $clause;
        }

        if ($built === []) {
            return [];
        }

        // WP expects: ['relation' => 'AND'|'OR', 0 => clause, 1 => clause, ...]
        return array_merge(['relation' => $relation], $built);
    }

    /**
     * Route a spec to the appropriate builder.
     *
     * @param array<string,mixed> $spec
     * @return array<string,mixed>|null Null when missing required fields or unknown type.
     */
    private static function makeClause(array $spec): ?array
    {
        error_log( '=== MetaQueryBuilder::makeClause() ===' );
        
        // TEMP debug
		if (!isset($spec['key'])) {
			error_log('[MetaQB::makeClause] missing key: ' . print_r($spec, true));
			return null;
		}
	
		// Handle shorthand: equals
		if (array_key_exists('equals', $spec)) {
			$value = $spec['equals'];
			if ($value === '' || $value === null) {
				error_log('[MetaQB::makeClause] equals empty for key=' . $spec['key']);
				return null;
			}
			$clause = [
				'key'     => (string)$spec['key'],
				'value'   => $value,
				'compare' => '=',
			];
			/*if (!empty($spec['cast'])) {
				$clause['type'] = QueryHelpers::normalizeCast($spec['cast']); // if you have this
			}*/
			return $clause;
		}
    
    
        $clauseType = isset($spec['type']) ? (string)$spec['type'] : '';
        /*if ( isset($spec['meta_type']) ) {
            $metaType = self::normalizeMetaType($spec['meta_type']);
            error_log( '[MetaQB::makeClause] spec[meta_type]: ' . $spec['meta_type'] );
        } else {
            $metaType = "";
        }*/
        $metaType = self::normalizeMetaType($spec['meta_type'] ?? $spec['cast'] ?? null);
        
        error_log( '[MetaQB::makeClause] metaType: ' . $metaType );

        //error_log( 'spec: ' . print_r($spec, true) );
        //error_log( 'clauseType: ' . $clauseType . '; metaType: ' . $metaType );

        switch ($clauseType) {
            case 'equals':
                // key = value
                return self::makeSimpleComparison($spec, '=', $metaType);

            case 'gte':
                // key >= value
                return self::makeSimpleComparison($spec, '>=', $metaType);

            case 'lte':
                // key <= value
                return self::makeSimpleComparison($spec, '<=', $metaType);

            case 'in':
                // key IN (values[])
                if (!QueryHelpers::requireFields($spec, ['key', 'value']) || !is_array($spec['value']) || $spec['value'] === []) {
                    return null;
                }
                return self::assembleClause(
                    (string)$spec['key'],
                    'IN',
                    array_map(static fn($v) => self::formatValue($v, $metaType), array_values($spec['value'])), // Each element in the array may be DateTimeInterface.
                    $metaType
                );

            case 'like':
                // key LIKE %value%
                if (!QueryHelpers::requireFields($spec, ['key']) || !array_key_exists('value', $spec)) {
                    return null;
                }
                $val = self::formatValue($spec['value'], $metaType);
                return self::assembleClause(
                    (string)$spec['key'],
                    'LIKE',
                    '%'.(string)$val.'%',
                    $metaType
                );

            case 'range':
                // key BETWEEN min AND max (inclusive)
                if (!QueryHelpers::requireFields($spec, ['key']) || !array_key_exists('min', $spec) || !array_key_exists('max', $spec)) {
                    return null;
                }
                error_log('[MQB::makeClause] spec[min]: ' . $spec['min']);
                $min = self::formatValue($spec['min'], $metaType);
                $max = self::formatValue($spec['max'], $metaType);
                error_log('[MQB::makeClause] formatted min: ' . $min);
                return self::assembleClause(
                    (string)$spec['key'],
                    'BETWEEN',
                    [$min, $max],
                    $metaType
                );
            
            case 'regex':
                $key = isset($spec['key']) ? (string)$spec['key'] : '';
                if ($key === '') {
                    return [];
                }
                return [
                    'key'     => $key,
                    'compare' => 'REGEXP',
                    'value'   => (string)($spec['value'] ?? ''),
                ];

            case 'containsSerialized':
				// expects: key, values (array of scalars)
				//if (!self::has($spec, ['key', 'values']) || !is_array($spec['values']) || $spec['values'] === []) {
				if (!QueryHelpers::requireFields($spec, ['key', 'values']) || !is_array($spec['values']) || $spec['values'] === []) {
					return null;
				}
				$group = ['relation' => 'OR'];
				foreach ($spec['values'] as $val) {
					$group[] = self::assembleClause((string)$spec['key'], 'LIKE', '"' . (string)$val . '"');
				}
				return ['__group' => $group];

            case 'exists':
                // EXISTS (no value/type)
                if (!QueryHelpers::requireFields($spec, ['key'])) {
                    return null;
                }
                return self::assembleClause((string)$spec['key'], 'EXISTS');

            case 'notExists':
                // NOT EXISTS (no value/type)
                if (!QueryHelpers::requireFields($spec, ['key'])) {
                    return null;
                }
                return self::assembleClause((string)$spec['key'], 'NOT EXISTS');

            case 'overlapRange':
                // (start_key <= end) AND (end_key >= start) [+ optionally allow missing end_key]
                $endOptional  = !empty($spec['end_optional']);
                return self::makeOverlapGroup($spec, $metaType, $endOptional);

            case 'custom':
                // Raw WP meta_query clause passthrough
                $raw = $spec['raw'] ?? null;
                return is_array($raw) && $raw !== [] ? $raw : null;

            default:
                return null; // Unknown type for v1
        }
    }

    /**
     * Build a simple comparison (equals/gte/lte): key {op} value.
     *
     * @param array{key?:string,value?:mixed,cast?:string} $spec
     */
    private static function makeSimpleComparison(array $spec, string $op, ?string $metaType): ?array
    {
        if (!QueryHelpers::requireFields($spec, ['key']) || !array_key_exists('value', $spec)) {
            return null;
        }
        
        return self::assembleClause(
            (string)$spec['key'],
            $op,
            self::formatValue($spec['value'], $metaType), // value might be a DateTimeInterface. Pre-format it based on cast.
            $metaType
        );
    }

    /**
     * Build the overlapRange group:
     * AND[
     *   start_key <= end (type applied if provided),
     *   end_key   >= start (type applied if provided),
     *   start_key EXISTS,
     *   end_key   EXISTS
     * ]
     *
     * @param array{start_key?:string,end_key?:string,start?:mixed,end?:mixed,cast?:string} $spec
     * @return array{__group:array<string,mixed>}|null
     */
    private static function makeOverlapGroup(array $spec, ?string $metaType, bool $endOptional=false): ?array
    {
        error_log( '=== MetaQueryBuilder::makeOverlapGroup() ===' );

        if (!QueryHelpers::requireFields($spec, ['start_key', 'end_key', 'start', 'end'])) {
            return null;
        }

        $group = ['relation' => 'AND'];

        //error_log( 'spec[end]: ' . print_r($spec['end'], true) );
        //error_log( 'spec[start]: ' . print_r($spec['start'], true) );

        $endValue   = self::formatValue($spec['end'], $metaType);
        $startValue = self::formatValue($spec['start'], $metaType);

        //error_log( 'metaType: ' . print_r($metaType, true) );
        //error_log( 'endValue: ' . print_r($endValue, true) );
        //error_log( 'startValue: ' . print_r($startValue, true) );

        // start_key <= end
        $group[] = self::assembleClause((string)$spec['start_key'], '<=', $endValue, $metaType);

        // end_key >= start (or allow missing end_key when enabled)
        $endCond = self::assembleClause((string)$spec['end_key'], '>=', $startValue, $metaType);

        if ($endOptional) {
            $group[] = array_merge(['relation' => 'OR'], [
                $endCond,
                self::assembleClause((string)$spec['end_key'], 'NOT EXISTS'),
            ]);
        } else {
            $group[] = $endCond;
        }
        //error_log( 'group: ' . print_r($group, true) );
        return ['__group' => $group];
    }

    /**
     * Assemble a single WP meta_query clause.
     *
     * IMPORTANT naming:
     * - $metaType is the **WP meta comparison type** (a cast): 'DATE'|'DATETIME'|'NUMERIC'|'CHAR'|'BINARY'|'DECIMAL'|'SIGNED'|'UNSIGNED'.
     *   This is DIFFERENT from the spec's clause discriminator (e.g. 'equals','in', etc.).
     *
     * Rules:
     * - Only include 'value' when the compare operator needs it (not for EXISTS/NOT EXISTS).
     * - Only include 'type' when a value is present AND the meta type is valid.
     *
     * @param mixed $value
     * @return array<string,mixed>
     */
    private static function assembleClause(string $key, string $compare, $value = null, ?string $metaType = null): array
    {
        $clause = [
            'key'     => $key,
            'compare' => $compare,
        ];
        /*
        // WIP
		$mt = self::normalizeMetaType($spec['meta_type'] ?? $spec['cast'] ?? null);
		$clause = [
			'key'     => (string)$spec['key'],
			'value'   => self::formatValue($value, $mt),
			'compare' => '=',
		];
		if ($mt !== '') {
		$clause['type'] = $mt;
		
		//if (in_array($metaType, ['NUMERIC','DATE','DATETIME'], true)) { $clause['type'] = $metaType; }
		}*/


        $needsValue = !in_array($compare, ['EXISTS', 'NOT EXISTS'], true);
        if ($needsValue) {
            $clause['value'] = $value;

            $normalized = self::normalizeMetaType($metaType);
            if ($normalized !== null) {
                $clause['type'] = $normalized;
            }
        }

        return $clause;
    }

    /**
     * Normalize/validate a WP meta type token.
     * Returns the UPPERCASE allowed token, or null if invalid/empty. 
     *
     * Allowed: NUMERIC, CHAR, BINARY, DATE, DATETIME, DECIMAL, SIGNED, UNSIGNED
     * Common aliases mapped:
     *   int|integer|number  -> NUMERIC
     *   string|text         -> CHAR
     *
     * @param array<string,mixed> $spec
     */
    private static function normalizeMetaType(?string $metaType): ?string
    {
        if ($metaType === null || $metaType === '') {
            return null;
        }

        $token = strtoupper(trim($metaType));

        // Alias map for friendlier inputs
        $aliases = [
            'INT' => 'NUMERIC',
            'INTEGER' => 'NUMERIC',
            'NUMBER' => 'NUMERIC',
            'FLOAT' => 'DECIMAL', // WP supports DECIMAL; map float to DECIMAL
            'DOUBLE' => 'DECIMAL',
            'STRING' => 'CHAR',
            'TEXT' => 'CHAR',
        ];
        if (isset($aliases[$token])) {
            $token = $aliases[$token];
        }

        // Allow-list from WP docs
        $allowed = ['NUMERIC','CHAR','BINARY','DATE','DATETIME','DECIMAL','SIGNED','UNSIGNED'];

        return in_array($token, $allowed, true) ? $token : null;
    }

    // Format values for use in WP_Query
    private static function formatValue($value, ?string $metaType)
	{
		error_log('[MQB::formatValue] value: ' . $value . '; metaType: ' . $metaType);
		return self::normalizeValueForMetaType($value, $metaType);
	}
	
	/**
	 * Normalize a scalar or array value into the correct storage string(s) for a WP meta_query,
	 * based on $metaType: 'NUMERIC' (ACF date_picker → Ymd), 'DATE', or 'DATETIME' (default).
	 * Moved FROM DateHelper class with the thought that other non-date metatypes might eventually become relevant
	 *
	 * @param mixed $value
	 * @param ?string $metaType
	 * @return mixed
	 */
	private static function normalizeValueForMetaType(mixed $value, ?string $metaType): mixed
	{
	    error_log('[MQB::normalizeValueForMetaType] value: ' . $value . '; metaType: ' . $metaType);
	    $type = is_string($metaType) ? strtoupper(trim($metaType)) : null;
	    
	    if (is_array($value)) {
	        return array_map(static fn($v) => self::normalizeValueForMetaType($v, $metaType), $value);
	    }
	    
	    // Only normalize if DateTimeInterface or looks like a date/time string.
	    if ($value instanceof \DateTimeInterface || (is_string($value) && DateHelper::isDateLike($value))) {
	        if ($type === 'NUMERIC')    { return DateHelper::toYmd($value); }
	        if ($type === 'DATE')       { return DateHelper::toDate($value); }
	        /* default → DATETIME */      return DateHelper::toDateTime($value);
	    }
	    
	    // Non-date scalars (ints, floats, non-date strings) pass through unchanged.
	    return $value;
	}
    
    /**
	 * Build a meta_query spec for matching a year window against different storage styles.
	 *
	 * @param string $key       Meta key that stores the year(s).
	 * @param string $keyType   'single'|'rows'|'serialized'
	 * @param array{min:int,max:int,years:int[]} $win  From DateHelper::yearsWindow()
	 * @param string $metaType  Usually 'NUMERIC' (ignored for non-numeric comparisons)
	 * @return array            A normalized spec consumable by MetaQueryBuilder::build()
	 */
	public static function fromYearsWindow(string $key, string $keyType, array $win, string $metaType = 'NUMERIC'): array
	{
		error_log('[fromYearsWindow] key: ' . $key . '; keyType: ' . $keyType  . '; metaType: ' . $metaType . '');
		
		// Empty window → no-op spec
		if (empty($win['years'])) {
			return ['relation' => 'AND', 'clauses' => []];
		}
	
		$keyType = strtolower(trim($keyType));
	
		// 1) Single numeric year per post (e.g., years_active = 1950)
		if ($keyType === 'single') {
			return [
				'relation' => 'AND',
				'clauses'  => [[
					'type' => 'range',
					'key'  => $key,
					'min'  => (int)$win['min'],
					'max'  => (int)$win['max'],
					'cast' => strtoupper($metaType) === 'NUMERIC' ? 'NUMERIC' : null,
				]],
			];
		}
	
		// 2) Rows: one meta row per year (multiple entries for the same key)
		// Prefer a single IN over many OR equals clauses.
		if ($keyType === 'rows') {
			return [
				'relation' => 'AND',
				'clauses'  => [[
					'type'  => 'in',
					'key'   => $key,
					'value' => array_values(array_map('intval', $win['years'])),
					// cast not required for IN on numeric strings; WP will compare as strings.
					// Add 'cast' => 'NUMERIC' here only if your data is stored as pure ints and you need strictness.
				]],
			];
		}
	
		// 3) Serialized array (e.g., ACF checkbox) — match integer or exact string element tokens via LIKE on `"YYYY"`
		// Covers: s:<len>:"YYYY";  and  i:YYYY;
		if ($keyType === 'serialized') {
		    $alts = implode('|', array_map('intval', $win['years'])); // e.g. "1948|1949|1950"
		    $pattern = '(:\\"(' . $alts . ')\\";|i:(' . $alts . ');)'; // match s:"YYYY"; OR i:YYYY;
			//$clauses = [];
			/*foreach ($win['years'] as $y) {
			    $y = (int)$y;
			    // String token: s:<len>:"YYYY";  — we match the stable tail part `:"YYYY";`
			    $clauses[] = ['type' => 'like', 'key' => $key, 'value' => ':"' . $y . '";'];
			    // Integer token: i:YYYY;
			    $clauses[] = ['type' => 'like', 'key' => $key, 'value' => 'i:' . $y . ';'];
				// v1:
				$clauses[] = ['type'  => 'like', 'key'   => $key, 'value' => '"' . (int)$y . '"'];	
			}*/
			return [
			    'relation' => 'AND',
			    'clauses'  => [[
			        'type'  => 'regex',
			        'key'   => $key,
			        'value' => $pattern, // used with compare REGEXP
			    ]],
			];
		}
		
		// Fallback: treat unknown key_type like 'rows'
		return [
			'relation' => 'AND',
			'clauses'  => [[
				'type'  => 'in',
				'key'   => $key,
				'value' => array_values(array_map('intval', $win['years'])),
			]],
		];
	}
	

    /**
     * Merge multiple MetaQueryBuilder specs into a single flat spec.
     * v1 semantics: all clauses are flattened under a single **root relation** (default AND).
     * NOTE: Child spec relations (e.g., 'OR') are NOT preserved in v1 — if you need OR,
     * build that as a single spec before merging.
     *
     * @param array<int,array{relation?:'AND'|'OR',clauses?:array<int,array<string,mixed>>}> $specs
     * @param 'AND'|'OR' $relation Root relation for the merged spec (default 'AND').
     * @return array{relation:'AND'|'OR',clauses:array<int,array<string,mixed>>} | [] // normalized spec or []
     */
    public static function mergeSpecs(array $specs, string $relation = 'AND'): array
    {
        $rootRelation = strtoupper(trim($relation)) === 'OR' ? 'OR' : 'AND';

        $merged = ['relation' => $rootRelation, 'clauses' => []];

        foreach ($specs as $spec) {
            if (!is_array($spec) || empty($spec['clauses']) || !is_array($spec['clauses'])) {
                continue;
            }
            foreach ($spec['clauses'] as $clause) {
                $merged['clauses'][] = $clause;
            }
        }

        return $merged['clauses'] ? $merged : [];
    }

}
