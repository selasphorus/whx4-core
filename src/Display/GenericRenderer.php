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
 */
final class GenericRenderer extends ContentRenderer
{
    protected static string $handlerClass = '';
}