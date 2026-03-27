<?php

namespace atc\WXC;

use atc\WXC\Utils\TitleFilter;
use atc\WXC\FieldGroupLoader;
use atc\WXC\Templates\TemplateRouter;
use atc\WXC\Shortcodes\ShortcodeManager;
use atc\WXC\Assets\AssetManager;
use atc\WXC\Display\DisplayShortcode;

class CoreServices
{
    /**
     * Boot all core utility services that need initialization.
     */
    public static function boot(): void
    {
        //error_log( '=== CoreServices::boot() ===' );
        $services = apply_filters( 'wxc_core_services', [
            TitleFilter::class,
            //FieldGroupLoader::class,
            TemplateRouter::class,
            ShortcodeManager::class,
            AssetManager::class,
            ShortcodeManager::add(DisplayShortcode::class),
        ]);

        foreach ( $services as $class ) {
            //error_log( 'About to attempt to load and boot class: ' . $class );
            if ( is_string( $class ) && method_exists( $class, 'boot' ) ) {
                $class::boot();
            } else {
                //if ( !is_string( $class ) ) { error_log( 'class: ' . $class . ' -- NOT a string!'); }
                //if ( !method_exists( $class, 'boot' ) ) { error_log( 'class: ' . $class . ' boot method not found!'); }
                //if ( !class_exists( $class) ) { error_log( 'class: ' . $class . ' -- DOES NOT EXIST!'); }
            }
        }
        
        // Set up for customizing display of post images
		add_filter('wxc_post_image', function(string $image, \WP_Post $post, string $size): string {
			if ($image !== '') {
				return $image;
			}
			if (!has_post_thumbnail($post->ID)) {
				return '';
			}
			return get_the_post_thumbnail($post->ID, $size, ['class' => 'wxc-item__image']);
		}, 5, 3);
    }
}
