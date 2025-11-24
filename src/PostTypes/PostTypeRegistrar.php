<?php

namespace WXC\PostTypes;

use WXC\App;
use WXC\BootOrder;
use WXC\PostTypes\PostTypeHandler;
use WXC\Utils\Text;

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
        //error_log( '=== PostTypeRegistrar::bootstrap() ===' );

        // Abort if no modules have been booted
		if ( !App::ctx()->modulesBooted() ) {
		    error_log( '=== no modules booted yet => abort ===' );
			return;
		}

        $activePostTypes = App::ctx()->getActivePostTypes();
        if ( empty( $activePostTypes ) ) {
			error_log( 'No active post types found. Skipping registration.' );
			return;
		}
		//error_log( 'activePostTypes: '.print_r($activePostTypes, true) );

		// Register CPTs
		$this->registerMany( $activePostTypes );

		// Expose active CPT slugs for wildcard shared taxonomies
		add_filter('wxc_active_post_types', function(array $cpts) use ($activePostTypes): array {
			return array_keys($activePostTypes);
		}, 10, 1);

		// Contribute CPT-specific taxonomy handlers (e.g., Habitat) to the unified registrar
		if (!empty($activePostTypes)) {
			add_filter('wxc_register_taxonomy_handlers', function(array $taxArray) use ($activePostTypes): array {
				foreach ($activePostTypes as $slug => $handlerClass) {
				    $handler = new $handlerClass();
				    // Wherever you attach/ensure taxonomies, resolve them:
				    $taxonomyClasses = $this->resolveTaxonomyClasses($handlerClass, $handler->getTaxonomies() ?? []);
				    // Example: hand them to your registrar, or call static register() if you use handlers.
				    // $this->taxonomyRegistrar->ensureRegistered($taxonomyClasses);
					$taxArray = array_merge($taxArray, (array) $taxonomyClasses);
				}
				return $taxArray;
			}, 10, 1);
		}
    }

	// Registers a custom post type using a PostTypeHandler
    public function registerCPT(PostTypeHandler $handler): void
    {
        //error_log( '=== PostTypeRegistrar->registerCPT() ===' );

    	$slug = $handler->getSlug();
    	//error_log('slug: '.$slug);

    	$capType = $handler->getCapType();
    	//error_log('capType: '.print_r($capType,true));

    	$labels = $handler->getLabels();
    	//error_log('labels: '.print_r($labels,true));

    	$supports = $handler->getSupports();
    	//error_log('supports: '.print_r($supports,true));

    	$taxonomies = $handler->getTaxonomies();
    	//error_log('taxonomies: '.print_r($taxonomies,true));

    	// Get capabilities (if defined, otherwise fall back to defaults)
        $capabilities = $handler->getCapabilities();
    	//error_log('capabilities: '.print_r($capabilities,true));

    	$icon = $handler->getMenuIcon();
    	//error_log('icon: '.$icon);

    	// WIP: better to enclose the following in mini-methods like getSupports? or simplify them all/most?
    	$hierarchical = $handler->getConfig()['hierarchical'] ?? false;
    	$rewrite = $handler->getConfig()['rewrite'] ?? ['slug' => $slug];

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
    	//error_log( '=== PostTypeRegistrar->registerMany() ===' );
    	//error_log( 'postTypeClasses: ' . print_r( $postTypeClasses, true ) );
        foreach( $postTypeClasses as $slug => $handlerClass ) {
        	//error_log( 'attempting to register handlerClass: '.$handlerClass );
        	$handler = new $handlerClass();
        	if (!post_type_exists($slug)) {
				// Only register if it doesn't already exist
				$this->registerCPT( $handler );
			} else {
			    //error_log( 'already post_type_exists: '.$slug );
        	}
        	//$handler->boot();
        }
    }

	//public function assignPostTypeCapabilities(array $handlers): void
	public function assignPostTypeCapabilities(): void
	{
		//error_log( '=== PostTypeRegistrar::assignPostTypeCapabilities() ===' );
		//$roles = ['administrator']; //
		$roles = ['administrator', 'editor'];

		$activePostTypes = App::ctx()->getActivePostTypes();
		//error_log( 'activePostTypes: ' . print_r($activePostTypes, true). ' ==' );

		foreach ( $activePostTypes as $slug => $handlerClass ) {
		    //error_log( 'preparing to add caps for handlerClass: '.$handlerClass );
			// Make sure the handler is of correct type
			$handler = new $handlerClass(); // $postTypeHandlerClass();
			//error_log( 'handler: ' . print_r($handler, true). ' ==' );
			if ( $handler instanceof PostTypeHandler ) {
				$caps = $handler->getCapabilities();
				$obsoleteCaps = [];
				//$obsoleteCaps = [ 'delete_venue', 'edit_venue', 'read_venue', 'delete_wxc_event', 'edit_wxc_event', 'read_wxc_event', 'delete_workpayment', 'edit_workpayment', 'read_workpayment', 'delete_person', 'edit_person', 'read_person', 'edit_transactions', 'edit_others_transactions', 'delete_transactions', 'publish_transactions', 'read_private_transactions', 'delete_private_transactions', 'delete_published_transactions', 'delete_others_transactions', 'edit_private_transactions', 'edit_published_transactions'];
				//error_log( 'caps for handler ' . $handler->getSlug() . ': ' . print_r($caps, true) );
				//
				foreach ($roles as $roleName) {
					$role = get_role($roleName);
					if ($role) {
						foreach ($caps as $cap) {
						    //error_log( ' adding cap: ' . $cap . ' for roleName: '. $roleName );
							$role->add_cap($cap);
						}
						// Remove obsolete caps (tmp)
						foreach ($obsoleteCaps as $cap) {
						    //error_log( ' removing cap: ' . $cap . ' for roleName: '. $roleName );
							$role->remove_cap($cap);
						}
					}
				}
			} else {
			    error_log('handler is not a PostTypeHandler.');
			}
		}
	}

	public function removePostTypeCapabilities(): void
	{
		$roles = [ 'administrator', 'editor' ]; // Adjust as needed

		foreach ( $this->getPostTypeHandlers() as $handler ) {
			$caps = $handler->getCapabilities();

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


    // WIP 08/27/25 -- the following three functions may be better placed in some other class, TBD

    /**
     * @param array|string $taxonomies Short names like 'habitat', or FQCNs, or 'Module:habitat'.
     * @return string[] FQCNs
     */
    protected function resolveTaxonomyClasses(string $handlerClass, array|string $taxonomies): array
    {
		//error_log( '=== PostTypeRegistrar::resolveTaxonomyClasses() ===' );
        $taxonomies = is_array($taxonomies) ? $taxonomies : [ $taxonomies ];
        //error_log( 'taxonomies: ' . print_r($taxonomies, true) );
        $resolved   = [];

        // WIP: modify to return associative array of slug => fqcn instead of ONLY the class names
        foreach ($taxonomies as $t) {
            $t = trim((string) $t);
            if ($t === '') {
                continue;
            }
            $resolved[] = $this->resolveTaxonomyFqcn($handlerClass, $t);
        }
        //error_log( 'resolved: ' . print_r($resolved, true) );

        return array_values(array_unique($resolved));
    }

    // TODO: generalize
    protected function resolveTaxonomyFqcn(string $handlerClass, string $name): string
    {
		//error_log( '=== PostTypeRegistrar::resolveTaxonomyFqcn() ===' );
		//error_log( 'name to resolve: ' . $name );

        // Already a FQCN?
        if (str_contains($name, '\\')) {
            //error_log( 'already a fqcn' );
            return ltrim($name, '\\');
        }

        // Extract root prefix up to "Modules\"
        // e.g. WXC\Modules\Supernatural\PostTypes\Monster
        // -> prefix: WXC\Modules\, currentModule: Supernatural
        /*$class = static::class;
        if (!preg_match('/^(.*\\\\Modules\\\\)([^\\\\]+)/', $class, $m)) {
            error_log( 'class: ' . $class );
            // Fallback: just StudlyCase in current namespace root (unlikely)
            return $this->studly($name);
        }*/
        if (!preg_match('/^(.*\\\\Modules\\\\)([^\\\\]+)/', $handlerClass, $m)) {
            //error_log( 'handlerClass: ' . $handlerClass );
            // Fallback: just StudlyCase in current namespace root (unlikely)
            //return $this->studly($name);
        }
        //error_log( 'm: ' . print_r($m, true) );
        $modulesPrefix = $m[1]; // "WXC\Modules\"
        $currentModule = $m[2]; // "Supernatural"

        // Optional "Module:basename" syntax
        $targetModule = $currentModule;
        $basename     = $name;
        if (str_contains($name, ':')) {
            [ $targetModule, $basename ] = array_map('trim', explode(':', $name, 2));
            if ($targetModule === '') {
                $targetModule = $currentModule;
            }
        }

        // Build FQCN: <prefix><Module>\Taxonomies\<Studly>
        // TODO: generalize for classes other than Taxonomies by replacing hardcoded '\\Taxonomies\\' with another var
        $fqcn = $modulesPrefix . $targetModule . '\\Taxonomies\\' . Text::studly($basename);
        //error_log( 'fqcn: ' . $fqcn );
        return $fqcn;
    }

}
