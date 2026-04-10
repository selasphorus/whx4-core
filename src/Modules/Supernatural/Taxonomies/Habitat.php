<?php

namespace atc\WXC\Modules\Supernatural\Taxonomies;

use atc\WXC\Taxonomies\TaxonomyHandler;

class Habitat extends TaxonomyHandler
{
    protected static function defineConfig(): array
    {
        return [
            'slug'         => 'habitat',
            'plural_slug'  => 'habitats',
            'object_types' => ['monster'],
            'hierarchical' => true,
        ];
    }
}
