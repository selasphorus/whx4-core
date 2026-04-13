<?php

namespace atc\WXC\PostTypes;

use atc\WXC\App;
use atc\WXC\Logger;
use atc\WXC\BootOrder;
use atc\WXC\PostTypes\PostTypeHandler;

class PostTypeRegistrar
{
    private bool $registered = false;
    private bool $capsAssigned = false;

    public function register(): void
    {
        if ( $this->registered ) return;

        // Assign caps BEFORE CPTs are registered and before admin UI builds menus.
        add_action('init', [$this, 'assignPostTypeCapabilities'], BootOrder::CAPS /* e.g. 8 */);

        // Register CPTs at the usual point
        add_action( 'init', [$this, 'bootstrap'], BootOrder::CPTS );
        $this->registered = true;
    }

    public function bootstrap(): void
    {
        // Abort if no modules have been booted
		if ( !App::ctx()->modulesBooted() ) {
		    Logger::debug( '=== no modules booted yet => abort ===' );
			return;
		}

        $activePostTypes = App::ctx()->getActivePostTypes();
        if ( empty( $activePostTypes ) ) {
			Logger::debug( 'No active post types found. Skipping registration.' );
			return;
		}
		//Logger::debug( 'activePostTypes', $activePostTypes, 'wxc' );

		// Register CPTs
		$this->registerMany( $activePostTypes );

		// Expose active CPT slugs for wildcard shared taxonomies
		add_filter('wxc_active_post_types', function(array $cpts) use ($activePostTypes): array {
			return array_keys($activePostTypes);
		}, 10, 1);

		// Contribute CPT-specific taxonomy handlers (e.g., Habitat) to the unified registrar
		if (!empty($activePostTypes)) {
			add_filter('wxc_register_taxonomy_handlers', function (array $taxArray) use ($activePostTypes): array {
				foreach ($activePostTypes as $slug => $handlerClass) {
					$taxArray = array_merge($taxArray, $handlerClass::resolveTaxonomyClasses($handlerClass::getTaxonomies()));
				}
				return $taxArray;
			}, 10, 1);
		}
    }

	// Registers a custom post type using a PostTypeHandler
    public function registerCPT(PostTypeHandler $handler): void
    {
        $slug = $handler::getSlug();
    	//Logger::debug('slug: '.$slug);

    	$capType = $handler->getCapType();
    	//Logger::debug( 'capType', $capType, 'wxc' );

    	$labels = $handler->getLabels();
    	//Logger::debug( 'labels', $labels, 'wxc' );

    	$supports = $handler->getSupports();
    	//Logger::debug( 'supports', $supports, 'wxc' );

    	$taxonomies = $handler->getTaxonomies();
    	//Logger::debug( 'taxonomies', $taxonomies, 'wxc' );

    	// Get capabilities (if defined, otherwise fall back to defaults)
        $capabilities = $handler->getCapabilities();
    	//Logger::debug( 'capabilities', $capabilities, 'wxc' );

    	$icon = $handler->getMenuIcon();
    	//Logger::debug('icon: '.$icon);

    	// WIP: better to enclose the following in mini-methods like getSupports? or simplify them all/most?
    	$hierarchical = $handler::getConfig()['hierarchical'] ?? false;
    	$rewrite      = $handler::getConfig()['rewrite'] ?? ['slug' => $slug];

        // Register the post type
        register_post_type($slug, [
            'public'       => true,
			'publicly_queryable'=> true,
			'show_ui' 			=> true,
			'query_var'        	=> true,
            'show_in_rest' => true,  // Enable REST API support // false = use classic, not block editor
            'labels'       => $labels,
            'capability_type' => $capType,
			//'caps'       => [ 'post' ],
            //'capabilities' => $capabilities,
            'map_meta_cap' => true,
            //
            'supports'     => $supports, // [ 'title', 'author', 'editor', 'excerpt', 'revisions', 'thumbnail', 'custom-fields', 'page-attributes' ],
			'taxonomies'   => $taxonomies, //'taxonomies'	=> [ 'category', 'tag' ],
            'rewrite'      => $rewrite, //['slug' => $slug],
            'has_archive'  => true,
            'show_in_menu' => true,
            'menu_icon'    => $icon,
			'hierarchical' => $hierarchical, // false
			//'menu_position'		=> null,
			//'delete_with_user' 	=> false,
        ]);
	}

    //
    public function registerMany( array $postTypeClasses ): void
    {
    	//Logger::debug( 'postTypeClasses', $postTypeClasses, 'wxc' );
        foreach( $postTypeClasses as $slug => $handlerClass ) {
        	//Logger::debug( 'attempting to register handlerClass: '.$handlerClass );
        	$handler = new $handlerClass();
        	if (!post_type_exists($slug)) {
				// Only register if it doesn't already exist
				$this->registerCPT( $handler );
			} else {
			    //Logger::debug( 'already post_type_exists: '.$slug );
        	}
        	//$handler->boot();
        }
    }

	public function assignPostTypeCapabilities(): void
	{
		$roles           = ['administrator', 'editor'];
		$activePostTypes = App::ctx()->getActivePostTypes();
		//Logger::debug( 'activePostTypes', $activePostTypes, 'wxc' );
	
		foreach ( $activePostTypes as $slug => $handlerClass ) {
		    //Logger::debug( 'preparing to add caps for handlerClass: '.$handlerClass );
			if ( ! is_subclass_of( $handlerClass, PostTypeHandler::class ) ) {
				Logger::debug( 'handler is not a PostTypeHandler.' );
				continue;
			}
	
			$caps = $handlerClass::getCapabilities();
			$obsoleteCaps = [];
			//$obsoleteCaps = [ 'delete_venue', 'edit_venue', 'read_venue', 'delete_wxc_event', 'edit_wxc_event', 'read_wxc_event', 'delete_workpayment', 'edit_workpayment', 'read_workpayment', 'delete_person', 'edit_person', 'read_person', 'edit_transactions', 'edit_others_transactions', 'delete_transactions', 'publish_transactions', 'read_private_transactions', 'delete_private_transactions', 'delete_published_transactions', 'delete_others_transactions', 'edit_private_transactions', 'edit_published_transactions'];
			//Logger::debug( 'caps for handler ' . $handler::getSlug() . ': ' . print_r($caps, true) );
	
			foreach ( $roles as $roleName ) {
				$role = get_role( $roleName );
				if ( ! $role ) continue;
				foreach ($caps as $cap) {
					//Logger::debug( ' adding cap: ' . $cap . ' for roleName: '. $roleName );
					$role->add_cap($cap);
				}
				// Remove obsolete caps (tmp)
				foreach ($obsoleteCaps as $cap) {
					//Logger::debug( ' removing cap: ' . $cap . ' for roleName: '. $roleName );
					$role->remove_cap($cap);
				}
			}
		}
	}

	public function removePostTypeCapabilities(): void
	{
		$roles = [ 'administrator', 'editor' ]; // Adjust as needed

		foreach ( $this->getPostTypeHandlers() as $handler ) {
			$caps = $handlerClass::getCapabilities();

			foreach ( $roles as $role_name ) {
				$role = get_role( $role_name );

				if ( $role ) {
					foreach ( $caps as $cap ) {
						$role->remove_cap( $cap );
					}
				}
			}
		}
	}

}
