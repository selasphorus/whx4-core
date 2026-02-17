<?php

declare(strict_types=1);

namespace atc\WXC\Traits;

use atc\WXC\Query\ScopedDateResolver;

trait AppliesScopeToMainQuery
{
    /**
     * Register the scope filter for main queries.
     * Call this from your post type's boot() method.
     */
    protected function registerScopeFilter(): void
    {
        add_action('pre_get_posts', [$this, 'applyScopeToMainQuery']);
    }
    
    /**
     * Apply scope parameter to the main query for this post type.
     *
     * @param \WP_Query $query
     */
    public function applyScopeToMainQuery(\WP_Query $query): void
    {
        // Only modify main query on frontend for this post type
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // Make sure we're handling the right post type
        if ($query->get('post_type') !== $this->getSlug()) {
			return;
		}
        
        // Check for scope query var
        $scope = get_query_var('scope');
        if (!$scope) {
            return;
        }
        
        // Get the date meta key from the implementing class
        $dateMetaKey = $this->getDateMetaKey();
        $metaType = $this->getDateMetaType();
        
        if (!$dateMetaKey) {
            return;
        }
        
        // Resolve scope to date bounds
        $mode = ($metaType === 'NUMERIC') ? 'DATE' : 'DATE'; // Both use DATE mode for windowing
        $bounds = ScopedDateResolver::resolve($scope, ['mode' => $mode]);
        
        if (empty($bounds['start']) || empty($bounds['end'])) {
            return;
        }
        
        //error_log('[WXC-applyScopeToMainQuery] postTypePath: ' . $postTypePath . '');
        error_log('[WXC] Scope: ' . $scope);
        error_log('-WXC- Scope: ' . $scope);
        
        // Build meta_query with OR logic for non-recurring vs recurring events
        $meta_query = $query->get('meta_query') ?: ['relation' => 'AND'];
        
        // Add meta_query for date filtering
        /*$meta_query = $query->get('meta_query') ?: [];
        $meta_query[] = [
            'key'     => $dateMetaKey,
            'value'   => [$bounds['start'], $bounds['end']],
            'compare' => 'BETWEEN',
            'type'    => $metaType,
        ];*/
        
        $meta_query[] = [
			'relation' => 'OR',
			[
				// Non-recurring events: start_date within scope AND no rrule
				'relation' => 'AND',
				[
					'key'     => $dateMetaKey,
					'value'   => [$bounds['start'], $bounds['end']],
					'compare' => 'BETWEEN',
					'type'    => $metaType,
				],
				[
					'key'     => 'whx4_events_rrule',
					'compare' => 'NOT EXISTS',
				],
			],
			[
				// Recurring events: start_date <= scope_end AND rrule exists
				'relation' => 'AND',
				[
					'key'     => $dateMetaKey,
					'value'   => $bounds['end'],
					'compare' => '<=',
					'type'    => $metaType,
				],
				[
					'key'     => 'whx4_events_rrule',
					'compare' => 'EXISTS',
				],
			],
		];
        
        $query->set('meta_query', $meta_query);
    }
    
    /**
     * Get the date meta key for this post type.
     * Implementing classes should define a DATE_META constant or override this method.
     *
     * @return string|null
     */
    protected function getDateMetaKey(): ?string
    {
        return defined('static::DATE_META') ? static::DATE_META : null;
    }
    
    /**
     * Get the meta type for date queries (DATE, DATETIME, or NUMERIC).
     * Implementing classes can override this method.
     *
     * @return string
     */
    protected function getDateMetaType(): string
    {
        return 'DATE';
    }
}