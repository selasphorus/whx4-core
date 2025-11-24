<?php

namespace atc\WXC\ACF;

class JsonPaths
{
	// ACF Set custom load and save JSON points.
	// @link https://www.advancedcustomfields.com/resources/local-json/
    public static function register(): void
    {
        add_filter( 'acf/json/load_paths', [self::class, 'jsonLoadPaths'] );
        add_filter( 'acf/settings/save_json/type=acf-field-group', [self::class, 'jsonSavePathForFieldGroups'] );
        add_filter( 'acf/settings/save_json/type=acf-ui-options-page', [self::class, 'jsonSavePathForOptionPages'] );
        add_filter( 'acf/settings/save_json/type=acf-post-type', [self::class, 'jsonSavePathForPostTypes'] );
        add_filter( 'acf/settings/save_json/type=acf-taxonomy', [self::class, 'jsonSavePathForTaxonomies'] );
        add_filter( 'acf/json/save_file_name', [self::class, 'jsonFilename'], 10, 3 );
    }

    public static function jsonLoadPaths( array $paths ): array
    {
        $paths[] = WXC_PLUGIN_DIR . '/acf-json/field-groups';
        $paths[] = WXC_PLUGIN_DIR . '/acf-json/options-pages';
        $paths[] = WXC_PLUGIN_DIR . '/acf-json/post-types';
        $paths[] = WXC_PLUGIN_DIR . '/acf-json/taxonomies';

        return $paths;
    }

    public static function jsonSavePathForPostTypes(): string
    {
        return WXC_PLUGIN_DIR . '/acf-json/post-types';
    }

    public static function jsonSavePathForFieldGroups(): string
    {
        return WXC_PLUGIN_DIR . '/acf-json/field-groups';
    }

    public static function jsonSavePathForTaxonomies(): string
    {
        return WXC_PLUGIN_DIR . '/acf-json/taxonomies';
    }

    public static function jsonSavePathForOptionPages(): string
    {
        return WXC_PLUGIN_DIR . '/acf-json/options-pages';
    }

    public static function jsonFilename( string $filename, array $post ): string
    {
        if ( isset( $post['title'] ) ) {
            $filename = str_replace([' ', '_'], '-', $post['title']);
            $filename = strtolower( $filename ) . '.json';
        }

        return $filename;
    }
}
