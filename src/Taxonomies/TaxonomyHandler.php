<?php

namespace atc\WXC\Taxonomies;

use atc\WXC\BaseHandler;
use atc\WXC\Logger;

abstract class TaxonomyHandler extends BaseHandler
{
    protected const TYPE = 'taxonomy';

    public function __construct(?\WP_Term $term = null)
    {
        parent::__construct($term);
    }

    public static function getSlug(): string
    {
        return (string) static::getConfig()['slug'];
    }

    public static function getObjectTypes(): array
    {
        return static::getConfig()['object_types'] ?? [];
    }

    public static function isHierarchical(): bool
    {
        return (bool) (static::getConfig()['hierarchical'] ?? false);
    }

    public function getArgs(): array
    {
        return [
            'labels'            => $this->getLabels(),
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'hierarchical'      => static::isHierarchical(),
            'meta_box_cb'       => static::isHierarchical() ? 'post_categories_meta_box' : null,
        ];
    }

    public function registerTaxonomy(): void
    {
        $slug  = static::getSlug();
        $types = static::getObjectTypes();
        $args  = $this->getArgs();

        if (!taxonomy_exists($slug)) {
            Logger::debug('about to register taxonomy: ' . $slug . ' for posttypes: ' . print_r($types, true) . ' with args: ' . print_r($args, true), 'wptx');
            register_taxonomy($slug, $types, $args);
        } else {
            foreach ($types as $pt) {
                Logger::debug('about to register taxonomy: ' . $slug . ' for posttype: ' . $pt, 'wptx');
                register_taxonomy_for_object_type($slug, $pt);
            }
        }
    }
}
