<?php

namespace atc\WXC\Taxonomies;

use atc\WXC\BaseHandler;

abstract class TaxonomyHandler extends BaseHandler
{
    protected const TYPE = 'taxonomy';

    public function __construct(array $config = [], \WP_Term|null $term = null)
    {
        parent::__construct($config, $term);
    }

    public function getObjectTypes(): array
    {
        return $this->getConfig()['object_types'] ?? [];
    }

    public function isHierarchical(): bool
    {
        return (bool)($this->getConfig()['hierarchical'] ?? false);
    }

    public function getSlug(): string
    {
        return (string)$this->getConfig()['slug'];
    }

    public function getArgs(): array
    {
        $labels = $this->getLabels();
        //
        return [
            'labels'            => $labels,
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true, // ok for default?
            'hierarchical'      => $this->isHierarchical(),
            'meta_box_cb'       => $this->isHierarchical() ? 'post_categories_meta_box' : null, // TODO:  mod to allow override?
        ];
    }

    public function registerTaxonomy(): void
    {
        //error_log( '=== TaxonomyHandler::registerTaxonomy() ===' );
        $slug  = $this->getSlug();
        $types = $this->getObjectTypes();
        $args  = $this->getArgs();

        if (!taxonomy_exists($slug)) {
            //error_log( "about to register taxonomy: " . $slug . " for posttypes: " . print_r($types,true) . "with args: " . print_r($args,true) );
            register_taxonomy($slug, $types, $args);
        } else {
            foreach ($types as $pt) {
                //error_log( "about to register taxonomy: " . $slug . " for posttype: " . $pt );
                register_taxonomy_for_object_type($slug, $pt);
            }
        }
    }

}
