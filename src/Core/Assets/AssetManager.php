<?php

declare(strict_types=1);

namespace atc\BhWP\Core\Assets;

final class AssetManager
{
    /** @var array<string,mixed> */
    private static array $catalog = ['styles' => [], 'scripts' => []];

    public static function boot(): void
    {
        //error_log( '=== AssetManager::boot() ===' );
        add_action('init', [self::class, 'collectAndRegister'], 8);
        add_action('wp_enqueue_scripts', [self::class, 'maybeAutoloadFront'], 11);
        add_action('admin_enqueue_scripts', [self::class, 'maybeAutoloadAdmin'], 11);
    }

    /**
     * @param array<string,mixed> $assets
     * @return array<string,mixed>
     */
    public static function filterShape(array $assets): array
    {
        $assets['styles']  = is_array($assets['styles'] ?? null) ? $assets['styles'] : [];
        $assets['scripts'] = is_array($assets['scripts'] ?? null) ? $assets['scripts'] : [];
        return $assets;
    }

    public static function collectAndRegister(): void
    {
        //error_log( '=== AssetManager::collectAndRegister() ===' );
        $assets = apply_filters('bhwp_assets', ['styles' => [], 'scripts' => []]);
        $assets = self::filterShape($assets);
        self::$catalog = $assets;
        
        //error_log('[AssetManager::collectAndRegister] assets: ' . print_r($assets, true));

        foreach ($assets['styles'] as $s) {
            //error_log('[AssetManager::collectAndRegister] style: ' . print_r($s, true));
            $handle = (string)($s['handle'] ?? '');
            $src    = (string)($s['src'] ?? '');
            if ($handle === '' || $src === '') {
                continue;
            }
            $deps = is_array($s['deps'] ?? null) ? $s['deps'] : [];
            $ver  = self::resolveVersion($s);
            $media = (string)($s['media'] ?? 'all');
            //error_log('[AssetManager::collectAndRegister] About to register style with handle: ' . $handle);
            wp_register_style($handle, $src, $deps, $ver, $media);
        }

        foreach ($assets['scripts'] as $j) {
            $handle = (string)($j['handle'] ?? '');
            $src    = (string)($j['src'] ?? '');
            if ($handle === '' || $src === '') {
                continue;
            }
            $deps = is_array($j['deps'] ?? null) ? $j['deps'] : [];
            $ver  = self::resolveVersion($j);
            $inFooter = (bool)($j['in_footer'] ?? true);
            wp_register_script($handle, $src, $deps, $ver, $inFooter);
        }
    }

    /**
     * Autoload front-end assets when explicitly marked with 'autoload' => true and 'where' includes 'front' or 'both'.
     */
    public static function maybeAutoloadFront(): void
    {
        foreach (self::$catalog['styles'] as $s) {
            if (!empty($s['autoload']) && self::whereMatch($s, 'front')) {
                wp_enqueue_style((string)$s['handle']);
            }
        }
        foreach (self::$catalog['scripts'] as $j) {
            if (!empty($j['autoload']) && self::whereMatch($j, 'front')) {
                wp_enqueue_script((string)$j['handle']);
            }
        }
    }

    /**
     * Autoload admin assets when 'autoload' => true and 'where' includes 'admin' or 'both'.
     */
    public static function maybeAutoloadAdmin(): void
    {
        foreach (self::$catalog['styles'] as $s) {
            if (!empty($s['autoload']) && self::whereMatch($s, 'admin')) {
                wp_enqueue_style((string)$s['handle']);
            }
        }
        foreach (self::$catalog['scripts'] as $j) {
            if (!empty($j['autoload']) && self::whereMatch($j, 'admin')) {
                wp_enqueue_script((string)$j['handle']);
            }
        }
    }

    /**
     * Helper for shortcodes/views: enqueue by handle when needed.
     */
    public static function enqueue(string $handle): void
    {
        if (wp_style_is($handle, 'registered') && !wp_style_is($handle, 'enqueued')) {
            wp_enqueue_style($handle);
        }
        if (wp_script_is($handle, 'registered') && !wp_script_is($handle, 'enqueued')) {
            wp_enqueue_script($handle);
        }
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function resolveVersion(array $item): string
    {
        // If 'ver' provided, use it. If 'ver' === 'auto' and 'path' provided, use filemtime. Else default to plugin version if defined.
        $ver = $item['ver'] ?? null;
        if (is_string($ver) && $ver !== '') {
            if ($ver !== 'auto') {
                return $ver;
            }
        }
        $path = (string)($item['path'] ?? '');
        if ($path !== '' && file_exists($path)) {
            $mtime = (int)@filemtime($path);
            if ($mtime > 0) {
                return (string)$mtime;
            }
        }
        return defined('BHWP_VERSION') ? (string)BHWP_VERSION : '1.0.0';
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function whereMatch(array $item, string $target): bool
    {
        $where = $item['where'] ?? 'front';
        $where = is_array($where) ? $where : [$where];
        $where = array_map(static fn($w) => is_string($w) ? strtolower(trim($w)) : '', $where);
        return in_array('both', $where, true) || in_array($target, $where, true);
    }
}
