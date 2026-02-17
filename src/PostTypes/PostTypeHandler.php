<?php

namespace atc\WXC\PostTypes;
// TODO: move this and BaseHandler to WXC\Handlers\ ?

use atc\WXC\App;
use atc\WXC\BaseHandler;
use atc\WXC\Traits\AppliesTitleArgs;
use atc\WXC\Query\PostQuery;
use atc\WXC\Templates\ViewLoader;
//
use atc\WXC\Utils\ClassInfo;

abstract class PostTypeHandler extends BaseHandler
{
	use AppliesTitleArgs;

	// Property to store the post object
    protected ?\WP_Post $post = null; //protected $post; // better private?
    protected const TYPE = 'post_type';

    /** @var array<string,string> Cache: post_type => handler FQCN */
    protected static array $handlerClassCache = [];

    /** @var array<int,self> Cache: post_id => handler instance */
    protected static array $perPostCache = [];

    // Constructor to set the config and post object
    /*public function __construct( array $config = [], ?\WP_Post $post = null )
    {
        parent::__construct( $config, $post );
    }*/
    // Constructor
	public function __construct(array $config = [], ?\WP_Post $post = null)
	{
		parent::__construct($config, $post);
		$this->post = $post;
	}

    public function boot(): void
	{
        add_filter( 'the_content', [ self::class, 'appendCustomContent' ], 15 );
	}
	
	public function getSlug(): string
    {
        return (string)$this->getConfig()['slug'];
    }
    
	// Optional explicit setter (handy for guarantees/safety-net)
	// TBD: is this still needed? Redundant w/ constructor...
	public function setPost(?\WP_Post $post): static
	{
		$this->post = $post;
		return $this;
	}

	public function getPost(): ?\WP_Post
	{
		return $this->post;
	}

	 /**
	 * Optional spec hook. Child classes override this only if they need
	 * custom query behavior (date ranges, CPT-specific defaults, etc.).
	 *
	 * Expected keys if provided:
	 * - 'cpt' (string)
	 * - 'date_meta' => ['key' OR 'start_key'+'end_key', 'meta_type' => 'DATE'|'DATETIME'|'NUMERIC', 'key_type' => 'single'|'rows'|'serialized'] -- TODO: detect field type from key?
	 * - 'taxonomies' => [ 'event_category', ... ]
	 * - 'defaults'   => ['limit','order','orderby','view']
	 * - 'allowed_orderby' => [...]
	 * - 'default_view'    => 'list'|'grid'|'table'
	 */
	protected static function getQuerySpec(): array
	{
		return [];
	}

    public static function queryDefaults(): array
    {
        //error_log( "PostTypeHandler::queryDefaults" );
        $spec = static::getQuerySpec();
        //if ( isset($spec['cpt']) ) { error_log( "spec['cpt']: " . $spec['cpt'] ); } else { error_log( "spec['cpt'] not set" ); }
        $ptype = $spec['cpt'] ?? (static::resolvePostTypeFromContext() ?? '');
        //error_log( "ptype: " . $ptype );

        $defaults = array_merge([
            'post_type'      => $ptype,
            'post_status'    => 'publish',
            'view'           => $spec['default_view'] ?? 'list',
            //'limit'          => $spec['defaults']['limit']  ?? 10,
            'order'          => $spec['defaults']['order']  ?? 'ASC',
            'orderby'        => $spec['defaults']['orderby']?? 'meta_value',
            //'scope'          => '',
            //'paged'          => '',
        ], self::taxonomyDefaultInputs($spec));

        /** @var array $filtered */
        $filtered = apply_filters('wxc_generic_query_defaults', $defaults, $spec);
        return $filtered;
    }
    
    ///// URL parameters stuff

    // WIP! 10/5/25
	public static function allowedUrlParams(): array { return []; } // default empty -- set per CPT

	/**
	 * URL-param: scope → lightweight token normalization.
	 * Semantics are enforced later by ScopedDateResolver.
	 */
	public static function sanitizeScopeParam(mixed $value): ?string
	{
	    error_log('[sanitizeScopeParam] value (as received): ' . $value);

		if ($value === null) { return null; }
		if (is_array($value)) { $value = reset($value); }
		$value = strtolower(trim((string)$value));
		if ($value === '') { return null; }
		$value = preg_replace('/[^a-z0-9,_-]/', '', $value) ?? '';
		return $value !== '' ? $value : null;
	}
	
	/**
	 * URL-param: generic taxonomy terms (slugs).
	 * Accepts single string or CSV/array, returns unique slug array.
	 */
	public static function sanitizeTermSlugsParam(mixed $value): array
	{
		$raw = [];
		if (is_array($value)) { $raw = $value; }
		elseif ($value !== null && $value !== '') { $raw = explode(',', (string)$value); }
	
		$slugs = [];
		foreach ($raw as $item) {
			$slug = strtolower(trim((string)$item));
			$slug = preg_replace('/[^a-z0-9_-]/', '', $slug) ?? '';
			if ($slug !== '') { $slugs[] = $slug; }
		}
		return array_values(array_unique($slugs));
	}
	
	//////// END URL parameters stuff

    protected static function resolvePostTypeFromContext(): ?string
	{
		//error_log( "PostTypeHandler::resolvePostTypeFromContext" );
		try {
			$ctx = App::ctx();
			$map = is_array($ctx->getActivePostTypes()) ? $ctx->getActivePostTypes() : [];
			foreach ($map as $ptype => $class) {
				if ($class === static::class) {
					//error_log( "resolvePostTypeFromContext returning ptype: " . $ptype );
					return (string) $ptype;
				}
			}
		} catch (\Throwable $e) {
			// ignore; return null
		}
		return null;
	}

    public static function normalizeFilters(array $input): array
    {
        $spec = static::getQuerySpec();
        $in = array_merge(static::queryDefaults(), $input);
        
        // Limit (per-page)
        $limit = isset($in['limit']) ? (int)$in['limit'] : (isset($in['per_page']) ? (int)$in['per_page'] : 10);
        
        // Pagination
		$qv    = (int) get_query_var('paged');
		$paged = isset($in['paged']) && $in['paged'] !== '' ? (int) $in['paged'] : ( $qv > 0 ? $qv : 1 );
		if ( $paged < 1 ) { $paged = 1; }

        
        // Order
        $order = strtoupper((string) $in['order']);
        if (!in_array($order, ['ASC','DESC'], true)) { $order = 'ASC'; }

        $allowedOrderby = $spec['allowed_orderby'] ?? ['meta_value','date','title','menu_order','modified'];
        $orderby = (string) $in['orderby'];
        if (!in_array($orderby, $allowedOrderby, true)) {
            $orderby = $spec['defaults']['orderby'] ?? 'meta_value';
        }

        // Scope (string) or explicit {start,end}
        $scope = null;
        if (isset($in['scope']) && $in['scope'] !== '') { // change to !empty?
            $scope = (string) $in['scope'];
        } elseif ( isset($in['start_date']) && isset($in['end_date']) && ($in['start_date'] ?? '') !== '' || ($in['end_date'] ?? '') !== '') {
            $scope = [
                'start' => $in['start_date'] !== '' ? (string) $in['start_date'] : null,
                'end'   => $in['end_date']   !== '' ? (string) $in['end_date']   : null,
            ];
        }
        
        // Date Meta
        $dateMeta = (isset($in['date_meta']) && is_array($in['date_meta'])) ? $in['date_meta'] : [];
        
        // Meta
        $meta = (isset($in['meta']) && is_array($in['meta'])) ? $in['meta'] : [];

        // Taxonomy inputs: accept CSV per taxonomy key
        $taxInputs = self::parseTaxInputs($spec, $in);

        $normalized = [
            'post_type'   => (string) $in['post_type'],
            'post_status' => (string) $in['post_status'],
            'view'        => in_array($in['view'], ['list','grid','table'], true) ? $in['view'] : ($spec['default_view'] ?? 'list'),
            'limit'       => max(1, (int) $limit), //max(1, (int) $in['limit']),
            'order'       => $order,
            'orderby'     => $orderby,
            'scope'       => $scope,
            'date_meta'   => $dateMeta, // ??? wip
            'meta'        => $meta,
            'tax_inputs'  => $taxInputs, // map: taxonomy => [slugs]
            'paged'       => $paged,
        ];

        /** @var array $filtered */
        $filtered = apply_filters('wxc_generic_normalize_filters', $normalized, $input, $spec);
        return $filtered;
    }

    public static function buildQueryParams(array $normalized): array
    {
        //error_log('[buildQueryParams::find] normalized: ' . print_r($normalized, true)); // ok
        
        $spec = static::getQuerySpec();
        //error_log('[buildQueryParams::find] spec: ' . print_r($spec, true));
        
        $tax = [];
        foreach (($normalized['tax_inputs'] ?? []) as $taxonomy => $slugs) {
            if (!empty($slugs)) {
                $tax[$taxonomy] = $slugs;
            }
        }

        // Date meta spec: either single 'key' or start/end keys
        // Prioritize passed params over CPT spec(?)
        if ( isset($normalized['date_meta']) ) { $dateMeta = $normalized['date_meta']; } elseif ( isset($spec['date_meta']) ) { $dateMeta = $spec['date_meta']; }
        //$dateMeta = $spec['date_meta'] ?? [];
        $metaKeyForSort = $normalized['orderby'] === 'meta_value'
            ? ($dateMeta['key'] ?? $dateMeta['start_key'] ?? null)
            : null;
        
        //
        $orderby = $normalized['orderby'];
		$metaKeyForSort = null;
		
		if ($orderby === 'meta_value') {
			$metaKeyForSort = $dateMeta['key'] ?? $dateMeta['start_key'] ?? null;
		
			if ($metaKeyForSort === null) {
				// No meta key to sort on → fall back
				$orderby = $spec['defaults']['orderby'] ?? 'date';
			} elseif (($dateMeta['cast'] ?? null) === 'NUMERIC') {
				// Numeric date/meta → use meta_value_num for correct sort
				$orderby = 'meta_value_num';
			}
		}
        
        $params = [
			'post_type'      => (string)$normalized['post_type'],
			'post_status'    => (string)$normalized['post_status'],
			'paged'          => (int)$normalized['paged'],
			'posts_per_page' => (int)$normalized['limit'], // ??? WIP
			'order'          => (string)$normalized['order'],
			'orderby'        => $orderby,
			'meta_key'       => $metaKeyForSort,   // may be null; OK
			'date_meta'      => $dateMeta ?: null, // consumed downstream
			'scope'          => $normalized['scope'], // string|array|null; OK
		];
		
        if (!empty($normalized['meta'])) {
            $params['meta'] = $normalized['meta'];
        }
        
        if ($tax) {
			$params['tax'] = $tax;
		}
		
		//error_log('[buildQueryParams::find] params: ' . print_r($params, true));
		
		// Trim nulls while preserving 0/false
		$params = array_filter(
			$params,
			static fn($v) => $v !== null && ($v !== [] || is_array($v) === false)
		);
		//error_log('[buildQueryParams::find] params after trim: ' . print_r($params, true));

        /** @var array $filtered */
        //$filtered = apply_filters('wxc_generic_query_params', $params, $normalized, $spec);
        $filtered = $params; // tft
        
        //error_log('[buildQueryParams::find] filtered: ' . print_r($filtered, true));
        return $filtered;
    }
    
    // TODO: standardize terminology for "find" methods -- filters? params?
    public static function find(array $filters): array
    {
        error_log('[PostTypeHandler::find] filters: ' . print_r($filters, true));
        
        $normalized = static::normalizeFilters($filters);
        //error_log('[PostTypeHandler::find] normalized filters: ' . print_r($normalized, true));
        
        $params = static::buildQueryParams($normalized);
        //error_log('[PostTypeHandler::find] params: ' . print_r($params, true));

        $query  = new PostQuery();
        $result = $query->find($params);
        
        if ( isset($params['scope']) ) { $scope = $params['scope']; } elseif ( isset($filters['scope']) ) { $scope = $filters['scope']; } else { $scope = ""; }

        $payload = [
            'posts'      => $result['posts'] ?? [],
            'pagination' => [
                'found'     => $result['found']     ?? 0, // maybe move this up a level? i.e. not part of pagination array
                'max_pages' => $result['max_pages'] ?? 0,
                'paged'     => $normalized['paged'],
            ],
        ];

        //if (defined('WXC_DEBUG') && WXC_DEBUG) {
            $payload['debug'] = [
                'args'          => $result['args']          ?? [],
                'query_request' => $result['query_request'] ?? '',
                'params'        => $params,
                'filters'       => $normalized,
                'scope'      => $scope, // wip -- pass it back so we can keep track of final scope after qv checks etc.
            ];
        //}

        /** @var array $filtered */
        //$filtered = apply_filters('wxc_generic_result', $payload, $params, $normalized, static::getQuerySpec());
        $filtered = $payload; // tft
        return $filtered;
    }

    /** @internal: helper to build empty tax inputs from spec */
    protected static function taxonomyDefaultInputs(array $spec): array
    {
        $out = [];
        foreach ($spec['taxonomies'] ?? [] as $tax) {
            $out[$tax] = '';
        }
        // Also allow optional start_date/end_date passthrough for explicit windows
        $out['start_date'] = '';
        $out['end_date'] = '';
        return $out;
    }

    /** @internal: turn CSV strings into arrays per taxonomy key */
    protected static function parseTaxInputs(array $spec, array $in): array
    {
        $map = [];
        foreach ($spec['taxonomies'] ?? [] as $tax) {
            $raw = isset($in[$tax]) ? (string) $in[$tax] : '';
            if ($raw === '') {
                $map[$tax] = [];
                continue;
            }
            $map[$tax] = array_values(array_filter(array_map('trim', explode(',', $raw))));
        }
        return $map;
    }

    public function getCapType(): array
    {
        $capType = $this->getConfig()['capability_type'] ?? [];
        if ( empty($capType) ) { $capType = [ $this->getSlug(), $this->getPluralSlug() ]; } else if ( !is_array($capType) ) { $capType = [$capType, "{$capType}s" ]; };
        return $capType;
        //return $this->getConfig()['capability_type'] ?? [ $this->getSlug(), $this->getPluralSlug() ];
    }

    public function getSupports(): array
    {
        return $this->getConfig()['supports'] ?? [ 'title', 'author', 'editor', 'revisions' ];
    }

    public function getTaxonomies(): array
    {
        //$taxonomies = $this->getConfig()['taxonomies'] ?? [ 'admin_tag' => 'AdminTag' ];
        return $this->getConfig()['taxonomies'] ?? [ 'admin_tag' ];
        // WIP 08/26/25 -- turn this into an array of slug -> className pairs
        //return $taxonomies;
        //// WIP 08/26/25 -- figure out how to get fqcn for bare class names

        // Wherever you attach/ensure taxonomies, resolve them:
        //$taxonomyClasses = $this->resolveTaxonomyClasses($this->getConfig('taxonomies') ?? []);
        // Example: hand them to your registrar, or call static register() if you use handlers.
        // $this->taxonomyRegistrar->ensureRegistered($taxonomyClasses);

        //return $this->getConfig()['taxonomies'] ?? [ 'admin_tag' => 'AdminTag' ];
        //return $taxonomyClasses;
    }

    public function getMenuIcon(): ?string
    {
        return $this->getConfig()['menu_icon'] ?? 'dashicons-superhero';
    }

    /**
     * Get the handler FQCN for a CPT slug, or null if not WXC-managed.
     */
    public static function getHandlerClassForPostType(string $postType): ?string
    {
        if (isset(self::$handlerClassCache[$postType])) {
            return self::$handlerClassCache[$postType];
        }

        $activePostTypeSlugs = (array) apply_filters('wxc_active_post_types', []);

        if ( !in_array($postType, $activePostTypeSlugs, true) ) {
            //error_log( 'postType '.$postType.' is NOT a WXC-managed post type' );
            return null;
        }
        //error_log( 'postType '.$postType.' is a WXC-managed post type' );

        $activePostTypes = App::ctx()->getActivePostTypes(); // ['person' => \...Person::class]
        if ( empty( $activePostTypes ) ) {
			return null;
		}

        $class = $activePostTypes[$postType] ?? null;

        if (is_string($class) && class_exists($class)) {
            self::$handlerClassCache[$postType] = $class;
            return $class;
        }

        return null;
    }

    /**
     * Get the handler instance for a post (or current global $post).
     * Returns a concrete subclass of PostTypeHandler, cached per post ID.
     */
    //public static function getHandlerForPost(\WP_Post|int|null $post = null): ?self
    public static function getHandlerForPost(\WP_Post $post): ?static
    {
        // Normalize $post
		if ($post === null) {
			$post = get_post();
		} elseif (is_int($post)) {
			$post = get_post($post);
		}
		if (!$post instanceof \WP_Post) {
			return null;
		}

        // Per-post cache
        $pid = (int) $post->ID;
        if (isset(self::$perPostCache[$pid])) {
            return self::$perPostCache[$pid];
        }

        // Resolve handler class for this CPT
        $pt = $post->post_type ?: get_post_type($post);
		if (!$pt) {
			return null;
		}

        $class = self::getHandlerClassForPostType($pt);
        if (!$class || !class_exists($class)) {
			return null;
		}

        // Handlers in WXC accept (?\WP_Post $post = null)
        /** @var self $instance */
        $instance = new $class($post);

        // Safety-net: force the post onto the instance
		if (method_exists($instance, 'setPost')) {
			$instance->setPost($post);
		}

        return self::$perPostCache[$pid] = $instance;
    }

    ///

    public function getPostId(): int
	{
		return $this->post instanceof \WP_Post ? (int) $this->post->ID : 0;
	}
    /**
	 * Get the post ID, optionally for a provided post.
	 */
	/*public function getPostId(?\WP_Post $post = null): ?int
	{
		$p = $post ?? self::getPost();
		//$p = $post ?? $this->getPost();
		return $p ? (int)$p->ID : null;
	}*/

	/**
	 * Get post meta. If $key is null, returns all meta (array).
	 * If $key is provided, returns get_post_meta($id, $key, $single).
	 * Returns [] (no key) or null (with key) when no post is set.
	 */
	public function getPostMeta(?string $key = null, mixed $default = null): mixed
	{
		$id = $this->getPostId();
		if ($id <= 0) {
			return $default;
		}

		if ($key === null) {
			// Return all meta for this post
			return get_post_meta($id);
		}

		$val = get_post_meta($id, $key, true);
		return ($val === '' || $val === null) ? $default : $val;
	}


    // Method to get the post title
    /*public function get_post_title()
    {
        return get_the_title($this->getPostID());
    }*/

    //public function getCustomTitleArgs(): array
	public function getCustomTitleArgs( \WP_Post $post ): array
	{
		return [];
	}

	// WIP -- maybe this goes elsewhere?
	function getRelatedPosts( $args = [] )
	{
		// Defaults
		$defaults = array(
			'post_id'           => null,
			'related_post_type' => null,
			'related_field_name'=> null,
			'limit'             => "-1",
			'scope'             => null,
		);
		$args = wp_parse_args( $args, $defaults );
		// TBD: use extract? maybe not as safe, though
		$post_id = $args['post_id'];
		$related_post_type = $args['related_post_type'];
		$related_field_name = $args['related_field_name'];
		$limit = $args['limit'];
		$scope = $args['scope'];
		//
		$arrPosts = [];

		// If we don't have actual values for all parameters, there's not enough info to proceed
		if ($post_id === null || $related_field_name === null || $related_post_type === null) { return null; }

		$related_id = null; // init

		// Set args
		$wp_args = array(
			'post_type'   => $related_post_type,
			'post_status' => 'publish',
			'posts_per_page' => $limit,
			'meta_query' => array(
				array(
					'key'     => $related_field_name,
					'value'   => $post_id,
				)
			),
			'orderby'        => 'title',
			'order'            => 'ASC',
		);

		// Run query
		$related = new WP_Query( $wp_args );

		// Loop through the records returned
		if ( $related->posts && count($related->posts) > 0 ) {

			return $related->posts;
			/*
			if ( $limit == 1 ) {
				$p = $related->posts[0];
				$info = $p->ID; // ok?
			} else {
				$info = $related_posts->posts;
			}
			*/
			/*
			$info .= "<br />";
			//$info .= "related_posts: ".print_r($related_posts,true);
			$info .= "related_posts->posts:<pre>".print_r($related_posts->posts,true)."</pre>";
			$info .= "wp_args:<pre>".print_r($wp_args,true)."</pre>";
			*/

		} else {
			//$info = "No matching posts found for wp_args: ".print_r($wp_args,true);
		}

		return $arrPosts;
	}

	// TODO: modify to allow for before/after/replace of $content with custom content(?)
	public static function appendCustomContent( string $content ): string
	{
		$post = get_post();
		$postType = get_post_type();
		error_log( 'PostTypeHandler -> postType: ' . $postType . '' );
	
		if ( ! is_singular( $postType ) || ! in_the_loop() || ! is_main_query()  || !$post instanceof \WP_Post) {
			return $content;
		}
	
		$handlerClass = self::getHandlerClassForPostType($postType);
		if ($handlerClass) {
		    $module = strtolower((string) ClassInfo::getModuleKey($handlerClass));
		} else {
		    return $content;
		}
	
		// Prepare view variables - let the handler prepare its own data
		$vars = ['post' => $post];
		
		// Get handler instance and let it prepare view data if it implements the method
		$handler = self::getHandlerForPost($post);
		if ($handler && method_exists($handler, 'prepareViewData')) {
			$preparedData = $handler->prepareViewData();
			$vars = array_merge($vars, $preparedData);
		}
		if ( $postType == "whx4_event" ) { $postType = "event"; error_log( '[PostTypeHandler] postType corrected to: ' . $postType . '' ); } // Tmp WIP
	
		$extra = ViewLoader::renderToString( 'content',
			// vars
			$vars,
			// specs
			[ 'kind' => 'partial', 'module' => $module, 'post_type' => $postType ]
		);
	
		// Render your CPT-specific template part via ViewLoader (cascade: child theme > parent theme > plugin)
		/*$extra = ViewLoader::render( '{$postType}/content', [
			'post' => get_post(),
		] );*/
	
		return $content . $extra;
	}
	// TODO: modify to allow for before/after/replace of $content with custom content(?)
	// v1 -- works fine but only passes post, no data
	/*
	public static function appendCustomContent( string $content ): string
	{
	    $post = get_post();
	    $postType = get_post_type();

		if ( ! is_singular( $postType ) || ! in_the_loop() || ! is_main_query()  || !$post instanceof \WP_Post) {
			return $content;
		}

        $handlerClass = self::getHandlerClassForPostType($postType);
        $module = strtolower((string) ClassInfo::getModuleKey($handlerClass));

        $extra = ViewLoader::renderToString( 'content',
            // vars
            [ 'post' => $post ],
            // specs
            [ 'kind' => 'partial', 'module' => $module, 'post_type' => $postType ]
        );

        // Render your CPT-specific template part via ViewLoader (cascade: child theme > parent theme > plugin)
        /*$extra = ViewLoader::render( '{$postType}/content', [
            'post' => get_post(),
        ] );*//*

        return $content . $extra;
	}*/

	//public function getCustomContent(\WP_Post $post): string
	public function getCustomContent()
	{
		$post_id = $this->getPostID();

		// This function retrieves supplementary info -- the regular content template (content.php) handles title, content, featured image

		// Init
		$info = "";
		$ts_info = "";

		$ts_info .= "post_id: ".$post_id."<br />";

		if ( $post_id === null ) { return false; }

		$info .= $ts_info;

		return $info;

	}
	
	/**
	 * Resolve scope from $atts with optional override from query vars.
	 *
	 * Order of precedence: $atts['scope'] → query_var('rex_scope'|'scope') → $_GET → $default
	 *
	 * @param array<string,mixed> $atts
	 * @return string|array|null  Sanitized scope (string like "2020-2025" or array form), or null if none.
	 */
	public static function getScopeFromRequest(array $atts = [], string $default = 'this_year')
	{
		error_log( "PostTypeHandler::getScopeFromRequest" );
		error_log('[getScopeFromRequest] atts: ' . print_r($atts, true));
		
		$scope = $atts['scope'] ?? $default;
	
		// Prefer query_var (avoids notices), then GET fallback
		$qv = get_query_var('scope');
		if (empty($qv)) { $qv = get_query_var('wxc_scope'); }
		if (empty($qv) && isset($_GET['scope'])) { $qv = (string) $_GET['scope']; }
		if (empty($qv) && isset($_GET['wxc_scope'])) { $qv = (string) $_GET['wxc_scope']; }
		
		if (!empty($qv)){
		    $sanitized = static::sanitizeScopeParam($qv);
		    if ($sanitized !== null) { $scope = $sanitized; }
		}
		
		error_log('[getScopeFromRequest] returning scope: ' . $scope);
	
		return $scope;
	}
	
	/**
	 * Resolve terms for a taxonomy with support for inclusion/exclusion patterns
	 * 
	 * @param string $taxonomy The taxonomy name
	 * @param mixed $param The filter parameter - can be:
	 *   - 'all': all terms
	 *   - 'active': terms with posts in current scope (requires $context with 'scope')
	 *   - CSV or array of slugs for inclusion
	 *   - CSV or array with '-' prefix for exclusion
	 * @param array $context Optional context (e.g., ['scope' => '2024', 'post_type' => 'transaction'])
	 * @return \WP_Term[] Array of resolved terms
	 */
	protected function resolveTerms(string $taxonomy, $param, array $context = []): array
	{
		// String modes
		if (is_string($param)) {
			$mode = strtolower(trim($param));
			if ($mode === 'all') {
				return $this->getTermsForTaxonomy($taxonomy, [], false);
			}
			if ($mode === 'active') {
				return $this->getTermsForTaxonomy($taxonomy, $context, true);
			}
			// fall through to slug handling
		}
	
		// Handle slugs (with optional exclusion prefix)
		$slugs = static::sanitizeTermSlugsParam($param);
		if ($slugs === []) { return []; }
		
		// Check for exclusions (slugs starting with -)
		$exclusions = array_filter($slugs, fn($s) => str_starts_with($s, '-'));
		if (!empty($exclusions)) {
			// Remove the - prefix
			$exclusions = array_map(fn($s) => ltrim($s, '-'), $exclusions);
			
			// Get all terms
			$allTerms = $this->getTermsForTaxonomy($taxonomy, $context, false);
			
			// Find excluded term IDs (including descendants)
			$excludedIds = [];
			foreach ($allTerms as $term) {
				if (in_array($term->slug, $exclusions, true)) {
					$excludedIds[] = $term->term_id;
					// Add all descendants
					$excludedIds = array_merge($excludedIds, $this->getDescendantTermIds($term->term_id, $allTerms));
				}
			}
			
			// Filter out excluded terms and their descendants
			return array_filter($allTerms, function($term) use ($excludedIds) {
				return !in_array($term->term_id, $excludedIds, true);
			});
		}
	
		// Inclusion logic
		$found = get_terms([
			'taxonomy'   => $taxonomy,
			'slug'       => $slugs,
			'hide_empty' => true,
		]);
	
		return (!is_wp_error($found) && is_array($found)) ? $found : [];
	}
	
	/**
	 * Get terms for a taxonomy, optionally filtered by active posts in scope
	 * 
	 * @param string $taxonomy Taxonomy name
	 * @param array $filters Query filters (may include 'scope')
	 * @param bool $activeInScope Whether to limit to terms on posts in scope
	 * @return \WP_Term[]
	 */
	protected function getTermsForTaxonomy(string $taxonomy, array $filters = [], bool $activeInScope = false): array
	{
		if (!$activeInScope) {
			$terms = get_terms([
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
			]);
			return is_array($terms) ? $terms : [];
		}
	
		// Active-in-scope: need to be overridden by child classes
		// that know how to fetch posts for their specific type
		return [];
	}
	
	/**
	 * Organize terms into a hierarchical structure with multiple levels
	 * 
	 * @param \WP_Term[] $terms Flat array of terms
	 * @return array ['parents' => [...], 'children' => [parent_id => [...]], 'depth' => int]
	 */
	public function organizeTermsHierarchically(array $terms): array
	{
		$parents = [];
		$children = [];
		$maxDepth = 0;
		
		// Build parent-child map
		foreach ($terms as $term) {
			if ($term->parent === 0) {
				$parents[] = $term;
			} else {
				if (!isset($children[$term->parent])) {
					$children[$term->parent] = [];
				}
				$children[$term->parent][] = $term;
			}
		}
		
		// Calculate max depth
		$termIds = array_map(fn($t) => $t->term_id, $terms);
		foreach ($terms as $term) {
			$depth = $this->calculateTermDepth($term, $termIds, $terms);
			$maxDepth = max($maxDepth, $depth);
		}
		
		return [
			'parents' => $parents,
			'children' => $children,
			'depth' => $maxDepth
		];
	}
	
	/**
	 * Calculate the depth of a term in the hierarchy
	 * 
	 * @param \WP_Term $term
	 * @param array $termIds All term IDs in the set
	 * @param \WP_Term[] $allTerms All terms
	 * @return int Depth (0 = root)
	 */
	private function calculateTermDepth(\WP_Term $term, array $termIds, array $allTerms): int
	{
		if ($term->parent === 0) {
			return 0;
		}
		
		// Only count depth if parent is in our set
		if (!in_array($term->parent, $termIds, true)) {
			return 0;
		}
		
		// Find parent term
		foreach ($allTerms as $t) {
			if ($t->term_id === $term->parent) {
				return 1 + $this->calculateTermDepth($t, $termIds, $allTerms);
			}
		}
		
		return 0;
	}
	
	/**
	 * Check if any terms in the list are hierarchically related
	 * 
	 * @param \WP_Term[] $terms
	 * @return bool
	 */
	public function hasHierarchicalRelationships(array $terms): bool
	{
		$termIds = array_map(fn($t) => $t->term_id, $terms);
		
		foreach ($terms as $term) {
			if ($term->parent !== 0 && in_array($term->parent, $termIds, true)) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Get all descendant term IDs for a given parent term
	 * 
	 * @param int $parentId Parent term ID
	 * @param \WP_Term[] $allTerms All terms to search through
	 * @return array Array of descendant term IDs
	 */
	private function getDescendantTermIds(int $parentId, array $allTerms): array
	{
		$descendants = [];
		
		foreach ($allTerms as $term) {
			if ($term->parent === $parentId) {
				$descendants[] = $term->term_id;
				// Recursively get this term's descendants
				$descendants = array_merge($descendants, $this->getDescendantTermIds($term->term_id, $allTerms));
			}
		}
		
		return $descendants;
	}
}

