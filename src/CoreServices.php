<?php

namespace WXC;

use WXC\Utils\TitleFilter;
use WXC\FieldGroupLoader;
use WXC\Templates\TemplateRouter;
use WXC\Shortcodes\ShortcodeManager;
use WXC\Assets\AssetManager;

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
    }
}
