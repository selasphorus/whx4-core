<?php

declare(strict_types=1);

namespace atc\WXC\Traits;

trait AppliesCustomFiltersToMainQuery
{
    /**
     * Register custom query filters for main queries.
     * Call this from your post type's boot() method.
     */
    protected function registerCustomQueryFilters(): void
    {
        add_action('pre_get_posts', [$this, 'applyCustomFilters']);
    }
    
    /**
     * Apply post-type-specific filters to the main query.
     */
    public function applyCustomFilters(\WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        
        $postType = $query->get('post_type');
        
        // Let each post type handle its own custom filtering
        $method = 'applyCustomFiltersFor' . ucfirst($postType);
        if (method_exists($this, $method)) {
            $this->$method($query);
        }
        
        // Also provide a hook for external customization
        do_action("wxc_apply_custom_filters_{$postType}", $query, $this);
    }
    // See e.g. Sermon cpt class
}