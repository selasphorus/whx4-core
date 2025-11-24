<?php
namespace atc\WXC\Shortcodes;

use atc\WXC\Contracts\ShortcodeInterface;

final class ShortcodeManager
{
    /** @var array<class-string<ShortcodeInterface>, class-string<ShortcodeInterface>> */
    private static array $registry = [];

    /** @var array<string, class-string<ShortcodeInterface>> map tag => class */
    private static array $registeredByTag = [];

    private function __construct() {}

    public static function boot(): void
    {
        //error_log('=== ShortcodeManager::boot() ===');
        if (did_action('init')) {
            self::registerAll();
            return;
        }
        add_action('init', [self::class, 'registerAll'], PHP_INT_MAX);
    }

    public static function add(string $fqcn): void
    {
        //error_log('=== ShortcodeManager::add() ===');
        //error_log('ShortcodeManager::add called for ' . $fqcn . '; init? ' . did_action('init'));

        self::$registry[$fqcn] = $fqcn;

        // If init already fired, validate & register now (no timing worries).
        if (did_action('init')) {
            self::registerClass($fqcn);
        }
    }

    public static function registerAll(): void
    {
        //error_log('=== ShortcodeManager::registerAll() ===');
        // Merge explicit registry and filter-provided classes
        $viaFilter  = (array)apply_filters('wxc_register_shortcodes', []);
        $candidates = array_unique(array_merge(array_values(self::$registry), $viaFilter));
        //error_log('Shortcode candidates: ' . json_encode($candidates, JSON_UNESCAPED_SLASHES));

        foreach ($candidates as $fqcn) {
            self::registerClass($fqcn);
        }
    }

    private static function registerClass(string $fqcn): void
    {
        //error_log('=== ShortcodeManager::registerClass() ===');
        //error_log('About to attempt registration of shortcode class: ' . $fqcn);

        if (!class_exists($fqcn)) {
            return;
        }
        if (!is_subclass_of($fqcn, ShortcodeInterface::class)) {
            return;
        }

        /** @var class-string<ShortcodeInterface> $fqcn */
        $tag = $fqcn::tag();

        // Deduplicate by tag
        if (isset(self::$registeredByTag[$tag])) {
            return;
        }

        $instance = new $fqcn();
        add_shortcode($tag, [$instance, 'render']);

        self::$registeredByTag[$tag] = $fqcn;
    }

    /** For unit tests */
    public static function reset(): void
    {
        self::$registry = [];
        self::$registeredByTag = [];
    }
}
