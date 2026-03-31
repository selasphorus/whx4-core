<?php

namespace atc\WXC;

use atc\WXC\Logger;
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
        $services = apply_filters( 'wxc_core_services', [
            TitleFilter::class,
            //FieldGroupLoader::class,
            TemplateRouter::class,
            ShortcodeManager::class,
            AssetManager::class,
        ]);

        foreach ( $services as $class ) {
            if ( is_string( $class ) && method_exists( $class, 'boot' ) ) {
                $class::boot();
            } else {
                //if ( !is_string( $class ) ) { Logger::debug( 'class: ' . $class . ' -- NOT a string!', 'wxc' ); }
                //if ( !method_exists( $class, 'boot' ) ) { Logger::debug( 'class: ' . $class . ' boot method not found!', 'wxc' ); }
                //if ( !class_exists( $class) ) { Logger::debug( 'class: ' . $class . ' -- DOES NOT EXIST!', 'wxc' ); }
            }
        }
        
        ShortcodeManager::add(DisplayShortcode::class);
        
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
