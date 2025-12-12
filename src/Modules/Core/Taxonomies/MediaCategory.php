<?php

namespace atc\WHx4\Modules\Core\Taxonomies;

use atc\WXC\Taxonomies\TaxonomyHandler;

class MediaCategory extends TaxonomyHandler
{
    public function __construct(\WP_Term|null $term = null)
    {
        parent::__construct([
            'slug'         => 'media_category',
            'plural_slug'  => 'media_categories',
            'object_types' => ['attachment'], // array of post types (by slug) to which this taxonomy applies
            'hierarchical' => true,
        ], $term);
    }
}
