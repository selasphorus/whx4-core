<?php
/**
 * Interface for all WXC ACF Field Groups.
 *
 * Developers: Please follow WXC Field Group standards.
 * See: /docs/FieldGroupStandards.md
 */

namespace atc\WXC\Contracts;

interface FieldGroupInterface
{
    public static function register(): void;
    //public static function getPostTypes(): array;
}
