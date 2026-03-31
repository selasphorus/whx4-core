<?php

namespace atc\WXC;

/**
 * Centralised debug logging for WXC-based plugins.
 *
 * Gated by WP_DEBUG for all levels except ERROR, which always logs.
 * With WP_DEBUG_LOG enabled, output goes to wp-content/debug.log.
 *
 * Usage:
 *   Logger::debug( 'Booting', $someVar );
 *   Logger::warn( 'Unexpected value' );
 *   Logger::error( 'Critical failure' );
 *   Logger::log( 'Message', Logger::INFO, $data );
 */
class Logger
{
    public const DEBUG = 'debug';
    public const INFO  = 'info';
    public const WARN  = 'warn';
    public const ERROR = 'error';

    public static function log( string $message, string $level = self::DEBUG, mixed $data = null, ?string $context = null ): void
    {
        if ( ! self::shouldLog( $level, $context ) ) {
			return;
		}

        $caller = self::resolveCaller( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 5 ) );

        $entry = sprintf( '(%s::%s) [%s] %s', $caller['class'], $caller['function'], strtoupper( $level ), $message );

        /*if ( $data !== null ) {
            $entry .= ' | ' . ( is_string( $data ) ? $data : print_r( $data, true ) );
        }*/
        
        if ( $data !== null ) {
			$dump = is_string( $data ) ? $data : print_r( $data, true );
			$entry .= ' | ' . preg_replace( '/\s+/', ' ', trim( $dump ) );
		}

        error_log( $entry );
    }
    
    public static function debug( string $message, mixed $data = null, ?string $context = null ): void { self::log( $message, self::DEBUG, $data, $context ); }
	public static function info(  string $message, mixed $data = null, ?string $context = null ): void { self::log( $message, self::INFO,  $data, $context ); }
	public static function warn(  string $message, mixed $data = null, ?string $context = null ): void { self::log( $message, self::WARN,  $data, $context ); }
	public static function error( string $message, mixed $data = null, ?string $context = null ): void { self::log( $message, self::ERROR, $data, $context ); }

    // -------------------------------------------------------------------------

    /**
	 * Determine whether a log entry should be written.
	 *
	 * Resolution order:
	 *  1. ERROR always logs, regardless of debug mode or query param.
	 *  2. All other levels require WP_DEBUG or WXC_DEBUG to be true.
	 *  3. With debug active, the ?dev query param controls filtering:
	 *       - absent      → suppress everything below ERROR
	 *       - ?dev=true   → log all levels
	 *       - ?dev=<context>  → log only calls whose $context matches
	 *     Untagged non-error calls are suppressed when a specific context is set.
	 */
	private static function shouldLog( string $level, ?string $context = null ): bool
	{
		if ( $level === self::ERROR ) {
			return true;
		}
	
		$debugActive = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
					|| ( defined( 'WXC_DEBUG' ) && WXC_DEBUG );
	
		if ( ! $debugActive ) {
			return false;
		}
	
		//$devParam = get_query_var('dev'); // nope too early
		//$devParam = isset( $_GET['dev'] ) ? sanitize_key( $_GET['dev'] ) : null;
		$devParam = isset( $_GET['dev'] ) ? strtolower( trim( $_GET['dev'] ) ) : null;
		error_log( 'devParam: ' . var_export( $devParam, true ) . ' | context: ' . var_export( $context, true ) );
	
		if ( $devParam === null ) {
			return false;
		}
	
		if ( $devParam === 'true' ) {
			return true;
		}
	
		return $context !== null && $devParam === $context;
	}

    /** Return the first backtrace frame that isn't Logger itself. */
    private static function resolveCaller( array $trace ): array
    {
        foreach ( $trace as $frame ) {
            if ( ( $frame['class'] ?? '' ) === self::class ) {
                continue;
            }
            return [
                //'class'    => $frame['class'] ?? basename( $frame['file'] ?? 'unknown' ),
                'class' => isset( $frame['class'] )
					? basename( str_replace( '\\', '/', $frame['class'] ) )
					: basename( $frame['file'] ?? 'unknown' ),
                'function' => $frame['function'] ?? 'unknown',
            ];
        }
        return [ 'class' => 'unknown', 'function' => 'unknown' ];
    }
}