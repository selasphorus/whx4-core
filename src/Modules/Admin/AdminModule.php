<?php

namespace atc\WXC\Modules\Admin;

use atc\WXC\Module as BaseModule;

// Post Types
use atc\WXC\Modules\Admin\PostTypes\AdminNote;
//use atc\Bkkp\Modules\Admin\PostTypes\YYY;
//use atc\Bkkp\Modules\Admin\PostTypes\ZZZ;

final class AdminModule extends BaseModule
{
    public function boot(): void
    {
        $this->registerDefaultViewRoot();
        parent::boot();
        
        // This extra step ensures that the AdminTag taxonomy will be registered for ALL active CPTs and WXC-registered Core types (Post, Page, Attachment)
        add_filter('wxc_register_taxonomy_handlers', function (array $handlers): array {
			$handlers[] = \atc\WXC\Modules\Admin\Taxonomies\AdminTag::class;
			return $handlers;
		});
        /*add_filter( 'wxc_register_subtypes', function( array $providers ): array {
             // TODO: add use statement above to simplify these lines?
            //$providers[] = new \[PluginName]\Modules\Admin\Subtypes\[SubtypeName]Subtype(); // Subtype of XXX PostType
            return $providers;
        } );*/
    }

    public function getPostTypeHandlerClasses(): array
    {
        return [
            AdminNote::class,
            //YYY::class,
            //ZZZ::class,
        ];
    }
}
