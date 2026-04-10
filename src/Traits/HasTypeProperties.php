<?php

namespace atc\WXC\Traits;

use atc\WXC\Logger;

// TODO: adapt as needed to apply to taxonomies and blocks as well as post types -- ???
trait HasTypeProperties
{
    abstract public static function getConfig(): array;
    abstract public function getType(): string; // 'post_type' or 'taxonomy'

    public static function getSlug(): string
    {
        return static::getConfig()['slug']
            ?? strtolower(basename(str_replace('\\', '/', static::class)));
    }

    public static function getPluralSlug(): string
    {
        return static::getConfig()['plural_slug'] ?? static::getSlug() . 's';
    }

    public function getLabels(): array
	{
		$slug     = static::getSlug();
		// Merge default labels with overrides from handler-specific config
		$labels   = array_merge($this->getDefaultLabels(), static::getConfig()['labels'] ?? []);
		$filtered = apply_filters("wxc_labels_{$slug}", $labels, $slug, $this);
        return apply_filters('wxc_labels', $filtered, $slug, $this);
	}

	public function getDefaultLabels(): array
	{
        $singular = ucwords(str_replace(['_', '-'], ' ', static::getSlug()));
        $plural   = ucwords(str_replace(['_', '-'], ' ', static::getPluralSlug()));

		return [
			'name'               => $plural,
			'singular_name'      => $singular,
			'add_new_item'       => "Add New $singular",
            'edit_item'          => "Edit $singular",
            'new_item'           => "New $singular",
            'view_item'          => "View $singular",
            'view_items'         => "View $plural",
            'search_items'       => "Search $plural",
			'not_found'          => "No $plural found",
			'not_found_in_trash' => "No $plural found in Trash",
		];
	}

    public function getCapabilities(): array
    {
        $capType = static::getConfig()['capability_type'] ?? [];
        if (!is_array($capType)) {
            $capType = [$capType, "{$capType}s"];
        }
        $custom = static::getConfig()['capabilities'] ?? [];
        return array_merge( $this->getDefaultCapabilities( $capType ), $custom );
    }

    public function getDefaultCapabilities( array $capType = [] ): array
    {
        $type     = $this->getType();
        if ( $capType ) {
            $singular = $capType[0];
            $plural = $capType[1];
        } else {
            $singular = static::getSlug();
            $plural   = static::getPluralSlug();
        }
        //Logger::debug( 'type: ' . $type . '; singular: ' . $singular . '; plural: ' . $plural );

        if ( $type === 'taxonomy' ) {
            return [
                'manage_terms' => "manage_{$plural}",
                'edit_terms'   => "edit_{$plural}",
                'delete_terms' => "delete_{$plural}",
                'assign_terms' => "assign_{$plural}",
            ];
        }

		return [
		    "edit_{$plural}",
		    "edit_others_{$plural}",
		    "delete_{$plural}",
		    "publish_{$plural}",
		    "read_private_{$plural}",
		    "delete_private_{$plural}",
		    "delete_published_{$plural}",
		    "delete_others_{$plural}",
		    "edit_private_{$plural}",
		    "edit_published_{$plural}",
		];
    }

	public function isHierarchical(): bool
	{
		$default = $this->getType() === 'taxonomy';
        return static::getConfig()['hierarchical'] ?? $default;
	}

}

