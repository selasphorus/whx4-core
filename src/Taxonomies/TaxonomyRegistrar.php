<?php

namespace atc\WXC\Taxonomies;

use atc\WXC\Logger;
use atc\WXC\BootOrder;
use atc\WXC\Taxonomies\TaxonomyHandler;
//use atc\WXC\PostTypes\SubtypeRegistry;

final class TaxonomyRegistrar
{
    public static function register(): void
    {
        add_action( 'init', [ self::class, 'bootstrap' ], BootOrder::TAXONOMIES );
    }

    /**
     * Single place where taxonomies are registered.
     * Accepts handlers via the 'wxc_register_taxonomy_handlers' filter.
     * Also synthesizes handlers for per‑CPT subtype taxonomies from SubtypeRegistry.
     */
    public static function bootstrap(): void
    {
        // 1) Start with any handlers contributed by CPTs/modules/core
        $handlers = array_unique((array) apply_filters('wxc_register_taxonomy_handlers', []));

        // 2) Subtype taxonomies -- Add synthesized handlers for per‑CPT subtype taxonomies
        /*$subtypes = SubtypeRegistry::getAll();
        foreach (array_keys($subtypes) as $postType) {
            $slug = SubtypeRegistry::getTaxonomyForPostType($postType);
            Logger::debug( 'Subtype slug: ' . $slug . '/postType: '. $postType );

            // Anonymous handler that behaves like a TaxonomyHandler
            $handlers[] = new class($slug, $postType) extends TaxonomyHandler {
                public function __construct(private string $slug, private string $pt)
                {
                    parent::__construct([
                        'slug'         => $this->slug,
                        'plural_slug'  => $this->slug . 's', // ?
                        'object_types' => [$this->pt],
                        'hierarchical' => true,
                    ], null);
                }
            };
        }*/

        if (empty($handlers)) {
            return; // nothing to do
        }

        //Logger::debug("taxonomy handlers", $handlers, 'wxc' );

        // Resolve active CPTs (for '*' wildcard); decouple via a filter
        $activePostTypes = (array) apply_filters('wxc_active_post_types', []);

        foreach ($handlers as $h) {
            // Accept FQCNs or ready instances
            if (is_string($h)) {
                if (!class_exists($h) || !is_subclass_of($h, TaxonomyHandler::class)) {
                    //Logger::warn("handler class: " . $h . " is not a valid TaxonomyHandler class.");
                    continue;
                }
                $objectTypes = self::resolveObjectTypes($h, $activePostTypes);
                $h = new $h();
            } elseif ($h instanceof TaxonomyHandler) {
				$objectTypes = self::resolveObjectTypes(get_class($h), $activePostTypes);
			} else {
				//Logger::warn("handler class: " . $h . " is not a valid TaxonomyHandler class.");
				continue;
			}
			
			if (empty($objectTypes)) {
				continue;
			}
	
			try {
				$h->registerTaxonomy($objectTypes);
			} catch (\Throwable $e) {
				Logger::error($e->getMessage(), $e->getTraceAsString());
			}
        }
    }
    
    /**
	 * Resolve object types for a taxonomy handler class, expanding
	 * OBJECT_TYPES_ALL ('*') to the full list of active CPT slugs.
	 *
	 * @param class-string<TaxonomyHandler> $handlerClass
	 * @param string[] $activePostTypes
	 * @return string[]
	 */
	private static function resolveObjectTypes(string $handlerClass, array $activePostTypes): array
	{
		$types = $handlerClass::getRawObjectTypes();
	
		if (in_array(TaxonomyHandler::OBJECT_TYPES_ALL, $types, true)) {
			return $activePostTypes;
		}
	
		return $types;
	}
}
