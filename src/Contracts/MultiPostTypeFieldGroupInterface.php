<?php

namespace atc\WXC\Contracts;

/**
 * Interface for ACF field groups that target multiple post types
 * not owned by a single module (e.g. a group spanning CPTs across
 * Events, Projects, and other modules).
 *
 * Use PostTypeFieldGroupInterface instead when a field group is
 * scoped to exactly one post type.
 */
interface MultiPostTypeFieldGroupInterface
{
    /**
     * Post type slugs this field group targets.
     *
     * @return string[]
     */
    public function getPostTypes(): array;
}
