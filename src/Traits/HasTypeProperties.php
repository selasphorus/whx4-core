<?php

namespace atc\WXC\Traits;

// TODO: adapt as needed to apply to taxonomies and blocks as well as post types -- ???
trait HasTypeProperties
{
	abstract public function getConfig(): array;
    abstract public function getType(): string; // 'post_type' or 'taxonomy'

    public function getSlug(): string
    {
        //error_log( 'HasTypeProperties::getSlug() config: ' . print_r( $this->getConfig(), true ) );
        return $this->getConfig()['slug'] ?? strtolower( basename( str_replace( '\\', '/', static::class ) ) );
    }

    public function getPluralSlug(): string
    {
        return $this->getConfig()['plural_slug'] ?? $this->getSlug() . 's';
    }

    public function getLabels(): array
	{
		//error_log('=== getLabels() ===');
		$slug = $this->getSlug();
		$defaults = $this->getDefaultLabels();
		$overrides = $this->getConfig()['labels'] ?? [];
		// Merge defaults with overrides
		$labels = array_merge($defaults, $overrides);

		// Troubleshooting...
		//error_log( 'default labels: ' . print_r( $defaults, true ) );
		//error_log( 'override labels: ' . print_r( $overrides, true ) );
    	//error_log( 'labels (merged): ' . print_r( $labels, true ) );

    	// Filter the array
		$filtered = apply_filters("wxc_labels_{$slug}", $labels, $slug, $this);
		return apply_filters("wxc_labels", $filtered, $slug, $this);
	}

	public function getDefaultLabels(): array
	{
		//$singular = ucfirst( $this->getSlug() );
        $singular = ucwords (str_replace(['_', '-'], ' ', $this->getSlug() ));
        //$plural   = ucfirst( $this->getPluralSlug() );
        $plural = ucwords (str_replace(['_', '-'], ' ', $this->getPluralSlug() ));

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
            /*
            'menu_name'          => ucfirst($this->postTypeSlug) . 's',
            'name_admin_bar'     => ucfirst($this->postTypeSlug),
            'add_new'            => 'Add New',
            'all_items'          => 'All ' . ucfirst($this->postTypeSlug) . 's',
            'parent_item_colon'  => 'Parent ' . ucfirst($this->postTypeSlug) . 's:',
            */
			// Add more defaults as needed
		];
	}

    public function getCapabilities(): array
    {
        // If capType is set, configure capabilities accordingly... NOT simply based on slug/plural
        $capType = $this->getConfig()['capability_type'] ?? [];
        if ( !is_array($capType) ) { $capType = [$capType, "{$capType}s" ]; };
        //
        $custom = $this->getConfig()['capabilities'] ?? [];
        return array_merge( $this->getDefaultCapabilities( $capType ), $custom );
    }

    public function getDefaultCapabilities( array $capType = [] ): array
    {
        //error_log( '=== HasTypeProperties::getDefaultCapabilities() ===' );
        $type     = $this->getType();
        if ( $capType ) {
            $singular = $capType[0];
            $plural = $capType[1];
        } else {
            $singular = $this->getSlug();
            $plural   = $this->getPluralSlug() ?? "{$singular}s";
        }
        //error_log( 'type: ' . $type . '; singular: ' . $singular . '; plural: ' . $plural );

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
		return $this->getConfig()['hierarchical'] ?? $default;
	}

}

