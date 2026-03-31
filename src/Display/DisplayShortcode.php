<?php

declare(strict_types=1);

namespace atc\WXC\Display;

use atc\WXC\Contracts\ShortcodeInterface;
use atc\WXC\Query\PostQuery;
use atc\WXC\Logger;

/**
 * Generic multi-type display shortcode.
 *
 * Provides a [display_posts] shortcode that queries posts via PostQuery
 * and renders them using the appropriate ContentRenderer subclass.
 *
 * For post-type-specific shortcodes (e.g. [whx4_events], [whx4_people]),
 * use dedicated shortcode classes in the relevant module instead.
 *
 * Usage examples:
 *
 *   [display_posts post_type="person" display="grid" cols="3"]
 *   [display_posts post_type="post" taxonomy="category" tax_terms="news" display="list"]
 *   [display_posts post_type="person" group_by="person_category" display="list"]
 */
final class DisplayShortcode implements ShortcodeInterface
{
    public static function tag(): string
    {
        return 'display_posts';
    }

    public function render(array $atts = [], string $content = '', string $tag = ''): string
    {
        Logger::debug( 'shortcode atts: ', $atts, 'shortcodes' );
        
        $atts = shortcode_atts(self::defaults(), $atts, $tag);

        $postType = (string) $atts['post_type'];
        $display  = (string) $atts['display_format'];

        // Run query
        $posts = $this->query($atts);

        if (empty($posts)) {
            return '';
        }

        // Resolve renderer and dispatch
        $renderer = ContentRenderer::resolve($postType);

        // Group_by requires a different rendering path
        if (!empty($atts['group_by'])) {
            return $this->renderGrouped($posts, $atts, $renderer, $display);
        }

        return $renderer->renderItems($posts, $atts, $display);
    }

    // -------------------------------------------------------------------------
    // Defaults
    // -------------------------------------------------------------------------

    /**
     * @return array<string,mixed>
     */
    // TODO: separate out atts that are specific to one display_format (in WXC) or one post_type (in that post type handler class)
    // e.g. [display_posts category="NOT-website-archives" orderby="date" order="DESC" show_subtitles="false" limit="8" context="snippet" do_ts="true"]
    // e.g. [display_posts return_format="grid" post_type="event" orderby="date" order="DESC" ids="374920, 369474, 374893, 374878, 374849, 370424, 370413, 370411" cols="2" debug="true"]
    public static function defaults(): array
    {
        return [
            'post_type'      => 'post',
            'limit'          => 15,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_key'       => null,
            'meta_value'     => null,
            //
            'ids'            => null,
            'slugs'          => null,
            //
            //'category' => null, // for posts/pages only
            'taxonomy'       => null,
            'tax_terms'      => null,
            //
            'display_format' => 'list',   // list | table | grid | archive
            //
            'aspect_ratio' => 'square',
			
			// For grid display_format:
			'cols' => 4,
			'spacing' => 'spaced',
			'header' => false,
			'overlay' => false,
            // For table display_format
            'fields'  => null,
            'headers'  => null,
			//
			'has_image' => false, // set to true to ONLY return posts with features images
            'image_size'     => 'thumbnail',
            'link_posts'     => true,
            'show_images'    => false,
            'show_subtitles' => true,
            'show_content'   => null,     // null | excerpts | full
            'expandable' => false, // for excerpts
            'text_length' => 'excerpt', // excerpt or full length
            'preview_length' => '55',
            //
            'prefer_short_title' => false,
            'scope'          => null, //'all', //'upcoming',
            'group_by'       => null,     // taxonomy slug to group results under
            'class'          => null,
            // For Events or Sermons
            'series' => false,
            //
            'context' => 'general', // wip
        ];
    }

    // -------------------------------------------------------------------------
    // Query
    // -------------------------------------------------------------------------

    /**
     * Run the PostQuery pipeline and return a flat array of WP_Post objects.
     *
     * @param  array $atts  Merged shortcode atts.
     * @return \WP_Post[]
     */
    private function query(array $atts): array
    {
        //$result = PostQuery::find([
        $result = (new PostQuery())->find([
            'post_type'   => $atts['post_type'],
            'limit'       => (int) $atts['limit'],
            'orderby'     => $atts['orderby'],
            'order'       => $atts['order'],
            'meta_key'    => $atts['meta_key'],
            'meta_value'  => $atts['meta_value'],
            'ids'         => $atts['ids'],
            'slugs'       => $atts['slugs'],
            'taxonomy'    => $atts['taxonomy'],
            'tax_terms'   => $atts['tax_terms'],
            'scope'       => $atts['scope'],
        ]);

        return $result['posts'] ?? [];
    }

    // -------------------------------------------------------------------------
    // Grouped rendering
    // -------------------------------------------------------------------------

    /**
     * Render posts grouped by a taxonomy term, with a heading per group.
     *
     * The group_by att accepts a taxonomy slug. Posts are retrieved per term
     * and rendered as separate sections, each headed by the term name.
     * Terms are ordered by a 'sort_num' meta value if present, falling back
     * to the default term order.
     *
     * @param  \WP_Post[]      $posts     Full post set (used for group membership checks).
     * @param  array           $atts      Merged shortcode atts.
     * @param  ContentRenderer $renderer  Resolved renderer for this post type.
     * @param  string          $display   Display variant.
     * @return string
     */
    private function renderGrouped(
        array $posts,
        array $atts,
        ContentRenderer $renderer,
        string $display
    ): string {
        $taxonomy = (string) $atts['group_by'];

        if (!taxonomy_exists($taxonomy)) {
            // Taxonomy not found — fall back to flat rendering
            return $renderer->renderItems($posts, $atts, $display);
        }

        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'orderby'    => 'meta_value_num',
            'meta_key'   => 'sort_num',
            'parent'     => 0,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return $renderer->renderItems($posts, $atts, $display);
        }

        // Build a map of term_id => posts for fast lookup
        $postsByTerm = $this->groupPostsByTerm($posts, $taxonomy);

        $out = '<div class="wxc-display-grouped wxc-display-grouped--' . esc_attr($taxonomy) . '">';

        foreach ($terms as $term) {
            $termPosts = $postsByTerm[$term->term_id] ?? [];

            if (empty($termPosts)) {
                continue;
            }

            $out .= '<section class="wxc-group" id="' . esc_attr($term->slug) . '">';
            $out .= '<h2 class="wxc-group__title">' . esc_html($term->name) . '</h2>';
            $out .= $renderer->renderItems($termPosts, $atts, $display);
            $out .= '</section>';

            // Recurse into child terms
            $out .= $this->renderChildTerms($term->term_id, $taxonomy, $postsByTerm, $atts, $renderer, $display);
        }

        $out .= '</div>';

        return $out;
    }

    /**
     * Render any child terms of a given parent term, at heading level 3.
     *
     * @param  int             $parentTermId
     * @param  string          $taxonomy
     * @param  array           $postsByTerm   term_id => WP_Post[]
     * @param  array           $atts
     * @param  ContentRenderer $renderer
     * @param  string          $display
     * @return string
     */
    private function renderChildTerms(
        int $parentTermId,
        string $taxonomy,
        array $postsByTerm,
        array $atts,
        ContentRenderer $renderer,
        string $display
    ): string {
        $childTerms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'child_of'   => $parentTermId,
        ]);

        if (is_wp_error($childTerms) || empty($childTerms)) {
            return '';
        }

        $out = '';

        foreach ($childTerms as $child) {
            $childPosts = $postsByTerm[$child->term_id] ?? [];

            if (empty($childPosts)) {
                continue;
            }

            $out .= '<section class="wxc-group wxc-group--child" id="' . esc_attr($child->slug) . '">';
            $out .= '<h3 class="wxc-group__title">' . esc_html($child->name) . '</h3>';
            $out .= $renderer->renderItems($childPosts, $atts, $display);
            $out .= '</section>';
        }

        return $out;
    }

    /**
     * Build a map of term_id => WP_Post[] from the full post set.
     *
     * Each post may appear under multiple terms if it belongs to more than one.
     *
     * @param  \WP_Post[] $posts
     * @param  string     $taxonomy
     * @return array<int,\WP_Post[]>
     */
    private function groupPostsByTerm(array $posts, string $taxonomy): array
    {
        $map = [];

        foreach ($posts as $post) {
            $terms = get_the_terms($post->ID, $taxonomy);

            if (!$terms || is_wp_error($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                $map[$term->term_id][] = $post;
            }
        }

        return $map;
    }
}