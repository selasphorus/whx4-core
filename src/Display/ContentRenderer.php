<?php

declare(strict_types=1);

namespace atc\WXC\Display;

use atc\WXC\Logger;
use atc\WXC\Display\TitleRenderer;
use atc\WXC\Utils\Text;

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
 * CSS naming convention: BEM with wxc- prefix for WordPress directory safety.
 *   .wxc-grid                  — grid container
 *   .wxc-grid--{type}          — post-type modifier (e.g. wxc-grid--event)
 *   .wxc-grid--{cols}col       — column count modifier (e.g. wxc-grid--threecol)
 *   .wxc-grid--{aspect}        — aspect ratio modifier (e.g. wxc-grid--square)
 *   .wxc-card                  — individual grid/card item
 *   .wxc-card--{aspect}        — aspect ratio modifier on card
 *   .wxc-card__image           — image container within card
 *   .wxc-card__info            — text/meta container within card
 *   .wxc-card__overlay         — overlay info layer (hover-capable devices)
 *   .wxc-list                  — list variant container
 *   .wxc-table                 — table variant container
 *   .wxc-archive               — archive variant container
 *
 * Usage from a shortcode or handler:
 *
 *   $renderer = ContentRenderer::resolve($postType);
 *   return $renderer->renderItems($posts, $atts, 'grid');
 */
abstract class ContentRenderer
{
    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Resolve and instantiate the appropriate renderer for a given post type.
     *
     * Asks each registered plugin's module loader to supply a renderer class
     * via the filter below, then falls back to GenericRenderer if nothing found.
     * ///
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

    /**
     * Register this renderer for a post type via the wxc_content_renderer_class filter.
     *
     * Call from a module's boot() method, passing the post type slug:
     *
     *   EventRenderer::register( Event::getSlug() );
     *
     * @param string $postType  Post type slug to claim (e.g. 'whx4_event').
     */
    public static function register(string $postType): void
    {
        add_filter(
            'wxc_content_renderer_class',
            static function (?string $class, string $type) use ($postType): ?string {
                return $type === $postType ? static::class : $class;
            },
            10,
            2
        );
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
     * @param  \WP_Post[] $posts
     * @param  array      $atts   Shortcode / caller atts (passed through to render methods).
     * @param  string     $variant  'list' | 'table' | 'grid' | 'archive' | any custom value.
     * @return string
     */
    public function renderItems(array $posts, array $atts, string $variant): string
    {
        $method = 'render' . ucfirst(strtolower($variant));
        //Logger::debug( 'method: '.$method, null, ['display', 'shortcodes'] );

        if ($method !== 'renderItems' && method_exists($this, $method)) {
            return $this->$method($posts, $atts);
        }

        return $this->renderList($posts, $atts);
    }

    // -------------------------------------------------------------------------
    // Variant renderers — override any of these in a subclass
    // -------------------------------------------------------------------------

    /**
     * Render posts as a <ul> list.
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
            $out .= '<li>' . $this->resolvePostRenderer($post)->renderItem($post, $atts) . '</li>';
        }
        $out .= '</ul>';

        return $out;
    }

    /**
     * Render posts as an HTML table.
     *
     * The base implementation produces a single Title column.
     * Subclasses override getTableColumns() and getTableCells() to
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
            $out .= $this->resolvePostRenderer($post)->renderTableRow($post, $atts);
        }

        $out .= '</tbody></table>';

        return $out;
    }

    /**
     * Render posts as a card grid.
     *
     * Produces a .wxc-grid container with .wxc-card items. Each card is
     * rendered by renderCard(), which separates image and info into
     * .wxc-card__image and .wxc-card__info wrappers.
     *
     * Supported atts:
     *   cols         int     Number of columns. Default 3.
     *   aspect_ratio string  'square' | 'landscape' | 'portrait'. Default 'square'.
     *   spacing      string  Optional spacing modifier class.
     *   overlay      string  'true' | 'fullover' | null. Enables hover overlay.
     *   hlevel       int     Heading level for card titles. Default 3.
     *
     * @param  \WP_Post[] $posts
     * @param  array      $atts
     * @return string
     */
    public function renderGrid(array $posts, array $atts): string
    {
        $type = $this->postTypeClass();
        //Logger::debug( 'type: '.$type, null, ['display', 'shortcodes'] );

        if (!$posts) {
            return $this->emptyMessage('grid', $type);
        }

        // Default heading level to h3 for grid contexts
        $atts['hlevel'] = (int) ($atts['hlevel'] ?? 3);

        $cols        = (int) ($atts['cols'] ?? 3);
        $aspectRatio = $atts['aspect_ratio'] ?? 'square';
        $colWord     = Text::digitToWord($cols);

        $containerClasses = implode(' ', array_filter([
            'wxc-grid',
            'wxc-grid--' . esc_attr($type),
            'wxc-grid--' . esc_attr($colWord) . 'col',
            'wxc-grid--' . esc_attr($aspectRatio),
            !empty($atts['spacing']) ? 'wxc-grid--' . esc_attr($atts['spacing']) : null,
        ]));
        
        // WIP
		if ( $atts['overlay'] == "true" || $atts['overlay'] == "fullover" ) {
			$overclass = "overlay";
			$containerClasses .= " overlaid";
			if ( $atts['overlay'] == "fullover" ) { $overclass .= " fullover"; }
		} else {
			$overclass = null;
		}
    
        $out = '<div class="' . $containerClasses . '">';
        foreach ($posts as $post) {
            $out .= $this->resolvePostRenderer($post)->renderCard($post, $atts);
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
            $out .= '<ul class="wxc-list wxc-list--' . esc_attr($type) . '">';
            foreach ($groupPosts as $post) {
                $out .= '<li>' . $this->resolvePostRenderer($post)->renderItem($post, $atts) . '</li>';
            }
            $out .= '</ul>';
            $out .= '</section>';
        }
        $out .= '</div>';

        return $out;
    }

    // -------------------------------------------------------------------------
    // Item-level rendering
    // -------------------------------------------------------------------------

    /**
     * Render a single post item for list and archive contexts.
     *
     * Composes title, image, and meta inline. For grid contexts,
     * renderCard() is used instead.
     *
     * Subclasses can override this entirely or override the individual
     * getItem*() helpers.
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

        $out = '';

        if ($image) {
            $out .= $image;
        }

        $out .= $title;

        if ($meta) {
            $out .= '<span class="wxc-item__meta">' . $meta . '</span>';
        }

        return $out;
    }

    /**
     * Render a single post as a card for grid contexts.
     *
     * Produces the .wxc-card structure with separate __image and __info
     * containers. When overlay is enabled, info is placed in a
     * .wxc-card__overlay div instead of .wxc-card__info.
     *
     * @param  \WP_Post $post
     * @param  array    $atts
     * @return string
     */
    protected function renderCard(\WP_Post $post, array $atts): string
    {
        $aspectRatio = $atts['aspect_ratio'] ?? 'square';
        $overlay     = $atts['overlay'] ?? null;

        $cardClasses = implode(' ', array_filter([
            'wxc-card',
            'wxc-card--' . esc_attr($aspectRatio),
            ($overlay === 'true' || $overlay === 'fullover') ? 'wxc-card--overlaid' : null,
        ]));

        // Resolve all content before building markup
        $image   = $this->getItemImage($post, $atts);
        $title   = $this->getItemTitle($post, $atts);
        $meta    = $this->getItemMeta($post, $atts);

        $infoHtml  = $title;
        if ($meta) {
            $infoHtml .= '<span class="wxc-card__meta">' . $meta . '</span>';
        }

        $out = '<div class="' . $cardClasses . '">';

        if ($image) {
            $out .= '<div class="wxc-card__image hoverZoom">' . $image . '</div>';
        }

        if ($overlay === 'true' || $overlay === 'fullover') {
            $overlayClass = 'wxc-card__overlay';
            if ($overlay === 'fullover') {
                $overlayClass .= ' wxc-card__overlay--full';
            }
            $out .= '<div class="' . $overlayClass . '">' . $infoHtml . '</div>';
        } else {
            $out .= '<div class="wxc-card__info">' . $infoHtml . '</div>';
        }

        $out .= '</div>';

        return $out;
    }

    /**
     * Render a single table row (used by renderTable).
     *
     * Override this in a subclass when the row structure differs, or
     * override getTableColumns() and getTableCells() for a data-only change.
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
     * Return the rendered post title HTML.
     *
     * Delegates to TitleRenderer::render() which handles subtitle, heading
     * level, link wrapping, and formatting transforms. Post-type defaults
     * registered via AppliesTitleArgs are applied automatically.
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
     * Return the post image HTML via the wxc_post_image filter.
     *
     * Image size is derived from the image_size att if set, then from
     * aspect_ratio (e.g. 'square' → 'grid_crop_square'), then falls
     * back to 'thumbnail'.
     *
     * @param  \WP_Post $post
     * @param  array    $atts
     * @return string
     */
    protected function getItemImage(\WP_Post $post, array $atts): string
	{
        if (!empty($atts['image_size'])) {
            $size = $atts['image_size'];
        } elseif (!empty($atts['aspect_ratio'])) {
            $size = 'grid_crop_' . $atts['aspect_ratio'];
        } else {
            $size = 'thumbnail';
        }
	
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

    /** @var array<string,string> Cache: FQCN => CSS type string */
    private static array $typeClassCache = [];

    /**
     * Resolve the appropriate renderer for a single post.
     *
     * Returns $this for homogeneous collections where the post type matches
     * the renderer's own type, or when using GenericRenderer as the entry
     * point for a mixed-type collection. Otherwise resolves and returns the
     * correct subclass renderer for the post's type, enabling mixed-type
     * collections to render each post with its own type-specific renderer.
     *
     * @param  \WP_Post $post
     * @return static
     */
    protected function resolvePostRenderer(\WP_Post $post): static
    {
        if ($this instanceof GenericRenderer || $post->post_type === $this->postTypeClass()) {
            return $this;
        }
        return static::resolve($post->post_type);
    }

    /**
     * A CSS-safe identifier derived from the concrete class name.
     *
     * Computed once per class per request and cached as a static property,
     * consistent with the caching pattern used in PostTypeHandler.
     * EventRenderer → 'event', GenericRenderer → 'generic', etc.
     * Used as a BEM modifier on container elements.
     *
     * @return string
     */
    protected function postTypeClass(): string
    {
        $class = static::class;
        if (!isset(self::$typeClassCache[$class])) {
            $short = substr($class, strrpos($class, '\\') + 1);
            self::$typeClassCache[$class] = strtolower(str_replace('Renderer', '', $short));
        }
        return self::$typeClassCache[$class];
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