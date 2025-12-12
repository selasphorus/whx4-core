<?php

namespace atc\WXC\Modules\Core\PostTypes;

use atc\WXC\PostTypes\PostTypeHandler;

// This handler stub is necessary to faciliate the registration of Subtypes of core WP post types

final class Page extends PostTypeHandler
{
    public function __construct(?\WP_Post $post=null)
    {
        $config = [
            'slug'   => 'page',
            'labels' => [
                'name'          => 'Pages',
                'singular_name' => 'Page',
            ],
            'taxonomies'   => [ 'page_tag' ],
            // Keep supports minimal; core already defines this post type.
            // Your system can still hook titles/content/etc. by slug.
        ];

        parent::__construct($config, $post);
    }

    public function boot(): void
    {
        parent::boot(); // Optional if you add shared logic later

        /*$this->applyTitleArgs( $this->getSlug(), [
            'line_breaks'    => true,
            'show_subtitle'  => true,
            'hlevel_sub'     => 4,
            'called_by'      => 'Post::boot',
        ]);*/
    }
}
