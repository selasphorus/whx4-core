<?php

declare(strict_types=1);

namespace atc\WXC\Query;

use WP_Query;
use atc\WXC\App;
use atc\WXC\Utils\DateHelper;
use atc\WXC\Query\QueryHelpers;
use atc\WXC\Query\MetaQueryBuilder;
use atc\WXC\Query\TaxQueryBuilder;
//use atc\WXC\Contracts\QueryContributor;
//use atc\WXC\Query\ScopedDateResolver;
use atc\WXC\Http\UrlParamBridge;

final class PostQuery
{
    /**
     * @param array<string,mixed> $params
     *   Expected keys (examples): post_type, scope, posts_per_page, paged,
     *   date_start, date_end, tax, meta, orderby, order.
     *
     * @param array{
     *   post_type?:string,
     *   post_status?:string,
     *   paged?:int,
     *   posts_per_page?:int,
     *   order?:'ASC'|'DESC'|string,
     *   orderby?:string,
     *   meta_key?:string|null,
     *   // date scope
     *   scope?:string|array|null,
     *   date_meta?:array{
     *     key?:string,
     *     start_key?:string,
     *     end_key?:string,
     *     meta_type?:'DATE'|'DATETIME'|'NUMERIC'|string
     *   },
     *   // tax filters
     *   tax?:array<string,array<int,string>>
     * } $params
     *
     * @return array{ posts: \WP_Post[], found: int, max_pages: int, args: array }
     */
    // TODO: consider pros/cons of making this a static function
    public function find(array $params): array
    {
        error_log('[PostQuery::find] params: ' . print_r($params, true));
        // First, ensure normalized contract
        $p = $this->normalizeContract($params); //$p = self::normalizeContract($params);
		
        //error_log('[PostQuery::find] params (p) AFTER normalizeContract: ' . print_r($p, true));

        // Allow the active CPT handler to refine args -- ???
        $ptype = $p['post_type'];
        
        // 1) Resolve scope → date meta spec
        $scope = $p['scope'];
        $dateMeta = $p['date_meta'] ?? [];
        $dateBounds = self::resolveScope($p['scope'] ?? null, $dateMeta['meta_type'] ?? null);
        $dateMetaSpec  = self::dateMetaSpecFromBounds($dateMeta, $dateBounds);
        error_log('[PostQuery::find] scope: ' . print_r($scope, true));
        error_log('[PostQuery::find] dateMeta: ' . print_r($dateMeta, true));
        error_log('[PostQuery::find] dateBounds: ' . print_r($dateBounds, true));
        error_log('[PostQuery::find] dateMetaSpec: ' . print_r($dateMetaSpec, true));
        // WIP...
        
        // 2) Build combined meta_query spec
        $metaSpec  = $p['meta'] ?? [];
        //error_log('[PostQuery::find] metaSpec BEFORE mergeSpecs: ' . print_r($metaSpec, true));
        
        $combinedMetaSpec  = MetaQueryBuilder::mergeSpecs([$dateMetaSpec, $metaSpec], 'AND'); // $combinedMetaSpec = MetaQueryBuilder::mergeSpecs([$dateMetaSpec, $p['meta']], 'AND');
        error_log('[PostQuery::find] combinedMetaSpec: ' . print_r($combinedMetaSpec, true));
        
        $metaQuery = $combinedMetaSpec ? MetaQueryBuilder::build($combinedMetaSpec) : []; // $metaQuery = MetaQueryBuilder::build($combinedMetaSpec);
        //error_log('[PostQuery::find] metaQuery: ' . print_r($metaQuery, true));
        /*if (!empty($p['meta'])) {
            $args['meta_query'] = MetaQueryBuilder::fromSpec($p['meta'])->toWp();
        }*/
		
		// 3) Build tax_query from either a simple map or a full spec
		$taxSpec = $p['tax'] ?? [];
		if ($taxSpec !== []) {
			if (!isset($taxSpec['clauses'])) {
				// Convert simple map: taxonomy => [terms]
				$clauses = [];
				foreach ($taxSpec as $taxonomy => $terms) {
					$clauses[] = [
						'taxonomy' => (string)$taxonomy,
						'terms'    => $terms,
						'field'    => 'slug',   // your map uses slugs; change to 'term_id' if you pass IDs
						'operator' => 'IN',
					];
				}
				$taxSpec = ['relation' => 'AND', 'clauses' => $clauses];
			}
		
			$taxQuery = TaxQueryBuilder::build($taxSpec);
			if (!empty($taxQuery)) {
				$args['tax_query'] = $taxQuery;
			}
		}

        // 4) Assemble basic WP_Query args		
        $args = [
            'post_type'      => $p['post_type'],
            'post_status'    => $p['post_status'],
            'nopaging'       => $p['nopaging'], //$nopaging,
            'paged'          => $p['paged'],
            'posts_per_page' => $p['limit'], //$posts_per_page,
            'order'          => $p['order'],
            'orderby'        => $p['orderby'],
            'no_found_rows'  => false,
        ];
        
        // 4a) meta_key/meta_type only when applicable
		if (
			($p['orderby'] === 'meta_value' || $p['orderby'] === 'meta_value_num')
			&& !empty($p['meta_key'])
		) {
			$args['meta_key'] = $p['meta_key'];
			if (!empty($p['date_meta']['meta_type'])) {
				$mt = strtoupper(trim((string)$p['date_meta']['meta_type']));
				if (in_array($mt, ['NUMERIC','BINARY','CHAR','DATE','DATETIME','DECIMAL','SIGNED','TIME','UNSIGNED'], true)) {
					$args['meta_type'] = $mt;
				}
			}
		}
		
		//
        if ($metaQuery !== []) {
            $args['meta_query'] = $metaQuery;
        }
        if (!empty($taxQuery)) { //if ($taxQuery !== []) {
            $args['tax_query'] = $taxQuery;
        }
        
        //error_log('[PostQuery::find] args BEFORE adjustQueryArgs: ' . json_encode($args, JSON_UNESCAPED_SLASHES));
        
        // 5) Allow the active CPT handler to refine args (AFTER base args are built)
        $handlerClass = App::ctx()->getActivePostTypes()[$ptype] ?? null;
        if ($handlerClass && is_a($handlerClass, QueryContributor::class, true)) {
            /** @var QueryContributor $contrib */
            $contrib = new $handlerClass();
            $args = $contrib->adjustQueryArgs($args, $p);
        }
        
        //error_log('[PostQuery::find] args AFTER adjustQueryArgs: ' . json_encode($args, JSON_UNESCAPED_SLASHES));

        // 6) Final site-level filters
        /**
         * Final, global escape hatch (site-level).
         * Filter name keeps your prefix and allows per-type specialization.
         */
        // WIP!!!
        $args = apply_filters('wxc_query_args', $args, $p);
        $args = apply_filters("wxc_query_args_{$ptype}", $args, $p);

        // 5) Run the query.
        $q = new WP_Query($args);

        // TODO: see PostTypeHandler find() method re alternate return format -- reconcile the two
        return [
            'posts'     => $q->posts ?: [],
            'found'     => (int)$q->found_posts,
            'max_pages' => (int)$q->max_num_pages,
            'args'      => $args,
            'scope'      => $scope, // wip
            'query_request'=> $q->request,
        ];
    }

    /**
     * Normalize loose $params into a canonical PostQuery contract (spec only).
     * - Validates post type against active types (falls back to 'post').
     * - Coerces paging/limit and ordering.
     * - Normalizes scope (string | {start,end} | null).
     * - Normalizes date_meta (pass-through keys only).
     * - Normalizes user meta spec (MetaQueryBuilder format) to a safe default.
     * - Normalizes tax map (taxonomy => [slugs]).
     *
     * @param array{
     *   post_type?:string,
     *   post_status?:string,
     *   paged?:int|string,
     *   limit?:int|string,
     *   order?:string,
     *   orderby?:string,
     *   meta_key?:string|null,
     *   scope?:string|array|null,
     *   date_meta?:array{key?:string,start_key?:string,end_key?:string,meta_type?:string},
     *   meta?:array,
     *   tax?:array<string,mixed>
     * } $params
     * @return array{
     *   post_type:string,
     *   post_status:string,
     *   paged:int,
     *   limit:int,
     *   order:string,
     *   orderby:string,
     *   meta_key:?string,
     *   scope:string|array|null,
     *   date_meta:array{key?:string,start_key?:string,end_key?:string,meta_type?:string},
     *   meta:array,
     *   tax:array<string,array<int,string>>
     * }
     */
    private function normalizeContract(array $params): array
    {
        // 1) Post type must be active/enabled
        $ptype = isset($params['post_type']) ? (string)$params['post_type'] : 'post';
        $enabled = array_keys(App::ctx()->getActivePostTypes());
        if (!in_array($ptype, $enabled, true)) {
            // Fallback to 'post' (or throw) — your call:
            $ptype = 'post';
        }

        // 2) Paging and limit (aka 'posts_per_page')
        $paged = isset($params['paged']) ? max(1, (int)$params['paged']) : 1;

        // Normalize pagination aliases & edge-cases once.
        //$params = QueryHelpers::normalizePagination($params);
        
        // Prefer 'limit'; allow 'posts_per_page' and 'per_page' as aliases if 'limit' not provided.
        if (isset($params['limit'])) {
			$limit = (int) $params['limit'];
		} elseif (isset($params['posts_per_page'])) {
			$limit = (int) $params['posts_per_page'];
		} elseif (isset($params['per_page'])) {
			$limit = (int) $params['per_page'];
		} elseif (empty($params['nopaging'])) {
		    // If caller didn't set pagination, apply (filterable) defaults.
			$limit = (int) get_option('posts_per_page', 10);
			// TODO/Optional: add filters for overriding this default, e.g.:
			//$limit = (int) apply_filters('wxc_query_default_posts_per_page', $limit, $params);
			//if ($ptype) { $limit = (int) apply_filters("wxc_{$ptype}_query_default_posts_per_page", $limit, $params); }
			//$limit = 10;
		}
		
		// Support "-1" (all) and/or explicit nopaging
		$nopaging = !empty($params['nopaging']) || $limit === -1;
		if ($nopaging) {
			$limit = -1;
			$paged = 0;
		} else {
			$limit = max(1, $limit);
		}

        // 3) Ordering
        $orderRaw = (string)($params['order'] ?? 'DESC');
        $order = strtoupper(trim($orderRaw));
        $order = in_array($order, ['ASC','DESC'], true) ? $order : 'DESC';
        $orderby = (string)($params['orderby'] ?? 'date'); // leave flexible for WP-supported values

        // 4) Status
        $postStatus = (string)($params['post_status'] ?? 'publish');

        // 5) meta_key hint (only meaningful when orderby=meta_value or meta_value_num)
        $metaKey = isset($params['meta_key']) && $params['meta_key'] !== '' ? (string)$params['meta_key'] : null;

        // 6) Scope normalization (string | {start,end} | null)
        // Accept string ("today", "this_week", ...) or array {start?, end?} or null
        $scope = $params['scope'] ?? null;
        if (is_string($scope)) {
            $scope = trim($scope) !== '' ? $scope : null;
        } elseif (is_array($scope)) {
            $s = $scope['start'] ?? null;
            $e = $scope['end'] ?? null;
            $scope = ($s !== null || $e !== null) ? ['start' => $s, 'end' => $e] : null;
        } else {
            $scope = null;
        }

        // 7) Date meta mapping (pass-through keys only; builders will validate further)
        $dateMetaIn = is_array($params['date_meta'] ?? null) ? $params['date_meta'] : [];
        $dateMeta = [];
        if (isset($dateMetaIn['key'])) {
            $dateMeta['key'] = (string)$dateMetaIn['key'];
        }
        if (isset($dateMetaIn['start_key'])) {
            $dateMeta['start_key'] = (string)$dateMetaIn['start_key'];
        }
        if (isset($dateMetaIn['end_key'])) {
            $dateMeta['end_key'] = (string)$dateMetaIn['end_key'];
        }
        if (isset($dateMetaIn['key_type'])) {
            $dateMeta['key_type'] = (string)$dateMetaIn['key_type'];
        }
        if (isset($dateMetaIn['meta_type'])) {
            $dateMeta['meta_type'] = (string)$dateMetaIn['meta_type'];
        }
        if (isset($dateMetaIn['numeric_years'])) {
            $dateMeta['numeric_years'] = (bool)$dateMetaIn['numeric_years'];
        }
        if (isset($dateMetaIn['end_optional'])) {
            $dateMeta['end_optional'] = (bool)$dateMetaIn['end_optional'];
        }

        // 8) User-provided meta spec (accept full spec OR shorthand)
		$metaSpecIn = $params['meta'] ?? [];
		$metaSpec = [];
		
		if (is_array($metaSpecIn)) {
			$hasClauses = isset($metaSpecIn['clauses']) && is_array($metaSpecIn['clauses']);
		
			if ($hasClauses) {
				// Already a MetaQueryBuilder spec
				$metaSpec = $metaSpecIn;
			} elseif ($metaSpecIn !== []) {
				// Shorthand → wrap as a standard spec
				$isAssoc = static function(array $a): bool {
					return array_keys($a) !== range(0, count($a) - 1);
				};
		
				$clauses = $isAssoc($metaSpecIn) ? [$metaSpecIn] : $metaSpecIn;
				$metaSpec = [
					'relation' => 'AND',
					'clauses'  => $clauses,
				];
			}
		}
		
        // 9) Taxonomy map: ensure "taxonomy => [slugs...]" (trimmed, non-empty) // ensure "taxonomy => [terms...]" (slugs by default)
        // check if isset($params['tax'])?
        $taxIn = is_array($params['tax'] ?? null) ? $params['tax'] : [];
        $taxOut = [];
        foreach ($taxIn as $taxonomy => $terms) {
            $list = is_array($terms) ? $terms : [$terms];
            /*$list = array_values(array_filter(array_map(
                static fn($t) => is_string($t) ? trim($t) : '',
                $list
            ), static fn($t) => $t !== ''));*/
            $list = array_values(array_filter(array_map(
			    static fn($t) => is_scalar($t) ? trim((string)$t) : '',
			    $list
			), static fn($t) => $t !== ''));
            if ($list !== []) {
                $taxOut[(string)$taxonomy] = $list;
            }
        }

        $args = [
            'post_type'   => $ptype,
            'post_status' => $postStatus,
            'paged'       => $paged,
            'limit'       => $limit,
            'posts_per_page' => $limit,   // mirror for downstream paths that expect WP-style arg
            'nopaging'    => $nopaging,
            'order'       => $order,
            'orderby'     => $orderby,
            'meta_key'    => $metaKey,
            'scope'       => $scope,
            'date_meta'   => $dateMeta,
            'meta'        => $metaSpec,
            'tax'         => $taxOut,
        ];

        return $args;
    }
    
    /*private function normalizeTaxSpec(array $tax): array
	{
		if ($tax === [] || isset($tax['clauses'])) {
			// Already a full spec or empty
			return $tax;
		}
	
		// Convert simple map: ['document_category' => ['tax_forms','paystubs']]
		$clauses = [];
		foreach ($tax as $taxonomy => $terms) {
			if (!is_array($terms)) { $terms = [$terms]; }
			$terms = array_values(array_filter(array_map('strval', $terms), static fn($t) => $t !== ''));
			if ($terms === []) { continue; }
			$clauses[] = [
				'taxonomy' => (string)$taxonomy,
				'terms'    => $terms,
				'field'    => 'slug',   // your map uses slugs
				'operator' => 'IN',
			];
		}
	
		return $clauses ? ['relation' => 'AND', 'clauses' => $clauses] : [];
	}*/


    /**
     * Resolve a scope (string or {start,end}) into concrete date bounds.
     *
     * @param string|array|null $scopeSpec Named scope (e.g., 'today','this_week') or ['start'=>..,'end'=>..] or null
     * @param string|null $castHint Optional cast hint: 'DATE'|'DATETIME'|'NUMERIC' (only DATE vs DATETIME matters here)
     * @return array{start:mixed,end:mixed}|null
     */
    private static function resolveScope($scopeSpec, ?string $castHint): ?array
    {
        if ($scopeSpec === null || $scopeSpec === '' || $scopeSpec === []) {
            return null;
        }

        // Prefer DATE windowing when explicitly hinted; otherwise default to DATETIME.
        //$mode = (is_string($castHint) && strtoupper(trim($castHint)) === 'DATE') ? 'DATE' : 'DATETIME';
        // Treat NUMERIC like DATE for windowing (year-based comparisons).
        $hint = is_string($castHint) ? strtoupper(trim($castHint)) : null;
        $mode = ($hint === 'DATE' || $hint === 'NUMERIC') ? 'DATE' : 'DATETIME';

        try {
            /** @var array{start:mixed,end:mixed} $bounds */
            $bounds = ScopedDateResolver::resolve($scopeSpec, ['mode'=>$mode]);
            return $bounds;
        } catch (\Throwable $e) {
            return null;
        }
    }
        /*
        $range = ScopedDateResolver::resolve($scope, [
            'mode' => 'DATE',              // or 'DATETIME' when you need time precision
            'year' => $year ?? null,       // if relevant (e.g., scope 'month')
            'month' => $month ?? null,     // if relevant
            // 'tz' => $tz,                // optional override
        ]);
        // $range['start'], $range['end'] are DateTimeImmutable|null*/

    /**
	 * Build the meta spec for a resolved scope window and a date_meta config.
	 *
	 * Accepted mappings, e.g.:
	 * - ['key' => 'transaction_date', 'meta_type' => 'DATE'] + scope → range
	 * - ['start_key' => 'start_date', 'end_key' => 'end_date', 'meta_type' => 'DATETIME'] + scope → overlapRange
	 *
	 * @param array{
	 *    key?:string,
	 *    start_key?:string,
	 *    end_key?:string,
	 *    meta_type?:'DATE'|'DATETIME'|'NUMERIC',
	 *    key_type?:'single'|'rows'|'serialized'
	 *    cast?:string -- ???
	 * } $dateMeta
	 * @param array{start:?string,end:?string}|null $dateBounds
	 * @return array{relation?:'AND'|'OR',clauses?:array<int,array<string,mixed>>}
	 */
	private static function dateMetaSpecFromBounds(array $dateMeta, ?array $dateBounds): array
	{
		if (defined('WXC_DEBUG') && WXC_DEBUG) {
		    error_log('[PostQuery::dateMetaSpecFromBounds] dateMeta: ' . print_r($dateMeta, true));
		    //error_log('[PostQuery::dateMetaSpecFromBounds] dateBounds: ' . print_r($dateBounds, true));
		}
		
		// No scope -> no date filtering requested
		if ( empty($dateBounds) || ($dateBounds['start'] ?? null) === null && ($dateBounds['end'] ?? null) === null) {
			return ['relation' => 'AND', 'clauses' => []];
		}
		
		// Normalize
		$metaType = isset($dateMeta['meta_type']) ? strtoupper((string)$dateMeta['meta_type']) : 'DATE';
		$keyType  = isset($dateMeta['key_type']) ? strtolower((string)$dateMeta['key_type']) : 'single';
		$numericYears  = isset($dateMeta['numeric_years']) ? $dateMeta['numeric_years'] : null;
		//
		$key      = isset($dateMeta['key']) ? (string)$dateMeta['key'] : null;
		$startKey = isset($dateMeta['start_key']) ? (string)$dateMeta['start_key'] : null;
		$endKey   = isset($dateMeta['end_key']) ? (string)$dateMeta['end_key'] : null;
		//error_log('[PostQuery::dateMetaSpecFromBounds] dateMeta[key] keyType: ' . $keyType);
		
		// NUMERIC (year-based) storage (single/rows/serialized)
		// Treat scope bounds as a years window and delegate to MetaQueryBuilder.
		
		// NUMERIC meta_type can mean either:
		// (A) years-only storage  → numeric_years=true (delegates to yearsWindow helper), OR
		// (B) ACF date_picker (Ymd) → numeric_years=false|unset (handled below as a normal range with cast=NUMERIC).
		if ($metaType === 'NUMERIC' && !empty($numericYears)) {
		    error_log('[PostQuery::dateMetaSpecFromBounds] numericYears is TRUE.');
		    // Expect a single meta key that stores a year (single/rows/serialized)
		    if (!is_string($key) || $key === '') {
		        // No usable key → noop
		        return ['relation' => 'AND', 'clauses' => []];
		    }
		    // Convert bounds to years window and build clauses accordingly
		    $window = DateHelper::yearsWindow($dateBounds);
		    return MetaQueryBuilder::fromYearsWindow($key, $keyType, $window, 'NUMERIC');
		} else if ($metaType === 'NUMERIC') {
		    error_log('[PostQuery::dateMetaSpecFromBounds] metaType is NUMERIC => need to format for ACF: ' . print_r($dateBounds, true));
		}
	
		// Single point-in-time meta (e.g., event_date, transaction_date)
		if (is_string($key) && $key !== '' && !$startKey && !$endKey) { //if (!empty($key)) {
		    error_log('[PostQuery::dateMetaSpecFromBounds] Single point-in-time meta with key: ' . $key);
			// Build a BETWEEN (date or datetime) using $bounds['start']..$bounds['end']
			return [
				'relation' => 'AND',
				'clauses'  => [[
					'type' => 'range',
					'key'  => $key,
					'min'  => $dateBounds['start'],
					'max'  => $dateBounds['end'],
					'cast' => $metaType, // 'DATE' or 'DATETIME' or 'NUMERIC' (for ACF yyyymmdd format)
				]],
			];
		}
	
		// Span storage (e.g., events with start_key/end_key) -- build overlap over start/end keys.
		if (!empty($startKey) && !empty($endKey)) { //if (is_string($startKey) && $startKey !== '' && is_string($endKey) && $endKey !== '') {
		    error_log('[PostQuery::dateMetaSpecFromBounds] startKey: ' . $startKey . '; endKey: ' . $endKey);
			return [
				'relation' => 'AND',
				'clauses'  => [[
					'type'       => 'overlapRange',
					'start_key'  => $startKey,
					'end_key'    => $endKey,
					'start'      => $dateBounds['start'],
					'end'        => $dateBounds['end'],
					'cast' => $metaType, //'meta_type'  => $metaType,
					'end_optional' => !empty($dateMeta['end_optional']),
				]],
			];
		}
	
		// Fallback: nothing to build
		return ['relation' => 'AND', 'clauses' => []];
	}

    /**
     * Translate a simple "taxonomy => [terms]" map into WP tax_query.
     * If you have a TaxQueryBuilder, swap this body with TaxQueryBuilder::build().
     *
     * @param array<string,array<int,string>> $taxMap
     * @return array
     */
    private static function buildTaxQuery(array $taxMap): array
    {
        if ($taxMap === []) {
            return [];
        }

        // If TaxQueryBuilder exists in your tree, prefer it:
        // return TaxQueryBuilder::build(['relation'=>'AND','clauses'=>...]);

        $out = ['relation' => 'AND'];
        foreach ($taxMap as $taxonomy => $terms) {
            $terms = array_values(array_filter($terms, static function($t) {
                return $t !== null && $t !== '';
            }));
            if ($terms === []) {
                continue;
            }
            $out[] = [
                'taxonomy' => (string)$taxonomy,
                'field'    => 'slug',
                'terms'    => $terms,
                'operator' => 'IN',
            ];
        }

        return count($out) > 1 ? $out : [];
    }

    public static function fromRequest(string $targetHandlerClass, array $baseArgs, ?array $only = null, ?array $source = null): self
    {
        $source = $source ?? $_GET;
        $urlArgs = UrlParamBridge::fromSource($targetHandlerClass, $source, $only);
        $args = UrlParamBridge::merge($targetHandlerClass, $baseArgs, $urlArgs);
        return new self($args);
    }

}
