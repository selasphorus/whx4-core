<?php

declare(strict_types=1);

namespace atc\WXC\Templates;

use atc\WXC\App;
use atc\WXC\Templates\ViewKind;
use atc\WXC\Utils\Text;
use atc\WXC\Utils\ClassInfo;

/**
 * Framework-level view resolver & renderer.
 * Responsibilities:
 *  - Register module view roots
 *  - Resolve view paths with layered overrides (theme → module → plugin)
 *  - Render views to output or string
 *
 * View "spec":
 *   - kind: one of ViewKind (enum instance) or string ('module', 'posttype', ...)
 *   - module: module slug (e.g. 'supernatural')
 *   - post_type: post type slug (e.g. 'monster')
 *   - allow_theme: whether to check theme overrides (default true)
 */
final class ViewLoader
{
    /** @var array<string,string> Module slug => absolute view root path */
    protected static array $moduleViewRoots = [];

    public static function hasViewRoot( string $moduleSlug ): bool
    {
        $slug = Text::slugify($moduleSlug); // just in case
        return isset(self::$moduleViewRoots[$slug]);
    }

    /**
     * Register a module-specific view directory (e.g., src/Modules/Supernatural/Views)
     */
    public static function registerModuleViewRoot( string $moduleSlug, string $absolutePath ): void
    {
        $key = Text::slugify($moduleSlug); // just in case
        $viewRoot = rtrim( $absolutePath, '/' );
        //error_log( '=== viewRoot for moduleSlug/key: ' . $key . ' is: ' . $viewRoot . '===' );
        self::$moduleViewRoots[ $key ] = $viewRoot;
    }

    /**
     * Echo a rendered view immediately.
     *
     * @param array<string,mixed> $vars
     * @param array{kind?:string|ViewKind,module?:string,post_type?:string,allow_theme?:bool} $specs
     */
    public static function render( string $view, array $vars = [], array $specs = [] ): void
    {
        echo self::renderToString($view, $vars, $specs);
    }

    /**
     * Return the rendered view as a string. Uses output buffering.
     *
     * @param array<string,mixed> $vars
     * @param array{kind?:string|ViewKind,module?:string,post_type?:string,allow_theme?:bool} $specs
     */
    public static function renderToString(string $view, array $vars = [], array $specs = []): string
    {
        //error_log( '[ViewLoader::renderToString] called for view: ' . $view . ' with vars: ' . print_r($vars,true) . ' and spec: ' . print_r($specs,true) . '===' );
        //error_log( '=== renderToString for view: ' . $view . ' with spec: ' . print_r($specs,true) . '===' );
        $path = self::getViewPath($view, $specs);
        //
        $kind   = self::normalizeKind($specs['kind'] ?? null);
        $module = Text::slugify($specs['module'] ?? '');
        $ptype  = Text::slugify($specs['post_type'] ?? '');

        if ( $path ) {
            ob_start();
            extract($vars, EXTR_SKIP);
            include $path;
            return ob_get_clean();
        }

        return '<div class="troubleshooting notice notice-error"><p>' .
            esc_html("View not found: {$view} (kind: {$kind}, module: {$module}, post_type: {$ptype})") .
            '</p></div>';
    }

    /**
     * Resolve a view to a concrete file path, or null if not found.
     *
     * @param array{kind?:string|ViewKind,module?:string,post_type?:string,allow_theme?:bool} $specs
     */
    public static function getViewPath(string $view, array $specs = []): ?string
    {
        //error_log( '=== getViewPath for view: ' . $view . ' with spec: ' . print_r($specs,true) . '===' );
        foreach (self::generateSearchPaths($view, $specs) as $path) {
            if (file_exists($path)) {
                //error_log( '=== File found at path: ' . $path . '===' );
                return $path;
            }
            //error_log( 'No file_exists at path: ' . $path . '' );
        }

        return null;
    }

    ////

    /**
     * Build ordered candidate paths:
     *   1) Theme overrides (child → parent)
     *   2) Module-registered root (src/Modules/<Module>/Views)
     *   3) Plugin fallback (wxc/views/<view>.php)
     *
     * @param array{module?:string,post_type?:string,allow_theme?:bool} $specs
     * @return string[]
     */
    protected static function generateSearchPaths(string $view, array $specs = []): array
    {
        //error_log( '=== generateSearchPaths for view: ' . $view . ' with specs: ' . print_r($specs,true) . '===' );
        $paths       = [];
        $kind        = Text::slugify($specs['kind'] ?? '');
        $module      = Text::slugify($specs['module'] ?? '');
        $postType    = Text::slugify($specs['post_type'] ?? '');
        $allowTheme  = $specs['allow_theme'] ?? true;
        //error_log( 'kind: ' . $kind . '' );
        //error_log( 'module: ' . $module . '' );
        //error_log( 'postType: ' . $postType . '' );

        // 1) Theme overrides (child → parent)
        if ( $allowTheme ) {
            foreach ([get_stylesheet_directory(), get_template_directory()] as $root) {
                self::appendPermutations(
                    $paths,
                    rtrim($root, '/'),
                    'wxc',          // theme subdir
                    $module,
                    $postType,
                    $view,
                    true            // include module-specific permutations
                );
            }
        }

        // 2) Module-registered root (e.g., wxc/src/Modules/Supernatural/Views)
        if ($module !== '' && isset(self::$moduleViewRoots[$module])) {
            //error_log( 'self::moduleViewRoots[module]: ' . self::$moduleViewRoots[$module] . '' );
            $root = rtrim(self::$moduleViewRoots[$module], '/');
            if ($postType !== '') {
                if ( $postType == "whx4_event" ) { $postType = "event"; } // Tmp WIP
                $postTypePath = "{$root}/" . Text::studly($postType) . "/{$view}.php";
                //$paths[] = "{$root}/" . Text::studly($postType) . "/{$view}.php"; // Within the Modules dir structure, postTypes are studly caps to match class names
                $paths[] = $postTypePath;
                //error_log( '(WXC-ViewLoader::generateSearchPaths) postTypePath: ' . $postTypePath . '' );
            }
            $paths[] = "{$root}/{$view}.php";
        }

        // 3) Plugin fallback (wxc/views/<view>.php)
        //error_log( 'WXC_PLUGIN_DIR: ' . WXC_PLUGIN_DIR . '' );
        self::appendPermutations(
            $paths,
            rtrim(WXC_PLUGIN_DIR, '/'),
            'views',        // plugin views dir
            $module,
            $postType,
            $view,
            false           // no module-specific permutations under plugin/views
        );

        return $paths;
    }

    /**
     * Append most-specific → least-specific permutations under a root.
     * When $includeModule is false, only "<root>/<subdir>/<view>.php" is appended.
     *
     * @param array<string> $list
     */
    private static function appendPermutations(
        array &$list,
        string $root,
        string $subdir,
        string $module,
        string $postType,
        string $view,
        bool $includeModule
    ): void
    {
        $base = rtrim($root, '/');
        $sub  = trim($subdir, '/');
        $base = $sub !== '' ? "{$base}/{$sub}" : $base;

        if ( $includeModule && $module !== '' ) {
            $list[] = "{$base}/{$module}/{$view}.php";
            if ( $postType !== '' ) {
                $list[] = "{$base}/{$module}/{$postType}/{$view}.php";
                // WIP -- add also single-{cpt}.php, archive-{cpt}.php?
            }
        }

        $list[] = "{$base}/{$view}.php";
    }

    ///////

    /**
     * Normalize the "kind" input to a lowercase string.
     *
     * Accepts:
     *  - null
     *  - raw string (e.g. "posttype")
     *  - ViewKind enum instance
     */
    private static function normalizeKind(mixed $kind): string
    {
        if ($kind instanceof ViewKind) {
            return Text::slugify($kind->value);
        }

        $k = Text::slugify((string) $kind);
        return $k !== '' ? $k : ViewKind::GLOBAL->value;
    }

}
