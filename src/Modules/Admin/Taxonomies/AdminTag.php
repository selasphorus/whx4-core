<?php

namespace atc\WXC\Modules\Admin\Taxonomies;

use atc\WXC\Taxonomies\TaxonomyHandler;

class AdminTag extends TaxonomyHandler
{
    public function __construct(\WP_Term|null $term = null)
    {
        parent::__construct([
            'slug'         => 'admin_tag',
            'plural_slug'  => 'admin_tags',
            'object_types' => ['admin_note', 'note'], // array of post types (by slug) to which this taxonomy applies
            // TODO: figure out how to make this taxonomy universally available -- i.e. to ALL active post types
            //[ 'admin_note', 'attachment', 'bible_book', 'collect', 'collection', 'data_table', 'edition', 'ensemble', 'event', 'event-recurring', 'event_series', 'lectionary', 'liturgical_date', 'liturgical_date_calc', 'location', 'music_list', 'page', 'person', 'post', 'product', 'project', 'psalms_of_the_day', 'publication', 'publisher', 'reading', 'repertoire', 'sermon', 'sermon_series', 'snippet', 'venue' ]
            'hierarchical' => true,
        ], $term);
    }
}
