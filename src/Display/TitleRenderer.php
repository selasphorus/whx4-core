<?php

declare(strict_types=1);

namespace atc\WXC\Display;

use atc\WXC\Logger;

/**
 * Renders post titles with optional formatting, enrichment, and wrapping.
 *
 * This is the single canonical place for title rendering logic across the
 * WXC/WHx4 ecosystem. Both TitleFilter (singular view pipeline) and
 * ContentRenderer::getItemTitle() (list/grid/table contexts) delegate here.
 *
 * Rendering pipeline (in order):
 *   1. Resolve raw title string (short_title meta or post_title)
 *   2. Format         — emphasis markup, pipe/bracket transformations
 *   3. Enrich         — subtitle, any post-type-specific additions
 *   4. Wrap           — prepend/append, link, heading element
 *
 * Args (all optional, merged over post-type defaults then global defaults):
 *
 *   prefer_short_title  bool    Use short_title meta if present. Default false.
 *   line_breaks         bool    Convert pipes to <br>. Default false.
 *   show_subtitle       bool    Append subtitle meta below title. Default false.
 *   link                bool    Wrap title in permalink anchor. Default false.
 *   hlevel              int|0   Wrap in <h{n}>. 0 = no heading element. Default 0.
 *   hclass              string  Class on heading element. Default 'entry-title'.
 *   hlevel_sub          int|0   Heading level for subtitle. 0 = <span>. Default 0.
 *   hclass_sub          string  Class on subtitle element. Default 'subtitle'.
 *   prepend             string  HTML prepended before the title. Default ''.
 *   append              string  HTML appended after the title. Default ''.
 *   the_title           string  Override: supply a title string directly.
 *
 * Usage:
 * 1. via ContentRenderer::getItemTitle()
 * 2. Direct static call:
 *        echo TitleRenderer::render(get_post(), ['link' => true, 'hlevel' => 2]);
 *        echo TitleRenderer::render(get_post($postId), ['show_subtitle' => true]);
 * 3. Potentially via a wrapper function:
    function wxc_the_title(\WP_Post|int $post, array $atts = []): string
	{
		$post = is_int($post) ? get_post($post) : $post;
		if (!$post instanceof \WP_Post) {
			return '';
		}
		return TitleRenderer::render($post, $atts);
	}
 */
class TitleRenderer
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Render a post title according to the supplied args.
     *
     * @param  \WP_Post $post
     * @param  array    $atts  Call-site args. Merged over registered defaults.
     * @return string
     */
    public static function render(\WP_Post $post, array $atts = []): string
    {
        $args = self::resolveArgs($post, $atts);

        // 1. Resolve raw title
        $title = self::resolveRawTitle($post, $args);
        //Logger::debug( 'title: '.$title, null, ['display'] );

        if ($title === '') {
            return '';
        }

        // 2. Format
        $title = self::format($title, $args);
        //Logger::debug( 'formatted title: '.$title, null, ['display'] );

        // 3. Enrich
        $subtitle = self::resolveSubtitle($post, $args);

        // 4. Wrap
        return self::wrap($post, $title, $subtitle, $args);
    }

    // -------------------------------------------------------------------------
    // Arg resolution
    // -------------------------------------------------------------------------

    /**
     * Global defaults. These are the lowest-priority values; everything
     * overrides them.
     *
     * @return array<string,mixed>
     */
    public static function defaults(): array
    {
        return [
            'prefer_short_title' => false,
            'line_breaks'        => false,
            'show_subtitle'      => false,
            'link'               => false,
            'hlevel'             => 0,
            'hclass'             => 'entry-title',
            'hlevel_sub'         => 0,
            'hclass_sub'         => 'subtitle',
            'prepend'            => '',
            'append'             => '',
            'the_title'          => null,
        ];
    }

    /**
     * Merge call-site args over post-type defaults over global defaults.
     *
     * Post-type defaults are published via the filter
     * 'wxc_title_defaults_{post_type}', which AppliesTitleArgs writes to
     * during handler boot.
     *
     * @param  \WP_Post $post
     * @param  array    $atts  Call-site overrides.
     * @return array<string,mixed>
     */
    public static function resolveArgs(\WP_Post $post, array $atts): array
    {
        $postTypeDefaults = apply_filters(
            'wxc_title_defaults_' . $post->post_type,
            []
        );

        return wp_parse_args($atts, wp_parse_args($postTypeDefaults, self::defaults()));
    }

    // -------------------------------------------------------------------------
    // Pipeline steps
    // -------------------------------------------------------------------------

    /**
     * Step 1 — resolve the raw title string.
     *
     * If 'the_title' is supplied in args it takes priority.
     * Otherwise, uses short_title meta when prefer_short_title is true and
     * the meta value exists, falling back to post_title.
     */
    private static function resolveRawTitle(\WP_Post $post, array $args): string
    {
        if (!empty($args['the_title'])) {
            return (string) $args['the_title'];
        }

        if ($args['prefer_short_title']) {
            $short = get_post_meta($post->ID, 'short_title', true);
            if ($short !== '') {
                return (string) $short;
            }
        }

        return $post->post_title;
    }

    /**
     * Step 2 — apply string formatting transforms.
     */
    private static function format(string $title, array $args): string
    {
        // Emphasis markup — delimiter pairs => open/close tags.
        // Defaults are filterable; a CMS settings page can write to the
        // underlying option and return it through this same filter.
        $delimiters = apply_filters('wxc_title_emphasis_delimiters', [
            '//'   => ['<span class="emtitle">', '</span>'],
            '{}'   => ['<span class="emtitle">', '</span>'],
            '[[]]' => ['<span class="emtitle">', '</span>'],
        ]);

        foreach ($delimiters as $delimiter => $tags) {
            $title = self::applyDelimiterPair($title, $delimiter, $tags[0], $tags[1]);
        }

        // Pipe handling — convert to <br> or collapse to space
        if ($args['line_breaks']) {
            $title = str_replace('|', '<br>', $title);
        } else {
            $title = preg_replace('/\s*\|\s*/', ' ', $title);
        }

        // Strip legacy bracketed content e.g. "[uid_123]"
        $title = self::removeBracketedInfo($title);

        return $title;
    }

    /**
     * Step 3 — resolve subtitle HTML, or empty string if not shown.
     */
    private static function resolveSubtitle(\WP_Post $post, array $args): string
    {
        if (!$args['show_subtitle']) {
            return '';
        }

        $subtitle = (string) get_post_meta($post->ID, 'subtitle', true);

        if ($subtitle === '') {
            return '';
        }

        $subtitle = esc_html($subtitle);
        $level    = (int) $args['hlevel_sub'];
        $class    = esc_attr((string) $args['hclass_sub']);

        if ($level > 0) {
            return '<h' . $level . ' class="' . $class . '">' . $subtitle . '</h' . $level . '>';
        }

        return '<span class="' . $class . '">' . $subtitle . '</span>';
    }

    /**
     * Step 4 — apply prepend/append, link wrapping, and heading element.
     */
    private static function wrap(
        \WP_Post $post,
        string $title,
        string $subtitle,
        array $args
    ): string
    {
        // Prepend / append raw strings
        $title = $args['prepend'] . $title . $args['append'];

        // Link wrapping
        if ($args['link']) {
            $title = '<a href="' . esc_url(get_permalink($post)) . '" rel="bookmark">'
                   . $title
                   . '</a>';
        }

        // Heading element
        $level = (int) $args['hlevel'];
        $class = esc_attr((string) $args['hclass']);

        if ($level > 0) {
            Logger::debug( 'level: '.$level, null, ['display'] );
            $title = '<h' . $level . ' class="' . $class . '">' . $title . '</h' . $level . '>';
        }

        // Subtitle follows the heading
        return $title . $subtitle;
    }

    // -------------------------------------------------------------------------
    // Formatting helpers
    // -------------------------------------------------------------------------

    /**
     * Apply a single open/close delimiter pair to a string.
     *
     * Supports two delimiter formats:
     *   - Paired identical delimiters e.g. '//' wraps as: //text// => <open>text<close>
     *   - Bracketed pairs e.g. '[[]]' where the first half is the opening
     *     delimiter and the second half is the closing delimiter.
     */
    private static function applyDelimiterPair(
        string $str,
        string $delimiter,
        string $open,
        string $close
    ): string {
        $len  = strlen($delimiter);
        $half = (int) ($len / 2);

        // Bracketed pair: '[[]]', '{}' etc. — split at midpoint
        if ($len % 2 === 0 && $len > 2) {
            $opening = substr($delimiter, 0, $half);
            $closing = substr($delimiter, $half);
            return str_replace([$opening, $closing], [$open, $close], $str);
        }

        // Repeated delimiter: '//' — every even occurrence is open, odd is close
        // Use preg_replace to swap paired occurrences
        $quoted = preg_quote($delimiter, '/');
        return preg_replace(
            '/(' . $quoted . ')(.*?)(' . $quoted . ')/s',
            $open . '$2' . $close,
            $str
        ) ?? $str;
    }

    /**
     * Strip content inside square brackets, including the brackets themselves.
     * Used to remove legacy UID annotations e.g. "[uid_123]" or "[archived]".
     */
    private static function removeBracketedInfo(string $str): string
    {
        if (!str_contains($str, '[')) {
            return $str;
        }

        return trim(preg_replace('/\[[^\]]*\]/', '', $str) ?? $str);
    }
}