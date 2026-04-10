<?php

namespace atc\WXC\Modules\Admin\Taxonomies;

use atc\WXC\Taxonomies\TaxonomyHandler;

class AdminTag extends TaxonomyHandler
{
    protected static function defineConfig(): array
    {
        return [
            'slug'             => 'admin_tag',
            //'plural_slug'      => 'admin_tags',
            'object_types' => ['*'],
            'hierarchical' => true,
        ];
    }
}
