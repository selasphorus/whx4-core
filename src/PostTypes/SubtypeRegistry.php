<?php

namespace atc\WXC\PostTypes;

use atc\WXC\BootOrder;
use atc\WXC\Contracts\SubtypeInterface;

// WIP 08/22/25
final class SubtypeRegistry
{
    /** @var array<string, array<string, SubtypeInterface>> */
	protected static array $instances = [];
	/** @var array<string, array<string, array{label:string, args:array, taxonomy?:string, term?:string}>> */
	protected static array $meta = [];

    public static function register(): void
    {
        //error_log( '=== SubtypeRegistry::register() ===' );
        add_action('init', [self::class, 'collect'], BootOrder::SUBTYPES);
    }
    
    // Normalize providers, store instance + meta, no side-effects
    public static function collect(): void
    {
        //error_log( '=== SubtypeRegistry::collect() ===' );
        //self::$subtypes = [];
        self::$instances = [];
        self::$meta = [];
        
        // 1) Gather providers
        $providers = apply_filters('wxc_register_subtypes', []);  // array of SubtypeInterface|class-string
        //error_log( 'providers: ' . print_r($providers, true) );
        
        foreach ($providers as $provider) {
			$instance = is_string($provider) ? (class_exists($provider) ? new $provider() : null) : $provider;
			if (!$instance instanceof SubtypeInterface) {
				// quietly skip invalid entries; optionally log under REX_DEBUG
				error_log( 'subtype provider: ' . $provider->getSlug() . ' is NOT a valid SubtypeInterface.');
				continue;
			}
	
			$pt   = $instance->getPostType();
			$slug = $instance->getSlug();
			/*
			self::$subtypes[$pt][$slug] = [
				'label' => $provider->getLabel(),
				'args'  => $provider->getTermArgs(),
			];
			*/
			self::$instances[$pt][$slug] = $instance;
	
			$meta = [
				'label' => $instance->getLabel(),
				'args'  => $instance->getTermArgs(),
			];
	
			// Optional: record taxonomy + term if subtype exposes them (not required by interface)
			if (method_exists($instance, 'getTermSlug')) {
				$meta['term'] = $instance->getTermSlug();
			}
			if (method_exists($instance, 'getTaxonomy')) {
				$meta['taxonomy'] = $instance->getTaxonomy();
			}
	
			self::$meta[$pt][$slug] = $meta;
		}
		
        /**
         * Allow other systems to react to the collected map.
         * IMPORTANT: collection only—no side effects.
         */
        //do_action('wxc_subtypes_collected', self::$subtypes);
        do_action('wxc_subtypes_collected', self::$meta, self::$instances);
    }
    
    /** @return array<string, array{label:string, args:array}> */
    public static function getForPostType(string $postType): array
    {
        return self::$subtypes[$postType] ?? [];
    }

    // Resolvers
	public static function resolve(string $postType, string $slug): ?SubtypeInterface
	{
		error_log( '=== SubtypeRegistry::resolve() ===' );
		error_log( 'Attempting to resolve subtype with postType: ' . $postType . ' and slug: ' . $slug);
		return self::$instances[$postType][$slug] ?? null;
	}
	
	/** @return array<string, SubtypeInterface> */
	public static function allForPostType(string $postType): array
	{
		return self::$instances[$postType] ?? [];
	}
	
	/** @return array<string, array{label:string, args:array, taxonomy?:string, term?:string}> */
	public static function getMetaForPostType(string $postType): array
	{
		return self::$meta[$postType] ?? [];
	}
	
	public static function getAllMeta(): array
	{
		return self::$meta;
	}
}
