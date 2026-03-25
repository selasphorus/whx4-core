<?php

declare(strict_types=1);

namespace atc\WXC\Display;

/**
 * Concrete fallback renderer used when no post-type-specific renderer
 * has been registered for a given post type.
 *
 * Also serves as the starting point for new renderers — copy this file
 * to your module's Display/ directory, rename the class, update the
 * namespace, and override only what differs from the base.
 *
 * To register a new renderer:
 *
 *   1. Extend ContentRenderer
 *   2. Call static::registerTitleDefaults() in your handler's boot()
 *   3. Call YourRenderer::register() in your module's boot sequence
 */
final class GenericRenderer extends ContentRenderer
{
    public static function register(string $postType): void
    {
        add_filter('wxc_content_renderer_class', static function (?string $class, string $type) use ($postType): ?string {
            return $type === $postType ? static::class : $class;
        }, 10, 2);
    }
}