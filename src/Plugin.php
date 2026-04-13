<?php

// Initialize the plugin, register hooks, and manage dependencies

namespace atc\WXC;

use atc\WXC\Contracts\PluginContext;
use atc\WXC\App;
use atc\WXC\Logger;

// TODO: reduce number of use statements and use FQCNs instead as needed
use atc\WXC\CoreServices;
use atc\WXC\BootOrder;
use atc\WXC\PostTypes\PostTypeRegistrar;
use atc\WXC\PostTypes\SubtypeRegistry;
use atc\WXC\Taxonomies\TaxonomyRegistrar;
use atc\WXC\FieldGroupLoader;
//use atc\WXC\PostTypes\SubtypeTermSeeder;
use atc\WXC\Contracts\ModuleInterface;
use atc\WXC\SettingsManager;
use atc\WXC\Admin\SettingsPageController;
use atc\WXC\Admin\FieldKeyAuditPageController;
use atc\WXC\Modules\Core\CoreModule;

use atc\WXC\Templates\ViewLoader;
use atc\WXC\Utils\TitleFilter;
//
use atc\WXC\ACF\JsonPaths;
use atc\WXC\ACF\RestrictAccess;
use atc\WXC\ACF\BlockRegistrar;

final class Plugin implements PluginContext
{
    private static ?self $instance = null;
    protected bool $booted = false;

	// NB: Set the actual modules array via boot (wxc.php) -- this way, Plugin class contains logic only, and other plugins or themes can register additional modules dynamically
	// WIP clean this up and simplify
	protected array $availableModules = [];
    protected array $activeModules = [];
    protected array $activePostTypes = [];
    /** @var array<string, ModuleInterface> */
    private array $moduleInstances = [];
    //
    protected bool $modulesLoaded = false;
    protected bool $modulesBooted = false;
    /** @var list<class-string<ModuleInterface>> */
    protected array $bootedModules = [];
    private bool $capsAssigned = false;

    // Make these nullable if they’re typed elsewhere
    protected ?PostTypeRegistrar $postTypeRegistrar = null;
    protected ?FieldGroupLoader $fieldGroupLoader = null;
    protected SettingsManager $settingsManager;
    protected ?SettingsManager $settings = null;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct()
    {
        // Initialize internal state or dependencies
        $this->settingsManager = new SettingsManager( $this );
    }

    // Convenience accessor. Keeps $settingsManager protected inside the Plugin class
    // call e.g. from inside module: `if ( $this->plugin->getSettingsManager()->isPostTypeEnabled( 'monster' ) ) {}`
    public function getSettingsManager(): SettingsManager
    {
        return $this->settingsManager;
    }

    /**
     * Prevent cloning of the instance.
     */
    private function __clone()
    {}

    /**
     * Prevent unserializing of the instance.
     */
    public function __wakeup()
    {
        throw new \RuntimeException( 'Cannot unserialize a singleton.' );
    }

    public static function getInstance(): self
    {
        if ( static::$instance === null ) {
            static::$instance = new self();
        }
        return static::$instance;
    }

    public function boot(): void
    {
        if ( $this->booted ) {
            return;
		}

		// Allow others to register modules early
		do_action( 'wxc_pre_boot', $this );

		App::setContext($this); // <-- make context available to all handlers

        //$this->defineConstants(); // phased out (for now) -- constants now defined via wxc.php
        $this->registerAdminHooks();

        CoreServices::boot(); // wip
        
        // Run as early as possible on init so modules are ready before init:10 work.
		if ( did_action('init') ) {
		    Logger::debug( 'Already did init; finishBoot now.', 'wxc' );
			$this->finishBoot(); // if we're already past init (rare), just run now
		} else {
			add_action('init', [$this, 'finishBoot'], 0);
		}

		/*
        TitleFilter::setContext( $this ); // $this implements PluginContext
        TitleFilter::boot();
        */
        /*
        add_action( 'init', function() {
			\WXC\Utils\TitleFilter::setContext( Plugin::getInstance() );
			\WXC\Utils\TitleFilter::boot();
		}, 11 );
		*/

		$this->booted = true;
    }

    protected function registerAdminHooks(): void
    {
		if (is_admin()) {
			// Register core admin pages FIRST so they can listen for the init action
			(new SettingsPageController())->addHooks();
			(new FieldKeyAuditPageController($this))->addHooks();
			
			// THEN initialize the registry, which fires the 'wxc_admin_pages_init' action
			$registry = Admin\AdminPageRegistry::getInstance();
			$registry->init();
			
			add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
		}
    }

    /*protected function registerPublicHooks(): void
    {
        // on 'init': Register post types, taxonomies, shortcodes
        add_action( 'init', [ $this, 'registerPostTypes' ], 10 );
        add_action( 'init', [ $this, 'collectSubtypes' ], 11 );
        add_action( 'acf/init', [ $this, 'registerFieldGroups' ], 11 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueuePublicAssets' ] );

		// After modules boot, assign capabilities based on handlers
		add_action( 'wxc_modules_booted', [ $this, 'assignPostTypeCaps' ], 20, 2 );
    }*/

    public function finishBoot(): void
	{
		// Boot core services -- TODO: make sure this is still useful...
		//CoreServices::boot();

        // Load modules and config

        // Discover all modules registered by core + add‑ons
        $modules = apply_filters( 'wxc_register_modules', [] );
        //Logger::debug( 'modules discovered via wxc_register_modules:'.print_r($modules, true), 'wxc' );
        $this->setAvailableModules( $modules );

        // Settings
        $this->settings = $this->settings ?? new SettingsManager(); // redundant w/ _construct -- WIP

        // First‑run initializer: if no selection saved, enable defaults
        $this->settings->ensureInitialized($this->getAvailableModules());

        // Load saved (or just‑seeded) active modules
        $this->loadActiveModules();

        // Boot active modules and remember which ones succeeded
        $this->bootActiveModules();

		// Ensure the active CPTs filter is added AFTER CPTs (10) and BEFORE Taxonomies (12)
		// WIP 08/23/25
		add_action('init', function (): void {
			add_filter('wxc_active_post_types', function (array $cpts): array {
				// Return slugs of currently active CPTs
				return array_keys($this->getActivePostTypes());
			}, 10, 1);
		}, BootOrder::SUBTYPES); // 11

		// Register systems in the same order that they will run, though prioritied enforce the actual order on 'init' or 'acf/init'

		// Register Custom Post Types
		//(new \WXC\PostTypes\PostTypeRegistrar($this))->register(); // init:10
        $this->postTypeRegistrar = new PostTypeRegistrar($this); // instance-based (needs plugin state)
        $this->postTypeRegistrar->register();                    // add_action('init', ..., BootOrder::CPT)

        // Collect Subtypes
        SubtypeRegistry::register();                             // add_action('init', collect, BootOrder::SUBTYPE_COLLECT)

        // Register shared/global taxonomies? WIP
        /*
        add_filter('wxc_register_taxonomy_handlers', function(array $list): array {
			$list[] = \atc\WXC\Taxonomies\RexTag::class; // object_types may be ['*'] or an explicit list
			return $list;
		});

		// ... and expose active CPTs (for the '*' wildcard) via a small filter the registrar reads:
		add_filter('wxc_active_post_types', function(array $cpts) use ($plugin): array {
			return array_keys($plugin->getActivePostTypes());
		});
		*/

        // Register Custom Taxonomies for active modules
        TaxonomyRegistrar::register();                           // add_action('init', bootstrap, BootOrder::TAXONOMY)

        // Seed subtype terms
        //SubtypeTermSeeder::register();                           // add_action('init', seed, BootOrder::TERM_SEED)

        // Register field groups (admin‑centric, depends on plugin state)
        $this->fieldGroupLoader = new FieldGroupLoader($this);
        $this->fieldGroupLoader->register();                     // add_action('acf/init', ..., BootOrder::ACF_FIELDS)

        // front-end assets (separate hook family)
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueuePublicAssets' ] ); // wip
        //\smith\Rex\Core\Assets::register();                      // add_action('wp_enqueue_scripts', ..., BootOrder::ENQUEUE_ASSETS)

	}

    // TODO: move this to core Assets class?
	public function enqueueAdminAssets(string $hook): void
	{
		//if ( $hook !== 'settings_page_wxc-settings' ) { return; }

		wp_enqueue_script(
			'wxc-settings',
			WXC_PLUGIN_URL . 'assets/js/settings.js',
			[],
			'1.0',
			true
		);

		/*wp_enqueue_style(
			'wxc-settings',
			WXC_PLUGIN_DIR . '/assets/css/settings.css',
			[],
			'1.0'
		);*/

    	wp_enqueue_style(
            'wxc-admin-style',
            WXC_PLUGIN_URL . 'assets/css/wxc-admin.css',
            [],
            filemtime( WXC_PLUGIN_DIR . 'assets/css/wxc-admin.css' )
        );
	}

    public function enqueuePublicAssets(): void
    {
    	wp_enqueue_style(
            'wxc-style',
            WXC_PLUGIN_URL . 'assets/css/wxc.css',
            [],
            filemtime( WXC_PLUGIN_DIR . 'assets/css/wxc.css' )
        );
    }

	public function maybeLoadSettingsManager(): void
	{
		if ( is_admin() && $this->isSettingsPage() ) {
			$this->settingsManager = new SettingsManager( $this );
		}
	}

	protected function isSettingsPage(): bool
	{
		return isset( $_GET['page'] ) && $_GET['page'] === 'wxc_settings';
	}

	public function setAvailableModules( array $modules ): void
	{
		Logger::debug( 'modules:'.print_r($modules, true), 'wxc' );

		// Validate classes -- make sure they implement ModuleInterface
		foreach( $modules as $slug => $class ) {
			if ( !class_exists( $class ) ) {
			    Logger::warn( 'The class:'.$class . ' does not exist.', 'wxc' );
			}
			if ( is_subclass_of( $class, ModuleInterface::class ) ) {
				$this->availableModules[$slug] = $class;
			    //Logger::debug( 'Module with slug: ' .$slug . ' and class: ' .$class . ' has been added to availableModules.', 'wxc' );
			} else {
			    Logger::debug( 'Module with slug: ' .$slug . ' and class: ' .$class . ' is not a subclass of ModuleInterface.', 'wxc' );
			}
		}
	}

	public function getAvailableModules(): array
	{
		return $this->availableModules;
	}

    protected function loadActiveModules(): void
    {
        if ( $this->modulesLoaded ) {
            return;
        }

        $activeSlugs = $this->settingsManager->getActiveModuleSlugs();

        foreach ( $activeSlugs as $slug ) {
            if ( isset( $this->availableModules[ $slug ] ) ) {
                //$this->activeModules[] = $this->availableModules[ $slug ]; // v1
                $moduleClass = $this->availableModules[ $slug ];
                $module = new $moduleClass();

                //$this->activeModules[ $slug ] = $module;
                $this->activeModules[ $slug ] = $moduleClass;
            }
        }

        $this->modulesLoaded = true;
    }

    public function getActiveModules(): array
    {
        $this->loadActiveModules();
        return $this->activeModules;
    }
    
	public function getModule(string $key): ?ModuleInterface
	{
		if (isset($this->moduleInstances[$key])) {
			return $this->moduleInstances[$key];
		}
	
		$active = $this->getActiveModules(); // returns class strings (by design)
		$moduleDef = $active[$key] ?? null;
	
		if ($moduleDef instanceof ModuleInterface) {
			return $this->moduleInstances[$key] = $moduleDef;
		}
	
		if (is_string($moduleDef) && class_exists($moduleDef)) {
			// If your modules need dependencies, wire them here.
			$instance = new $moduleDef();
			return $this->moduleInstances[$key] = $instance;
		}
	
		return null;
	}

    public function bootActiveModules(): int
    {
        $this->bootedModules = [];
        foreach ( $this->getActiveModules() as $moduleClass ) {
            //Logger::debug( 'About to attempt instantiation for moduleClass: ' . $moduleClass, 'wxc' );
        	$module = new $moduleClass();
        	if (!$module instanceof ModuleInterface) {
				Logger::debug( 'Module does not implement ModuleInterface: '.$moduleClass, 'wxc' );
				continue;
			}

        	//Logger::debug( 'About to attempt module boot() for moduleClass: ' . $moduleClass, 'wxc' );
        	try {
				if (method_exists($module, 'boot')) {
					$module->boot();
					$this->bootedModules[] = $moduleClass;
					//Logger::debug( 'Module booted! moduleClass: '.$moduleClass, 'wxc' );
				} else {
					Logger::debug( 'boot() method missing for moduleClass: '.$moduleClass, 'wxc' );
				}
			} catch (\Throwable $e) {
				Logger::debug( 'Error booting module '.$moduleClass.': '.$e->getMessage(), 'wxc' );
			}
        }
        $count = count($this->bootedModules);
		$this->modulesBooted = $count > 0;
		//Logger::debug( $count . ' Modules booted', 'wxc' );

		/**
		 * Fires after modules have attempted to boot.
		 *
		 * @param self     $plugin
		 * @param string[] $bootedModules
		 */
		do_action('wxc_modules_booted', $this, $this->bootedModules);

		return $count;
    }

    public function modulesBooted(): bool
    {
        return $this->modulesBooted;
    }

    /**
     * Returns all enabled post types across active modules,
     * based on both the module definitions and plugin settings.
     * Items in return array are structured as follows: $postTypeClasses[ $postTypeSlug ] = $postTypeHandlerClass;
     */
    public function getActivePostTypes(): array
	{
    	// Don't reload activePostTypes if we've cached them already
		if ( ! empty( $this->activePostTypes ) ) {
		    //Logger::debug( 'activePostTypes already cached', 'wxc' );
			return $this->activePostTypes;
		}

    	$this->loadActiveModules();
		$enabledPostTypesByModule = $this->getSettingsManager()->getEnabledPostTypeSlugsByModule();
		//$activeSlugsByModule = $this->settingsManager->getEnabledPostTypeSlugsByModule();
		//Logger::debug( 'enabledPostTypesByModule: ' . print_r($enabledPostTypesByModule, true), 'wxc' );

		$postTypeClasses = [];

		foreach( $this->activeModules as $moduleSlug => $moduleClass ) {
			//Logger::debug( 'About to look for activePostTypes for moduleSlug: ' . print_r($moduleSlug, true), 'wxc' );
			try {
				if( !class_exists($moduleClass) ) {
					Logger::debug( 'Class $moduleClass does not exist.', 'wxc' );
					continue;
				}

				if( !is_subclass_of($moduleClass, Contracts\ModuleInterface::class) ) {
					Logger::debug( 'Class $moduleClass is not a ModuleInterface.', 'wxc' );
					continue;
				}

				$moduleInstance = new $moduleClass();
				$moduleSlug = strtolower($moduleInstance->getSlug()); // TBD: why not just get this from the for loop setup?

				if( !method_exists($moduleClass, 'getPostTypes') ) {
					Logger::debug( 'Module $moduleClass does not implement getPostTypes().', 'wxc' );
					continue;
				}

				//$definedPostTypes = $moduleClass::getPostTypes();
				$definedPostTypes = $moduleInstance->getPostTypeHandlerClasses();
				//$handlers = $module->getPostTypeHandlers();
				//Logger::debug( 'definedPostTypes: ' . print_r($definedPostTypes, true), 'wxc' );

				$enabled = $enabledPostTypesByModule[ $moduleSlug ] ?? $definedPostTypes;
				//Logger::debug( 'Module $moduleSlug: defined=' . implode(',', $definedPostTypes) . '; enabled=' . implode(',', $enabled), 'wxc' );

				//foreach ($definedPostTypes as $postTypeSlug => $name) {
				//foreach ( $handlers as $handlerClass ) {
				foreach ( $definedPostTypes as $postTypeHandlerClass ) {
				    if ( ! class_exists( $postTypeHandlerClass ) ) {
						continue;
					}
					//Logger::debug( 'postTypeHandlerClass: ' . $postTypeHandlerClass, 'wxc' );
					$postTypeSlug = $postTypeHandlerClass::getSlug();
					if  (in_array( $postTypeSlug, $enabled, true )) {
					    //Logger::debug("Post type '$postTypeSlug' from module '$moduleSlug' is now enabled (class: '$postTypeHandlerClass' ).", 'wxc' );
						$this->activePostTypes[ $postTypeSlug ] = $postTypeHandlerClass;
					} else {
						//Logger::debug( "Post type '$postTypeSlug' from module '$moduleSlug' is not enabled.", 'wxc' );
					}
					/*
					if (
						! isset( $activeSlugsByModule[ $moduleSlug ] ) ||
						! in_array( $slug, $activeSlugsByModule[ $moduleSlug ], true )
					) {
						continue;
					}
					*/
				}
			} catch( \Throwable $e ) {
				Logger::error( 'Exception in getActivePostTypes for module $moduleSlug: ' . $e->getMessage());
			}
		}

		//Logger::debug( 'active postTypeClasses: ' . print_r($postTypeClasses, true), 'wxc' );

		// Make sure WP default Post Types are also accounted for so that Subtypes will work -- e.g. subtype of Post
		// TODO: make this more robust to ensure that these default types haven't for some reason been deactivated/removed?
		$core = new CoreModule();
		foreach ($core->getPostTypeHandlerClasses() as $handlerClass) {
			if (class_exists($handlerClass)) {
				$this->activePostTypes[$handlerClass::getSlug()] = $handlerClass;
			}
		}
		
		return $this->activePostTypes;
	}

	/// WIP
	public function assignPostTypeCaps(array $bootedModules = []): void
    {
        if ($this->capsAssigned) {
			return;
		}
		$this->capsAssigned = true;

        try {
            if (!$bootedModules) {
                Logger::debug( 'No modules were booted; skipping.', 'wxc' );
                return;
            }

            $handlers = $this->getActivePostTypes();
            //Logger::debug( 'handlers: ' . print_r( $handlers, true ), 'wxc' );

            if (empty($handlers)) {
                Logger::debug( 'No active post type handlers found; skipping.', 'wxc' );
                return;
            }

            if (!$this->postTypeRegistrar) {
                Logger::debug( 'postTypeRegistrar is null; cannot assign capabilities.', 'wxc' );
                return;
            }

            $count = is_countable($handlers) ? count($handlers) : 0;
            Logger::debug( "Assigning capabilities for {$count} handler(s).", 'wxc' );
            //Logger::debug( 'handlers: ' . print_r( $handlers, true ), 'wxc' );

            /*
            // Optional: short-circuit if nothing changed since last run
			$activeSlugs = array_keys($this->getActivePostTypes());
			$hash = md5(implode('|', $activeSlugs));
			$stored = get_option('wxc_caps_hash');

			if ($stored === $hash) {
				return;
			}
			*/

            $this->postTypeRegistrar->assignPostTypeCapabilities($handlers);

            //Logger::debug( 'Capabilities assigned successfully.', 'wxc' );

            //update_option('wxc_caps_hash', $hash);
        } catch (\Throwable $e) {
            Logger::error('Error in assignPostTypeCaps: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
        }
    }

	//
    protected function use_custom_caps() {
		$use_custom_caps = false;
		if ( isset($options['use_custom_caps']) && !empty($options['use_custom_caps']) ) {
			$use_custom_caps = true;
		}
		return $use_custom_caps;
	}

    protected static function activate(): void {
       flush_rewrite_rules();
    }

    protected static function deactivate(): void {
       flush_rewrite_rules();
    }

    /*
	register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
	register_activation_hook( __FILE__, 'wxc_flush_rewrites' );
	function wxc_flush_rewrites() {
		// call your CPT registration function here (it should also be hooked into 'init')
		myplugin_custom_post_types_registration();
		flush_rewrite_rules();
	}
	*/

}

