<?php

namespace atc\WXC\Modules\Admin\PostTypes;

use atc\WXC\PostTypes\PostTypeHandler;

// TODO: phase out this post type, migrate admin_notes to more general "Notes" posttype?
class AdminNote extends PostTypeHandler
{
    protected static function defineConfig(): array
    {
        return [
            'slug'             => 'admin_note',
            'name'             => 'Notes',
            //'menu_icon'        => 'dashicons-networking',
			'capability_type'  => ['secret','secrets'], // ???
            'supports'         => ['title', 'author', 'thumbnail', 'editor', 'excerpt', 'revisions'],
			'taxonomies'       => ['adminnote_category', 'data_table', 'query_tag'],
            'default_taxonomy' => 'adminnote_category',
            'labels'           => [
				//'add_new_item' => 'Gather a new Group',
            ],
			//'hierarchical' => true,
        ];
    }

    public function boot(): void
    {
        parent::boot(); // Optional if you add shared logic later
    }

}

