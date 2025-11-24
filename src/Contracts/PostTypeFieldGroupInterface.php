<?php

namespace atc\WXC\Contracts;

interface PostTypeFieldGroupInterface
{
    /** Base post type, e.g. 'group' */
    public function getPostType(): string;
}
