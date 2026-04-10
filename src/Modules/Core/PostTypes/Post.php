<?php

namespace atc\WXC\Modules\Core\PostTypes;

use atc\WXC\PostTypes\PostTypeHandler;

// This handler stub is necessary to faciliate the registration of Subtypes of core WP post types

final class Post extends PostTypeHandler
{
    protected static function defineConfig(): array
    {
        return [
            'slug'             => 'post',
            'default_taxonomy' => 'category',
            'labels'           => [
                'name'          => 'Posts',
                'singular_name' => 'Post',
            ],
        ];
    }
}
