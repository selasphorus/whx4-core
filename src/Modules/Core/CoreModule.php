<?php

namespace atc\WXC\Modules\Core;

use atc\WXC\Module as BaseModule;

// Post Types
use atc\WXC\Modules\Core\PostTypes\Post;
use atc\WXC\Modules\Core\PostTypes\Page;
use atc\WXC\Modules\Core\PostTypes\Attachment;

final class CoreModule extends BaseModule
{
    public function boot(): void
    {
        $this->registerDefaultViewRoot();

        parent::boot();
    }

    /** @return array<string, class-string> */
    public function getPostTypeHandlerClasses(): array
    {
        return [
            'post'       => Post::class,
            'page'       => Page::class,
            'attachment' => Attachment::class,
        ];
    }
}
