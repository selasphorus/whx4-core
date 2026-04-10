<?php

namespace atc\WXC\Modules\Admin\Taxonomies;

use atc\WXC\Taxonomies\TaxonomyHandler;

class QueryTag extends TaxonomyHandler
{
    protected static function defineConfig(): array
    {
        return [
            'slug'         => 'query_tag',
            'plural_slug'  => 'query_tags',
            'object_types' => ['admin_note', 'note'],
            'hierarchical' => true,
        ];
    }
}
