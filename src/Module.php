<?php

namespace atc\WXC;

use atc\WXC\App;
use atc\WXC\Logger;
use atc\WXC\Contracts\ModuleInterface;
use atc\WXC\Templates\ViewLoader;
use atc\WXC\PostTypes\PostTypeHandler;
use atc\WXC\Shortcodes\ShortcodeManager;

use WP_Post;

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
		$logCtx = ['wxc'];
		//Logger::debug( 'module: ' . $this->getSlug() );
        $this->registerDefaultViewRoot();

        $enabledSlugs = App::ctx()
			->getSettingsManager()
			->getEnabledPostTypeSlugsByModule()[ $this->getSlug() ] ?? [];

		//Logger::debug( 'enabledSlugs', $enabledSlugs, 'wxc' );

		// Get all the post type handlers for this module
		foreach ( $this->getPostTypeHandlerClasses() as $handlerClass ) {
			if ( ! class_exists( $handlerClass ) ) {
				Logger::warn( "Missing post type handler: $handlerClass" );
				continue;
			}

			$handler = new $handlerClass();

			if ( ! method_exists( $handler, 'getSlug' ) ) {
				Logger::error( "Handler class $handlerClass missing getSlug()" );
				continue;
			}

			if ( ! in_array( $handler->getSlug(), $enabledSlugs, true ) ) {
				//Logger::debug( 'slug: ' . $handler->getSlug() . ' is not in the enabledSlugs array: ' . print_r($enabledSlugs,true) );
				continue; // Skip if not enabled for this module
			}

			//Logger::debug( 'About to attempt handler boot() for PostType handlerClass: ' . $handlerClass . '===' );
			if ( ! method_exists( $handler, 'boot' ) ) {
				Logger::error( "Handler class $handlerClass missing boot()" );
				continue;
			}
			$handler->boot();
		}

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
	
	// findViaHandler v1
	/*protected function findViaHandler(string $postType, array $filters): array
	{
		$map   = App::ctx()->getActivePostTypes(); // ['monster' => Monster::class, ...]
		$class = $map[$postType] ?? null;
	
		if (!$class || !is_subclass_of($class, PostTypeHandler::class)) {
			return ['posts' => [], 'pagination' => ['found' => 0, 'max_pages' => 0, 'paged' => $filters['paged'] ?? 1], 'debug' => ['error' => 'handler missing']];
		}
		
		//Logger::debug('postType=' . $postType);
		//Logger::debug('class=' . (($class ?? 'NULL')));
		//Logger::debug('filters=' . json_encode($filters, JSON_UNESCAPED_SLASHES));
		
		// @var class-string<PostTypeHandler> $class
		$result = $class::find($filters);
		
		//Logger::debug('result.debug=' . json_encode($result['debug'] ?? [], JSON_UNESCAPED_SLASHES));
		return $result;
	}*/

	protected function findViaHandler(string|array $postType, array $filters): array
	{
		$postTypes = (array) $postType;
		//Logger::debug( 'postTypes', $postTypes, ['wxc', 'query'] );
		//Logger::debug( 'filters', $filters, ['wxc', 'query'] );
	
		if (count($postTypes) === 1) {
			return $this->findSingleType($postTypes[0], $filters);
		}
	
		return $this->findAcrossTypes($postTypes, $filters);
	}
	
	private function findSingleType(string $postType, array $filters): array
	{
		$logCtx = ['wxc', 'query'];
		//Logger::debug( 'postType: '.$postType, null, $logCtx );
		//Logger::debug( 'filters', $filters, $logCtx );
		
		$map   = App::ctx()->getActivePostTypes();
		$class = $map[$postType] ?? null;
	
		if (!$class || !is_subclass_of($class, PostTypeHandler::class)) {
			return $this->emptyResult($filters);
		}
	
		/** @var class-string<PostTypeHandler> $class */
		return $class::find($filters);
	}
	
	private function findAcrossTypes(array $postTypes, array $filters): array
	{
		$logCtx = ['wxc', 'query'];
		//Logger::debug( 'postTypes', $postTypes, $logCtx );
		
		// Pagination is not well-defined across merged result sets.
		// Fetch all matching posts from each type and merge.
		$mergedFilters = array_merge($filters, ['limit' => -1, 'paged' => 1]);
		//Logger::debug( 'mergedFilters', $mergedFilters, $logCtx );
	
		$allPosts = [];
		$totalFound = 0;
	
		foreach ($postTypes as $type) {
		    //Logger::debug( 'About to run find for postType [' . $type .']', null, $logCtx );
		    
		    $map     = App::ctx()->getActivePostTypes();
		    $class   = $map[$type] ?? null;
		    
		    // Resolve generic 'category' to this type's default taxonomy.
			$typeFilters = $mergedFilters;
			if (
				$class
				&& isset($typeFilters['category'])
				&& is_subclass_of($class, PostTypeHandler::class)
			) {
				$defaultTax = $class::getDefaultTaxonomy();
				if ($defaultTax !== null && $defaultTax !== 'category') {
					$typeFilters[$defaultTax] = $typeFilters['category'];
					unset($typeFilters['category']);
				}
			}
        
			$result = $this->findSingleType($type, array_merge($mergedFilters, ['post_type' => $type]));
			Logger::debug( count($result['posts']).' found for postType: '.$type, null, $logCtx );
			$allPosts   = array_merge($allPosts, $result['posts'] ?? []);
			$totalFound += $result['pagination']['found'] ?? 0;
		}
		//Logger::debug( count($allPosts).' posts found', null, $logCtx );
	
		// Re-sort if requested — title sort is the common case
		$orderby = $filters['orderby'] ?? null;
		$order   = strtoupper($filters['order'] ?? 'ASC');
	
		if ($orderby === 'title') {
		    //Logger::debug( 'About to attempt usort by WP Post title', null, $logCtx );
			usort($allPosts, function (WP_Post $a, WP_Post $b) use ($order) {
				$cmp = strcasecmp($a->post_title, $b->post_title);
				return $order === 'DESC' ? -$cmp : $cmp;
			});
		}
	
		// Apply limit after merge+sort if one was requested
		$limit = (int) ($filters['limit'] ?? -1);
		if ($limit > 0) {
			$allPosts = array_slice($allPosts, 0, $limit);
		}
		//Logger::debug( count($allPosts).' posts found after limit applied', null, $logCtx );
	
		return [
			'posts'      => $allPosts,
			'pagination' => [
				'found'     => $totalFound,
				'max_pages' => 1, // merged result treated as single page
				'paged'     => 1,
			],
			'debug' => ['merged_types' => $postTypes],
		];
	}
	
	private function emptyResult(array $filters): array
	{
		return [
			'posts'      => [],
			'pagination' => ['found' => 0, 'max_pages' => 0, 'paged' => $filters['paged'] ?? 1],
			'debug'      => ['error' => 'handler missing'],
		];
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
		$postTypes = [];

		foreach( $this->getPostTypeHandlerClasses() as $class ) {
			try {
				$handler = new $class();
				$slug = $handler->getSlug();
				$label = $handler->getLabels()['singular_name']; // or getLabel()
				$postTypes[ $slug ] = $label;
			} catch( \Throwable $e ) {
				Logger::error( "Error in post type handler {$class}", $e->getMessage() );
			}
		}

		//Logger::Logger::debug("postTypes", $postTypes, 'wxc' );

		return $postTypes;
	}
}
