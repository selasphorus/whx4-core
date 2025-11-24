<?php

namespace atc\WXC\Utils;

class PluginPaths
{
    /**
     * Absolute path to plugin root (trailing slash).
     */
    public static function baseDir(): string
    {
        return trailingslashit( dirname( dirname( __DIR__ ) ) );
    }

    /**
     * URL to plugin root (trailing slash).
     */
    public static function baseUrl(): string
    {
        return trailingslashit( plugin_dir_url( self::baseFile() ) );
    }

    /**
     * Absolute path to plugin's main file.
     */
    public static function baseFile(): string
    {
        return self::baseDir() . 'wxc.php'; // Adjust if your main file differs
    }

    /**
     * URL to a given file relative to plugin root.
     */
    public static function url( string $relativePath ): string
    {
        return self::baseUrl() . ltrim( $relativePath, '/' );
    }

    /**
     * Filesystem path to a file relative to plugin root.
     */
    public static function path( string $relativePath ): string
    {
        return self::baseDir() . ltrim( $relativePath, '/' );
    }
}
