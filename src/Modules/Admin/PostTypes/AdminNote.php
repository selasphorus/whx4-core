<?php

namespace atc\WXC\Modules\Admin\PostTypes;

use atc\WXC\PostTypes\PostTypeHandler;

// TODO: phase out this post type, migrate admin_notes to more general "Notes" posttype?
class AdminNote extends PostTypeHandler
{
    public function __construct(?\WP_Post $post = null) {
        $config = [
            'slug'        => 'admin_note',
            //'plural_slug' => 'admin_notes',
            'name' => 'Notes',
            //'rewrite' => ['slug' => 'whimsy'],
            //'menu_icon'   => 'dashicons-palmtree',
            'capability_type' => ['secret','secrets'],
            //'hierarchical' => false,
            //'taxonomies' => ['admin_tag', 'secret_category'],
            'taxonomies' => array( 'adminnote_category', 'admin_tag', 'data_table', 'query_tag', 'admin_tag' ),
        ];

        parent::__construct( $config, $post );
    }

    public function boot(): void
    {
        parent::boot(); // Optional if you add shared logic later
    }

}

