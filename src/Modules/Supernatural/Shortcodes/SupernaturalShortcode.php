<?php

namespace atc\WXC\Modules\Supernatural\Shortcodes;

use atc\WXC\Contracts\ShortcodeInterface;
use atc\WXC\App;
use atc\WXC\Utils\ClassInfo;
use atc\WXC\Templates\ViewLoader;

final class SupernaturalShortcode implements ShortcodeInterface
{
    public static function tag(): string
    {
        return 'whoa_supernatural';
    }

    public function render(array $atts = [], string $content = '', string $tag = ''): string
    {
        $info = "";
        
        $ctx = App::ctx();
        $key = ClassInfo::getModuleKey(self::class);
        $module = $ctx->getModule($key);
        if (!$module) {
            return '<p>Supernatural module inactive.</p>';
        }

        $stats = $module->getModuleStats();
        //
        $monsters = $module->findMonstersByColor('blue') ?? [];
        $monsterPosts  = $monsters['posts'] ?? [];

        // Pagination info for the view.
        $pagination = $monsters['pagination'] ?? ['found' => 0, 'max_pages' => 0, 'paged' => 1];

        // Troubleshooting info
        $info .= "[" . $monsters['pagination']['found'] . "] posts found<br />";

        // Handler factory so views can call CPT methods safely.
        $handlerFactory = [PostTypeHandler::class, 'getHandlerForPost'];
        
        // Set the view
        $view = "module-view-test";
        
        $vars = [
            'posts'      => $monsterPosts,
            'handler'    => $handlerFactory,
            //'atts'       => $atts,
            //'pagination' => $pagination,
            'stats' => $stats,
            //'info' => $info, // for TS -- deprecate in favor of:
            // Optionally pass debug through when WXC_DEBUG is on:
            'debug'      => $monsters['debug'] ?? null,
        ];

        return ViewLoader::renderToString(
            $view,
            $vars,
            ['kind' => 'partial', 'module' => 'supernatural'] //, 'post_type' => self::CPT
        );
    }
}
