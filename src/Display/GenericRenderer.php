<?php

declare(strict_types=1);

namespace atc\WXC\Display;

/**
 * Concrete fallback renderer used when no post-type-specific renderer
 * has been registered for a given post type.
 *
 * Also serves as the canonical starting point for new renderers — copy
 * this file to your module's Display/ directory, rename the class, update
 * the namespace, and override only what differs from the base.
 *
 * To create and register a new renderer:
 *
 *   1. Extend ContentRenderer
 *   2. Declare the associated post type handler:
 *        protected static string $handlerClass = YourPostTypeHandler::class;
 *   3. Call YourRenderer::register() in your module's boot sequence
 *   4. Override only the methods that differ from the base:
 *        - getItemMeta()        Surface type-specific meta (dates, job title, etc.)
 *        - getTableColumns()    Change column headers (keep in sync with getTableCells)
 *        - getTableCells()      Populate those columns
 *        - getArchiveGroupKey() Change archive grouping (default: publication year)
 *        - renderItem()         Full control over individual item markup
 *        - renderCard()         Full control over grid card markup
 *
 * Extension point for non-OOP plugins:
 *
 *   Procedural plugins (e.g. SDG, MLib) have no renderer class to subclass
 *   and therefore always fall through to this renderer via
 *   ContentRenderer::resolve(). getItemMeta() wraps its default in a
 *   per-post-type filter, so a procedural plugin's main file can hook in
 *   with a plain add_filter() call — no class required:
 *
 *     add_filter('wxc_item_meta_sermon', function (string $meta, \WP_Post $post, array $atts): string {
 *         return get_post_meta($post->ID, 'sermon_date', true);
 *     }, 10, 3);
 */
final class GenericRenderer extends ContentRenderer
{
    protected static string $handlerClass = '';

    /**
     * {@inheritDoc}
     *
     * Allows procedural plugins to supply item meta via
     * `wxc_item_meta_{$post_type}`, since they have no renderer subclass
     * to override this method on.
     */
    protected function getItemMeta(\WP_Post $post, array $atts): string
    {
        return (string) apply_filters(
            "wxc_item_meta_{$post->post_type}",
            parent::getItemMeta($post, $atts),
            $post,
            $atts
        );
    }
}