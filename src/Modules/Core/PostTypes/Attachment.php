<?php

namespace atc\WXC\Modules\Core\PostTypes;

use atc\WXC\PostTypes\PostTypeHandler;

// This handler stub is necessary to faciliate the registration of Subtypes of core WP post types

final class Attachment extends PostTypeHandler
{
    protected static function defineConfig(): array
    {
        return [
            'slug'             => 'attachment',
            'taxonomies'       => ['media_category', 'media_tag'],
            'default_taxonomy' => 'media_category',
            'labels'           => [
                'name'          => 'Media',
                'singular_name' => 'File',
            ],
            // Attachments are special in WP; don't redefine supports here.
        ];
    }

    public function boot(): void
    {
        parent::boot(); // Optional if you add shared logic later
    }
}
