<?php

namespace atc\WXC;

use atc\WXC\App;
use atc\WXC\Logger;
use atc\WXC\Contracts\ModuleInterface;
use atc\WXC\Templates\ViewLoader;
use atc\WXC\PostTypes\PostTypeHandler;
use atc\WXC\Shortcodes\ShortcodeManager;

use WP_Post;

abstract class Module implements ModuleInterface
{
    protected ?string $moduleSlug = null;

    /** @return array<class-string<PostTypeHandler>> */
	abstract public function getPostTypeHandlerClasses(): array;

    public function boot(): void
    {
		$logCtx = ['wxc'];
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

            if ( ! in_array( $handlerClass::getSlug(), $enabledSlugs, true ) ) {
				continue;
			}

            ( new $handlerClass() )->boot();
		}

        ShortcodeManager::add(WXC\Shortcodes\WXCListShortcode::class);
    }

	public function getSlug(): string
	{
		return $this->detectModuleSlugFromNamespace();
	}

	// Human-readable label derived from the module namespace.
	public function getName(): string
	{
		$parts = explode( '\\', static::class );
		$name  = end( $parts ) === 'Module' && isset( $parts[ count( $parts ) - 2 ] )
			? $parts[ count( $parts ) - 2 ]
			: ( new \ReflectionClass( $this ) )->getShortName();

		return ucwords( str_replace( '_', ' ', $name ) );
	}

    /**
     * Returns a slug => label map of all post types defined by this module.
     * Useful for settings UIs. For query purposes, use findViaHandler() instead.
     */
    public function getPostTypes(): array
    {
        $postTypes = [];

        foreach ( $this->getPostTypeHandlerClasses() as $class ) {
            try {
                $postTypes[ $class::getSlug() ] = $class::getLabels()['singular_name'];
            } catch ( \Throwable $e ) {
                Logger::error( "Error in post type handler {$class}", $e->getMessage() );
            }
        }

        return $postTypes;
    }

    // -------------------------------------------------------------------------
    // Query
    // -------------------------------------------------------------------------

    /**
     * Find posts of one or more post types, dispatching to the correct handler.
     *
     * @param string|string[] $postType
     */
	protected function findViaHandler(string|array $postType, array $filters): array
	{
		$postTypes = (array) $postType;
		//Logger::debug( 'postTypes', $postTypes, ['wxc', 'query'] );
		//Logger::debug( 'filters', $filters, ['wxc', 'query'] );
	
        return count( $postTypes ) === 1
            ? $this->findSingleType( $postTypes[0], $filters )
            : $this->findAcrossTypes( $postTypes, $filters );
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
	
    /**
     * Merge results across multiple post types.
     *
     * Pagination is not well-defined across merged result sets; results are
     * fetched in full, merged, re-sorted, and then limited if requested.
     * A generic 'category' filter is resolved per-type to each handler's
     * default taxonomy before dispatching.
     */
	private function findAcrossTypes(array $postTypes, array $filters): array
	{
		$logCtx = ['wxc', 'query'];
		//Logger::debug( 'postTypes', $postTypes, $logCtx );
		
		// Pagination is not well-defined across merged result sets.
		// Fetch all matching posts from each type and merge.
		$mergedFilters = array_merge($filters, ['limit' => -1, 'paged' => 1]);
        $map           = App::ctx()->getActivePostTypes();
	
		$allPosts = [];
		$totalFound = 0;
	
		foreach ($postTypes as $type) {
		    //Logger::debug( 'About to run find for postType [' . $type .']', null, $logCtx );
		    $class   = $map[$type] ?? null;
            $typeFilters = $mergedFilters;
		    
		    // Resolve generic 'category' to this type's default taxonomy.
            // A direct taxonomy key always takes precedence over 'category'.
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
			$result = $this->findSingleType($type, array_merge($typeFilters, ['post_type' => $type]));
			Logger::debug( count($result['posts']).' found for postType: '.$type, null, $logCtx );
			$allPosts   = array_merge($allPosts, $result['posts'] ?? []);
			$totalFound += $result['pagination']['found'] ?? 0;
		}
		//Logger::debug( count($allPosts).' posts found', null, $logCtx );
	
        // Re-sort if requested — title sort is the common case.
		$orderby = $filters['orderby'] ?? null;
		$order   = strtoupper($filters['order'] ?? 'ASC');
	
		if ($orderby === 'title') {
		    //Logger::debug( 'About to attempt usort by WP Post title', null, $logCtx );
			usort($allPosts, function (WP_Post $a, WP_Post $b) use ($order) {
				$cmp = strcasecmp($a->post_title, $b->post_title);
				return $order === 'DESC' ? -$cmp : $cmp;
			});
		}
	
        // Apply limit after merge and sort.
		$limit = (int) ($filters['limit'] ?? -1);
		if ($limit > 0) {
			$allPosts = array_slice($allPosts, 0, $limit);
		}
		//Logger::debug( count($allPosts).' posts found after limit applied', null, $logCtx );
	
		return [
			'posts'      => $allPosts,
			'pagination' => [
				'found'     => $totalFound,
                'max_pages' => 1,
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
	
	// -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------
    
	protected function registerDefaultViewRoot(): void
	{
		$slug = $this->detectModuleSlugFromNamespace();

		if ( ! ViewLoader::hasViewRoot( $slug ) ) {
			$reflector = new \ReflectionClass( $this );
            ViewLoader::registerModuleViewRoot( $slug, dirname( $reflector->getFileName() ) . '/Views' );
		}
	}

	protected function detectModuleSlugFromNamespace(): string
	{
		if ( isset( $this->moduleSlug ) ) {
			return $this->moduleSlug;
		}

		$parts = explode( '\\', static::class ); // Example: smith\Rex\Modules\Supernatural\Module → supernatural
        $key   = array_search( 'Modules', $parts, true );

        $this->moduleSlug = ( $key !== false && isset( $parts[ $key + 1 ] ) )
            ? strtolower( $parts[ $key + 1 ] )
            : strtolower( ( new \ReflectionClass( $this ) )->getShortName() );

		return $this->moduleSlug;
	}
}
