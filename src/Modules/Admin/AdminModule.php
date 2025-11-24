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
