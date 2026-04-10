<?php

namespace atc\WXC\Modules\Core\Taxonomies;

use atc\WXC\Taxonomies\TaxonomyHandler;

class MediaCategory extends TaxonomyHandler
{
    protected static function defineConfig(): array
    {
        return [
            'slug'         => 'media_category',
            'plural_slug'  => 'media_categories',
            'object_types' => ['attachment'],
            'hierarchical' => true,
        ];
    }
}
