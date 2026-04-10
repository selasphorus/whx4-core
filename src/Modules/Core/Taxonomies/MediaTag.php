<?php

namespace atc\WXC\Modules\Core\Taxonomies;

use atc\WXC\Taxonomies\TaxonomyHandler;

class MediaTag extends TaxonomyHandler
{
    protected static function defineConfig(): array
    {
        return [
            'slug'         => 'media_tag',
            'plural_slug'  => 'media_tags',
            'object_types' => ['attachment'],
            'hierarchical' => true,
        ];
    }
}
