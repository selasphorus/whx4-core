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
        
        // Get the post type slug from the implementing class
        $postType = $this->getSlug();
        
        if ($query->get('post_type') !== $postType) {
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
        
        // Add meta_query for date filtering
        $meta_query = $query->get('meta_query') ?: [];
        $meta_query[] = [
            'key'     => $dateMetaKey,
            'value'   => [$bounds['start'], $bounds['end']],
            'compare' => 'BETWEEN',
            'type'    => $metaType,
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