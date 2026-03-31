<?php

namespace atc\WXC\PostTypes;

use atc\WXC\App;
use atc\WXC\Logger;
use atc\WXC\BootOrder;
use atc\WXC\PostTypes\PostTypeHandler;
use atc\WXC\Utils\Text;

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
        $slug = $handler->getSlug();
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
		$roles = ['administrator', 'editor'];

		$activePostTypes = App::ctx()->getActivePostTypes();
		//Logger::debug( 'activePostTypes', $activePostTypes, 'wxc' );

		foreach ( $activePostTypes as $slug => $handlerClass ) {
		    //Logger::debug( 'preparing to add caps for handlerClass: '.$handlerClass );
			// Make sure the handler is of correct type
			$handler = new $handlerClass();
			//Logger::debug( 'handler', $handler, 'wxc' );
			if ( $handler instanceof PostTypeHandler ) {
				$caps = $handler->getCapabilities();
				$obsoleteCaps = [];
				//$obsoleteCaps = [ 'delete_venue', 'edit_venue', 'read_venue', 'delete_wxc_event', 'edit_wxc_event', 'read_wxc_event', 'delete_workpayment', 'edit_workpayment', 'read_workpayment', 'delete_person', 'edit_person', 'read_person', 'edit_transactions', 'edit_others_transactions', 'delete_transactions', 'publish_transactions', 'read_private_transactions', 'delete_private_transactions', 'delete_published_transactions', 'delete_others_transactions', 'edit_private_transactions', 'edit_published_transactions'];
				//Logger::debug( 'caps for handler ' . $handler->getSlug() . ': ' . print_r($caps, true) );
				//
				foreach ($roles as $roleName) {
					$role = get_role($roleName);
					if ($role) {
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
			} else {
			    Logger::debug('handler is not a PostTypeHandler.');
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
        $taxonomies = is_array($taxonomies) ? $taxonomies : [ $taxonomies ];
        
        //Logger::debug( 'taxonomies', $taxonomies, 'wxc' );
        $resolved   = [];

        // WIP: modify to return associative array of slug => fqcn instead of ONLY the class names
        foreach ($taxonomies as $t) {
            $t = trim((string) $t);
            if ($t === '') {
                continue;
            }
            $resolved[] = $this->resolveTaxonomyFqcn($handlerClass, $t);
        }
        //Logger::debug( 'resolved', $resolved, 'wxc' );

        return array_values(array_unique($resolved));
    }

    // TODO: generalize
    protected function resolveTaxonomyFqcn(string $handlerClass, string $name): string
    {
		//Logger::debug( 'name to resolve: ' . $name );

        // Already a FQCN?
        if (str_contains($name, '\\')) {
            //Logger::debug( 'already a fqcn' );
            return ltrim($name, '\\');
        }

        // Extract root prefix up to "Modules\"
        // e.g. WXC\Modules\Supernatural\PostTypes\Monster
        // -> prefix: WXC\Modules\, currentModule: Supernatural
        /*$class = static::class;
        if (!preg_match('/^(.*\\\\Modules\\\\)([^\\\\]+)/', $class, $m)) {
            Logger::debug( 'class: ' . $class );
            // Fallback: just StudlyCase in current namespace root (unlikely)
            return $this->studly($name);
        }*/
        if (!preg_match('/^(.*\\\\Modules\\\\)([^\\\\]+)/', $handlerClass, $m)) {
            //Logger::debug( 'handlerClass: ' . $handlerClass );
            // Fallback: just StudlyCase in current namespace root (unlikely)
            //return $this->studly($name);
        }
        //Logger::debug( 'm: ' . print_r($m, true) );
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
        //Logger::debug( 'fqcn: ' . $fqcn );
        return $fqcn;
    }

}
