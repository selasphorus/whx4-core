<?php

namespace atc\WXC\Modules\Core\PostTypes;

use atc\WXC\PostTypes\PostTypeHandler;

// This handler stub is necessary to faciliate the registration of Subtypes of core WP post types

final class Page extends PostTypeHandler
{
    protected static function defineConfig(): array
    {
        return [
            'slug'             => 'page',
            'taxonomies'       => ['page_tag'],
            //'default_taxonomy' => 'category',
            'labels'            => [
                'name'          => 'Pages',
                'singular_name' => 'Page',
            ],
            // Keep supports minimal; core already defines this post type.
            // Your system can still hook titles/content/etc. by slug.
        ];
    }

    public function boot(): void
    {
        parent::boot(); // Optional if you add shared logic later
    }
}
