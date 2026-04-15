<?php

declare(strict_types=1);

namespace atc\WXC\Display;

use atc\WXC\Logger;
use atc\WXC\Display\TitleRenderer;

/**
 * Base renderer for lists of WP_Post objects.
 *
 * Provides generic implementations of all standard display variants.
 * Post-type-specific subclasses (e.g. EventRenderer) override only the
 * methods that need to differ — getItemMeta(), renderItem(), or a full
 * variant method like renderTable().
 *
 * Dispatch is handled by renderItems(), which resolves variant-specific
 * methods automatically: it first looks for a method named
 * render{Variant}() on the concrete subclass (or this base), then falls
 * back to renderList(). No registry is required.
 *
 * Usage from a shortcode or handler:
 *
 *   $renderer = ContentRenderer::resolve($postType);
 *   return $renderer->renderItems($posts, $atts, 'table');
 */
abstract class ContentRenderer
{
    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Resolve and instantiate the appropriate renderer for a given post type.
     *
     * Convention: a renderer for post type 'whx4_event' is expected at
     * atc\WHx4\Modules\Events\Display\EventRenderer, but this method does
     * not need to know that. It asks each registered plugin's module loader
     * to supply a renderer class via the filter below, then falls back to
     * GenericRenderer if nothing is found.
     *
     * Filter: 'wxc_content_renderer_class'
     *   @param string|null $class     Fully-qualified class name, or null.
     *   @param string      $postType  The post type slug.
     *
     * @param  string $postType  Post type slug (e.g. 'whx4_event', 'post').
     * @return static
     */
    public static function resolve(string $postType): static
    {
        $class = apply_filters('wxc_content_renderer_class', null, $postType);

        if ($class && is_string($class) && is_subclass_of($class, static::class)) {
            return new $class();
        }

        return new GenericRenderer();
    }

    // -------------------------------------------------------------------------
    // Primary dispatch
    // -------------------------------------------------------------------------

    /**
     * Render a collection of posts in the requested variant.
     *
     * Variant resolution order:
     *   1. render{Variant}() on the concrete subclass       — e.g. renderTable()
     *   2. renderList() as the universal fallback
     *
     * This means a subclass that only overrides renderTable() still gets the
     * base renderList() and renderGrid() implementations for free.
     *
     * @param  \WP_Post[] $posts
     * @param  array      $atts   Shortcode / caller atts (passed through to render methods).
     * @param  string     $variant  'list' | 'table' | 'grid' | 'archive' | any custom value.
     * @return string
     */
    public function renderItems(array $posts, array $atts, string $variant): string
    {
        $method = 'render' . ucfirst(strtolower($variant));
        Logger::debug( 'method: '.$method, null, ['display', 'shortcodes'] );

        if ($method !== 'renderItems' && method_exists($this, $method)) {
            return $this->$method($posts, $atts);
        }

        return $this->renderList($posts, $atts);
    }

    // -------------------------------------------------------------------------
    // Variant renderers — override any of these in a subclass
    // -------------------------------------------------------------------------

    /**
     * Render posts as a `<ul>` list.
     *
     * @param  \WP_Post[] $posts
     * @param  array      $atts
     * @return string
     */
    public function renderList(array $posts, array $atts): string
    {
        $type = $this->postTypeClass();

        if (!$posts) {
            return $this->emptyMessage('list', $type);
        }

        $out = '<ul class="wxc-list wxc-list--' . esc_attr($type) . '">';
        foreach ($posts as $post) {
            $out .= '<li>' . $this->renderItem($post, $atts) . '</li>';
        }
        $out .= '</ul>';

        return $out;
    }

    /**
     * Render posts as an HTML table.
     *
     * The base implementation produces a two-column Date / Title table.
     * Subclasses should override this (and/or getTableColumns()) to
     * produce type-appropriate columns.
     *
     * @param  \WP_Post[] $posts
     * @param  array      $atts
     * @return string
     */
    public function renderTable(array $posts, array $atts): string
    {
        $type = $this->postTypeClass();

        if (!$posts) {
            return $this->emptyMessage('table', $type);
        }

        $columns = $this->getTableColumns();
        $out     = '<table class="wxc-table wxc-table--' . esc_attr($type) . '">';
        $out    .= '<thead><tr>';

        foreach ($columns as $label) {
            $out .= '<th>' . esc_html($label) . '</th>';
        }

        $out .= '</tr></thead><tbody>';

        foreach ($posts as $post) {
            $out .= $this->renderTableRow($post, $atts);
        }

        $out .= '</tbody></table>';

        return $out;
    }

    /**
     * Render posts as a flex grid.
     *
     * @param  \WP_Post[] $posts
     * @param  array      $atts
     * @return string
     */
    public function renderGrid(array $posts, array $atts): string
    {
        $type = $this->postTypeClass();
        $cols = isset($atts['cols']) ? (int) $atts['cols'] : 3;
        
        Logger::debug( 'type: '.$type, null, ['display', 'shortcodes'] );

        if (!$posts) {
            return $this->emptyMessage('grid', $type);
        }

        $out = '<div class="wxc-grid wxc-grid--' . esc_attr($type) . ' wxc-grid--cols-' . $cols . ' flex-container">'; // TODO: simplify classes?
        foreach ($posts as $post) {
            $out .= '<div class="wxc-grid__item flex-box">' . $this->renderItem($post, $atts) . '</div>';
        }
        $out .= '</div>';

        return $out;
    }

    /**
     * Render posts grouped by a date period (year by default).
     *
     * Subclasses can override getArchiveGroupKey() to change the grouping
     * without replacing the entire method.
     *
     * @param  \WP_Post[] $posts
     * @param  array      $atts
     * @return string
     */
    public function renderArchive(array $posts, array $atts): string
    {
        $type = $this->postTypeClass();

        if (!$posts) {
            return $this->emptyMessage('archive', $type);
        }

        $grouped = [];
        foreach ($posts as $post) {
            $key            = $this->getArchiveGroupKey($post);
            $grouped[$key][] = $post;
        }

        $out = '<div class="wxc-archive wxc-archive--' . esc_attr($type) . '">';
        foreach ($grouped as $groupLabel => $groupPosts) {
            $out .= '<section class="wxc-archive__group">';
            $out .= '<h2 class="wxc-archive__group-title">' . esc_html((string) $groupLabel) . '</h2>';
            $out .= $this->renderList($groupPosts, $atts);
            $out .= '</section>';
        }
        $out .= '</div>';

        return $out;
    }

    // -------------------------------------------------------------------------
    // Item-level rendering — override these in subclasses for custom markup
    // -------------------------------------------------------------------------

    /**
     * Render a single post item (used by renderList and renderGrid).
     *
     * Composes title, image, and meta. Subclasses can override this entirely
     * or override the individual getItem*() helpers.
     *
     * @param  \WP_Post $post
     * @param  array    $atts
     * @return string
     */
    protected function renderItem(\WP_Post $post, array $atts): string
    {
        $title = $this->getItemTitle($post, $atts);
        $meta  = $this->getItemMeta($post, $atts);
        $image = $this->getItemImage($post, $atts);

        $out  = $image;
        $out .= $title;

        if ($meta) {
            $out .= '<span class="wxc-item__meta">' . $meta . '</span>';
        }

        return '<span class="wxc-item">' . $out . '</span>';
    }

    /**
     * Render a single table row (used by renderTable).
     *
     * Override this in a subclass when the row structure differs from the
     * base two-column Date / Title layout — or override getTableColumns()
     * and getTableCells() together for a data-only change.
     *
     * @param  \WP_Post $post
     * @param  array    $atts
     * @return string
     */
    protected function renderTableRow(\WP_Post $post, array $atts): string
    {
        $cells = $this->getTableCells($post, $atts);
        $out   = '<tr>';
        foreach ($cells as $cell) {
            $out .= '<td>' . $cell . '</td>';
        }
        $out .= '</tr>';

        return $out;
    }

    // -------------------------------------------------------------------------
    // Data helpers — the primary override points for subclasses
    // -------------------------------------------------------------------------

    /**
     * Return the linked post title.
     *
     * @param  \WP_Post $post
     * @param  array    $atts
     * @return string
     */
    protected function getItemTitle(\WP_Post $post, array $atts): string
	{
		return TitleRenderer::render($post, $atts);
	}

    /**
     * Return the post thumbnail HTML, or empty string if none.
     *
     * @param  \WP_Post $post
     * @param  array    $atts
     * @return string
     */
    protected function getItemImage(\WP_Post $post, array $atts): string
	{
		$size = $atts['image_size'] ?? 'thumbnail';
	
		return (string) apply_filters('wxc_post_image', '', $post, $size, $atts);
	}

    /**
     * Return additional meta markup for a post item.
     *
     * The base implementation returns an empty string. Subclasses override
     * this to surface type-specific meta (dates, location, job title, etc.)
     * without touching the item or list rendering logic.
     *
     * @param  \WP_Post $post
     * @param  array    $atts
     * @return string   HTML or empty string.
     */
    protected function getItemMeta(\WP_Post $post, array $atts): string
    {
        return '';
    }

    /**
     * Return column header labels for the table variant.
     *
     * Override in subclasses to change column names or add/remove columns.
     * Must stay in sync with getTableCells().
     *
     * @return string[]
     */
    protected function getTableColumns(): array
    {
        return ['Title'];
    }

    /**
     * Return table cell values for a single post row.
     *
     * Must stay in sync with getTableColumns().
     *
     * @param  \WP_Post $post
     * @param  array    $atts
     * @return string[]
     */
    protected function getTableCells(\WP_Post $post, array $atts): array
    {
        return [
            '<a href="' . esc_url(get_permalink($post)) . '">' . esc_html(get_the_title($post)) . '</a>',
        ];
    }

    /**
     * Return the grouping key for a post in the archive variant.
     *
     * Defaults to the post's publication year. Subclasses can override to
     * group by event start year, custom taxonomy term, or any other value.
     *
     * @param  \WP_Post $post
     * @return string|int
     */
    protected function getArchiveGroupKey(\WP_Post $post): string|int
    {
        return get_the_date('Y', $post);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * A CSS-safe identifier derived from the concrete class name.
     *
     * EventRenderer → 'event', GenericRenderer → 'generic', etc.
     *
     * @return string
     */
    protected function postTypeClass(): string
    {
        $short = (new \ReflectionClass($this))->getShortName();
        return strtolower(str_replace('Renderer', '', $short));
    }

    /**
     * Build a standard empty-state message.
     *
     * @param  string $variant  'list' | 'table' | 'grid' | 'archive'
     * @param  string $type     Post type label (for CSS class).
     * @return string
     */
    protected function emptyMessage(string $variant, string $type): string
    {
        return '<div class="wxc-' . esc_attr($variant) . ' wxc-' . esc_attr($variant) . '--' . esc_attr($type) . ' is-empty">'
             . esc_html__('No items found.', 'wxc')
             . '</div>';
    }
}