<?php

namespace atc\WXC\Modules\Admin\Taxonomies;

use atc\WXC\Taxonomies\TaxonomyHandler;

class AdminNoteCategory extends TaxonomyHandler
{
    protected static function defineConfig(): array
    {
        return [
            'slug'             => 'adminnote_category',
            'plural_slug'      => 'adminnote_categories',
            'object_types' => ['adminnote', 'note'],
            'hierarchical' => true,
        ];
    }
}
