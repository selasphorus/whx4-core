<?php

namespace atc\WXC\Taxonomies;

use atc\WXC\BaseHandler;
use atc\WXC\Logger;

abstract class TaxonomyHandler extends BaseHandler
{
    protected const TYPE = 'taxonomy';
    public const OBJECT_TYPES_ALL = '*';

    public function __construct(?\WP_Term $term = null)
    {
        parent::__construct($term);
    }

    public static function getSlug(): string
    {
        return (string) static::getConfig()['slug'];
    }

	public static function getRawObjectTypes(): array
	{
		$types = static::getConfig()['object_types'] ?? [];
		return is_array($types) ? $types : [$types];
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
    
    /**
	 * Register this taxonomy against the supplied object types.
	 *
	 * Object type resolution (including wildcard expansion) is the
	 * registrar's responsibility; this method only handles the WP API calls.
	 *
	 * @param string[] $objectTypes Resolved CPT slugs to attach this taxonomy to.
	 */
	public function registerTaxonomy(array $objectTypes): void
	{
		$slug = static::getSlug();
		$args = $this->getArgs();
	
		if (!taxonomy_exists($slug)) {
			Logger::debug('registering taxonomy: ' . $slug . ' for: ' . implode(', ', $objectTypes), 'wptx');
			register_taxonomy($slug, $objectTypes, $args);
		} else {
			foreach ($objectTypes as $pt) {
				Logger::debug('registering taxonomy: ' . $slug . ' for: ' . $pt, 'wptx');
				register_taxonomy_for_object_type($slug, $pt);
			}
		}
	}
}
