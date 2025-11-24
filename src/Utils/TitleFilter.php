<?php

namespace WXC\Utils;

use WXC\App;

class TitleFilter
{
    public static function boot(): void
    {
        //error_log( '=== TitleFilter::boot() ===' );
        //add_filter( 'the_title', [ self::class, 'filterTitle' ], 10, 2 );
    }

    public static function filterTitle( string $title, int $postId ): string
    {
        if ( is_admin() ) {
            return $title;
        }

        $post = get_post( $postId );

        if ( ! $post instanceof \WP_Post ) {
            return $title;
        }

        $postType = $post->post_type;
        if ( ! post_type_exists( $postType ) ) {
            return $title;
        }

        $args = self::$globalArgs[ $postType ] ?? []; //$args = self::$globalArgsByPostType[ $postType ] ?? []; //$args = self::getGlobalArgsForPostType( $postType );
        if ( empty( $args ) ) {
            return $title;
        }

        return self::renderTitle( $post, $title, $args );

        /*
        $args = self::normalizeTitleArgs( $args );

        // Check if this post type has extra dynamic args
        $handler = self::getPostTypeHandler( $postType, $post );

        if ( $handler && method_exists( $handler, 'getCustomTitleArgs' ) ) {
            $customArgs = $handler->getCustomTitleArgs();
            $args = array_merge( $args, $customArgs );
        }
        */
        /*
        if ( isset( self::$contextArgs[ $postId ] ) ) {
            $args = array_merge( $args, self::$contextArgs[ $postId ] );
        }
        */

        //return self::renderTitle( $args ); //return self::renderTitle( $post->ID, $args );

    }

    //public static function renderTitle( array $args ): string //int $postID,
    public static function renderTitle( \WP_Post $post, string $title, array $args ): string
    {
        if ( is_numeric( $post ) ) {
            $post = get_post( $post );
            $postId = $post->ID;
        }

        if ( ! $post instanceof \WP_Post ) {
            return $title; //return '';
        }

        //$post = is_numeric( $args['post'] ) ? get_post( $args['post'] ) : $args['post'];
        //$post = $postId && is_numeric( $postId ) ? get_post( $postId ) : null;

        $args = self::normalizeTitleArgs( $post, $args );
        //$args = self::normalizeTitleArgs( $post, array $overrides = [] );
        //$args = self::normalizeTitleArgs( $args );
        //$post = $args['post'] ?? null;

        $title = get_the_title( $post );

        // Apply line breaks
        if ( ! empty( $args['line_breaks'] ) ) {
            $title = str_replace( ': ', ":<br>", $title );
        }

        // Append designated content, if any -- move this after subtitle, maybe?
        if ( ! empty( $args['append'] ) ) {
            $title .= ' ' . esc_html( $args['append'] );
        }

        // Prepend designated content, if any
        if ( ! empty( $args['prepend'] ) ) {
            $title = $args['prepend'] . ' ' . $title;
        }

        // Append subtitle if enabled and supported
        if ( $args['show_subtitle'] ) {
            $subtitle = get_post_meta( $postId, 'subtitle', true );
            if ( $subtitle ) {
                $title .= sprintf(
                    '<div class="post-subtitle h%d">%s</div>',
                    (int) $args['hlevel_sub'],
                    esc_html( $subtitle )
                );
            }
        }
        /*
        if ( $args['show_subtitle'] ) {
            $subtitle = get_post_meta( $post->ID, 'rex_' . $post->post_type . '_subtitle', true );

            if ( $subtitle ) {
                $lineBreak = $args['line_breaks'] ? '<br>' : ' ';
                $title .= $lineBreak . '<' . $args['hlevel_sub'] . ' class="subtitle">' . esc_html( $subtitle ) . '</' . $args['hlevel_sub'] . '>';
            }
        }
        */

        return $title;
    }


    /**
     * Returns default arguments for post title rendering
     */
    protected static function getDefaultTitleArgs(): array
    {
        $defaults = [
            'line_breaks'    => false,
            'show_subtitle'  => false,
            'hlevel'         => 1,
            'hlevel_sub'     => 2,
            'hclass'         => 'entry-title',
            'hclass_sub'     => 'subtitle',
            'called_by'      => 'default',
            'link'           => false,
            'echo'           => false,
            'prepend'        => '', // aka/previously 'before'
            'append'         => '', // aka/previously 'after'
        ];
        /*
            $defaults = array(
            'the_title'		=> null, // optional override to set title via fcn arg, not via post
            'post'			=> null,
            'show_person_title' => false, // WIP
            'show_series_title' => false,
            'do_ts'			=> devmode_active( array("sdg", "titles") ),
        );
        */

        return $defaults;
    }

    /**
     * Normalizes the title args array by merging with type-specific defaults.
     */
    public static function normalizeTitleArgs( \WP_Post $post, array $args ): array
    {
        $postType = $post->post_type;

        $defaults = self::getDefaultTitleArgs( $postType );

        $globals = $postType && isset( self::$globalArgs[ $postType ] )
            ? self::$globalArgs[ $postType ]
            : [];

        // Merge per-post-type customizations
        $custom = [];

        //$handlerClass = self::$ctx?->getActivePostTypes()[ $postType ] ?? null; //$activePostTypes = self::$ctx?->getActivePostTypes()[ $postType ] ?? null;
        //error_log( 'normalizeTitleArgs >> activePostTypes: ' . print_r($activePostTypes, true) );
        /*if ( method_exists( $handlerClass, 'getCustomTitleArgs' ) ) {
            $handler = new $handlerClass();
            $custom = $handler->getCustomTitleArgs( $post );
        }*/

        // Merge in priority: user-supplied > custom > globals > defaults
        //return array_merge( $defaults, $custom, $overrides );
        return wp_parse_args( $args, $custom + $globals + $defaults ); //wp_parse_args( $args, $globals + $defaults ),
    }

    public static function setArgsForPost( int $post_id, array $args ): void
    {
        self::$contextArgs[ $post_id ] = $args;
    }

    protected static function getPostTypeHandler( string $postType, \WP_Post $post ): ?PostTypeHandler
    {
        $handlers = App::ctx()->getActivePostTypes();

        foreach ( $handlers as $handler ) {
            if ( $handler->getSlug() === $postType ) {
                return new $handler( $post );
            }
        }

        return null;
    }

}
