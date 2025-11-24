<?php

namespace atc\WXC\Traits;

use atc\WXC\Utils\TitleFilter;

trait AppliesTitleArgs
{
    protected function applyTitleArgs( string $postType, array $args ): void
    {
        //TitleFilter::setGlobalArgsForPostType( $postType, $args );
    }
}
