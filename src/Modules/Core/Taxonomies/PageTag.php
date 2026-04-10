<?php

namespace atc\WXC\Modules\Core\Taxonomies;

use atc\WXC\Taxonomies\TaxonomyHandler;

class PageTag extends TaxonomyHandler
{
    protected static function defineConfig(): array
    {
        return [
            'slug'         => 'page_tag',
            'plural_slug'  => 'page_tags',
            'object_types' => ['page'],
            'hierarchical' => false,
        ];
    }
}
