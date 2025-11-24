<?php

namespace WXC\Taxonomies;

use WXC\BootOrder;
use WXC\Taxonomies\TaxonomyHandler;
//use WXC\PostTypes\SubtypeRegistry;

final class TaxonomyRegistrar
{
    public static function register(): void
    {
        //error_log( '=== TaxonomyRegistrar::register() ===' );
        add_action( 'init', [ self::class, 'bootstrap' ], BootOrder::TAXONOMIES );
    }

    /**
     * Single place where taxonomies are registered.
     * Accepts handlers via the 'wxc_register_taxonomy_handlers' filter.
     * Also synthesizes handlers for per‑CPT subtype taxonomies from SubtypeRegistry.
     */
    public static function bootstrap(): void
    {
        //error_log( '=== TaxonomyRegistrar::bootstrap() ===' );

        // 1) Start with any handlers contributed by CPTs/modules/core
        $handlers = (array) apply_filters('wxc_register_taxonomy_handlers', []);
        $handlers = array_unique($handlers); // Get rid of duplicates

        // 2) Subtype taxonomies -- Add synthesized handlers for per‑CPT subtype taxonomies
        /*$subtypes = SubtypeRegistry::getAll();
        foreach (array_keys($subtypes) as $postType) {
            $slug = SubtypeRegistry::getTaxonomyForPostType($postType);
            error_log( 'Subtype slug: ' . $slug . '/postType: '. $postType );

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

        //error_log("taxonomy handlers: " . print_r($handlers, true));

        // Resolve active CPTs (for '*' wildcard); decouple via a filter
        $activePostTypes = (array) apply_filters('wxc_active_post_types', []);

        foreach ($handlers as $h) {
            // Accept FQCNs or ready instances
            if (is_string($h)) {
                if (!class_exists($h) || !is_subclass_of($h, TaxonomyHandler::class)) {
                    //error_log("handler class: " . $h . " is not a valid TaxonomyHandler class.");
                    continue;
                }
                $h = new $h();
            } elseif (!$h instanceof TaxonomyHandler) {
                //error_log("handler class: " . $h . " is not a valid TaxonomyHandler class.");
                continue;
            }

            // Resolve wildcard '*' and apply tax to all active WXC CPTs (if provided)
            $targets = $h->getObjectTypes();
            if (in_array('*', $targets, true)) {
                $targets = $activePostTypes ?: [];
                // If nothing provided, skip rather than registering a taxonomy with no object types
                if (empty($targets)) {
                    continue;
                }
                // Quick way to set the resolved targets: override via a tiny child class
                $h = new class($h, $targets) extends TaxonomyHandler {
                    public function __construct(private TaxonomyHandler $base, private array $targets)
                    {
                        // clone base config but swap object_types
                        $cfg = $this->base->getConfig();
                        $cfg['object_types'] = $this->targets;
                        parent::__construct($cfg, 'taxonomy', null);
                    }
                };
            }

            // Finally, register it (handler knows how to call register_taxonomy)
            try {
                $h->registerTaxonomy();
            } catch (\Throwable) {
                // optional: error_log(...)
            }
        }
    }
}
