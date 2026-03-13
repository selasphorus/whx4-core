<?php

declare(strict_types=1);

namespace atc\WXC\Traits;

//use atc\WXC\Utils\TitleFilter;

/**
 * Allows a post type handler to register default title rendering args
 * for its post type. These become the middle tier in TitleRenderer's
 * three-level merge: call-site args > post-type defaults > global defaults.
 *
 * Usage in a handler's boot() method:
 *
 *   protected static function titleDefaults(): array
 *   {
 *       return [
 *           'show_subtitle' => true,
 *           'hlevel'        => 2,
 *       ];
 *   }
 *
 * The trait's registerTitleDefaults() call wires those into the filter
 * that TitleRenderer reads. Call it from the handler's boot() method:
 *
 *   self::registerTitleDefaults(static::getSlug(), array $defaults);
 */
trait AppliesTitleArgs
{    
    /**
     * Register this handler's title defaults with TitleRenderer.
     *
     * @param string $postType  The post type slug.
     */
    protected static function registerTitleDefaults(string $postType, array $defaults): void
	{
		if (empty($defaults)) {
			return;
		}
	
		add_filter(
			'wxc_title_defaults_' . $postType,
			static fn(array $existing): array => wp_parse_args($defaults, $existing),
			10
		);
	}
}