<?php

namespace atc\WHx4\Modules\Core\Taxonomies;

use atc\WXC\Taxonomies\TaxonomyHandler;

class PageTag extends TaxonomyHandler
{
    public function __construct(\WP_Term|null $term = null)
    {
        parent::__construct([
            'slug'         => 'page_tag',
            'plural_slug'  => 'page_tags',
            'object_types' => ['page'], // array of post types (by slug) to which this taxonomy applies
            'hierarchical' => false,
        ], $term);
    }
}
