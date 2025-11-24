<?php

namespace atc\WXC;

use atc\WXC\App;
use atc\WXC\Contracts\ModuleInterface;
use atc\WXC\Templates\ViewLoader;
use atc\WXC\PostTypes\PostTypeHandler;
use atc\WXC\Shortcodes\ShortcodeManager;

// TODO: make this final class?
abstract class Module implements ModuleInterface
{
    protected ?string $moduleSlug = null;

    /**
	 * @return array<class-string>
	 */
	 // TODO: consider finding these automatically, as with FieldGroups?
	abstract public function getPostTypeHandlerClasses(): array;
	/**
	 * @return array<class-string>
	 */
    //abstract public function getPostTypeHandlers(): array;
    public function getPostTypeHandlers(): array
	{
		return array_map(
			fn( $class ) => new $class(),
			$this->getPostTypeHandlerClasses()
		);
	}

    public function boot(): void
    {
		//error_log( '=== Module class boot() for module: ' . $this->getSlug() . '===' );
        $this->registerDefaultViewRoot();

        $enabledSlugs = App::ctx()
			->getSettingsManager()
			->getEnabledPostTypeSlugsByModule()[ $this->getSlug() ] ?? [];

		//error_log( 'enabledSlugs: ' . print_r($enabledSlugs,true) );

		// Get all the post type handlers for this module
		foreach ( $this->getPostTypeHandlerClasses() as $handlerClass ) {
			if ( ! class_exists( $handlerClass ) ) {
				error_log( "Missing post type handler: $handlerClass" );
				continue;
			}

			$handler = new $handlerClass();

			if ( ! method_exists( $handler, 'getSlug' ) ) {
				error_log( "Handler class $handlerClass missing getSlug()" );
				continue;
			}

			if ( ! in_array( $handler->getSlug(), $enabledSlugs, true ) ) {
				//error_log( 'slug: ' . $handler->getSlug() . ' is not in the enabledSlugs array: ' . print_r($enabledSlugs,true) );
				continue; // Skip if not enabled for this module
			}

			//error_log( 'About to attempt handler boot() for PostType handlerClass: ' . $handlerClass . '===' );
			if ( ! method_exists( $handler, 'boot' ) ) {
				error_log( "Handler class $handlerClass missing boot()" );
				continue;
			}
			$handler->boot();
		}

		//error_log('Module::boot -> calling ShortcodeManager::add');
        ShortcodeManager::add(WXC\Shortcodes\WXCListShortcode::class);
    }

	public function getSlug(): string
	{
		return $this->detectModuleSlugFromNamespace();
	}

	// Human-readable label version of Module name
	public function getName(): string
	{
		$parts = explode( '\\', static::class );
		$name  = end( $parts ) === 'Module' && isset( $parts[ count( $parts ) - 2 ] )
			? $parts[ count( $parts ) - 2 ]
			: ( new \ReflectionClass( $this ) )->getShortName();

		return ucwords( str_replace( '_', ' ', $name ) );
	}

	protected function registerDefaultViewRoot(): void
	{
		$slug = $this->detectModuleSlugFromNamespace();

		if ( ! ViewLoader::hasViewRoot( $slug ) ) {
			$reflector = new \ReflectionClass( $this );
			$moduleDir = dirname( $reflector->getFileName() );
			ViewLoader::registerModuleViewRoot( $slug, $moduleDir . '/Views' );
		}
	}
	
	protected function findViaHandler(string $postType, array $filters): array
	{
		$map   = App::ctx()->getActivePostTypes(); // ['monster' => Monster::class, ...]
		$class = $map[$postType] ?? null;
	
		if (!$class || !is_subclass_of($class, PostTypeHandler::class)) {
			return ['posts' => [], 'pagination' => ['found' => 0, 'max_pages' => 0, 'paged' => $filters['paged'] ?? 1], 'debug' => ['error' => 'handler missing']];
		}
		
		//error_log('[findViaHandler] postType=' . $postType);
		//error_log('[findViaHandler] class=' . (($class ?? 'NULL')));
		//error_log('[findViaHandler] filters=' . json_encode($filters, JSON_UNESCAPED_SLASHES));
		
		/** @var class-string<PostTypeHandler> $class */
		$result = $class::find($filters);
		
		//error_log('[findViaHandler] result.debug=' . json_encode($result['debug'] ?? [], JSON_UNESCAPED_SLASHES));
		return $result;
	}

	protected function detectModuleSlugFromNamespace(): string
	{
		if ( isset( $this->moduleSlug ) ) {
			return $this->moduleSlug;
		}

		$parts = explode( '\\', static::class ); // Example: smith\Rex\Modules\Supernatural\Module → supernatural

		$key = array_search( 'Modules', $parts, true );
		if ( $key !== false && isset( $parts[ $key + 1 ] ) ) {
			$this->moduleSlug = strtolower( $parts[ $key + 1 ] );
		} else {
			// Fallback
			// ->getShortName() returns just the class name without the namespace
			$this->moduleSlug = strtolower( ( new \ReflectionClass( $this ) )->getShortName() );
		}

		return $this->moduleSlug;
	}

	// ?? obsolete/redundant
	public function getPostTypes(): array
	{
		//error_log( '=== \Core\Module -- getPostTypes() ===' );
		$postTypes = [];

		foreach( $this->getPostTypeHandlerClasses() as $class ) {
			try {
				$handler = new $class();
				$slug = $handler->getSlug();
				$label = $handler->getLabels()['singular_name']; // or getLabel()
				$postTypes[ $slug ] = $label;
			} catch( \Throwable $e ) {
				error_log( "Error in post type handler {$class}: " . $e->getMessage() );
			}
		}

		//error_log("postTypes: " . print_r($postTypes, true));

		return $postTypes;
	}
}
