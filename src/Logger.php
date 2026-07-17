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
    
    // Create a nicely-formatted entry to send to the standard error_log function, including the class and method from which Logger was called
    public static function log( string $message, string $level = self::DEBUG, mixed $data = null, string|array|null $context = null ): void
    {
        if ( ! self::shouldLog( $level, $context ) ) {
			return;
		}

        $caller = self::resolveCaller( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 5 ) );        
        $logType = $level !== self::DEBUG ? '[' . strtoupper( $level ) . '] ' : '';
        $entry = sprintf( '(%s::%s) %s%s', $caller['class'], $caller['function'], $logType, $message ); // no line nums
        //$entry = sprintf( '(%s::%s:%d) %s%s', $caller['class'], $caller['function'], $caller['line'], $logType, $message ); // wip
        
        if ( $data !== null ) {
			$dump = is_string( $data ) ? $data : print_r( $data, true );
			$entry .= ' | ' . preg_replace( '/\s+/', ' ', trim( $dump ) );
		}

        error_log( $entry );
    }
    
    public static function debug( string $message, mixed $data = null, string|array|null $context = null ): void { self::log( $message, self::DEBUG, $data, $context ); }
	public static function info(  string $message, mixed $data = null, string|array|null $context = null ): void { self::log( $message, self::INFO,  $data, $context ); }
	public static function warn(  string $message, mixed $data = null, string|array|null $context = null ): void { self::log( $message, self::WARN,  $data, $context ); }
	public static function error( string $message, mixed $data = null, string|array|null $context = null ): void { self::log( $message, self::ERROR, $data, $context ); }

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
     *  4. The dev flag is read from ?dev if present (and persisted to a cookie
     *     for the session), otherwise from the wxc_dev cookie. This keeps
     *     logging active across form submissions and redirects that drop
     *     the query string.
	 */
	private static function shouldLog( string $level, string|array|null $context = null ): bool
	{
		if ( $level === self::ERROR ) {
			return true;
		}
	
		$debugActive = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
					|| ( defined( 'WXC_DEBUG' ) && WXC_DEBUG );
	
		if ( ! $debugActive ) {
			return false;
		}
		
		$devParam = self::resolveDevParam();
		//$devParam = isset( $_GET['dev'] ) ? strtolower( trim( $_GET['dev'] ) ) : null;
		//error_log( 'devParam: ' . var_export( $devParam, true ) . ' | context: ' . var_export( $context, true ) ); // tft
	
		if ( $devParam === null ) {
			return false;
		}
	
		if ( $devParam === 'true' ) {
			return true;
		}
		
		$activeContexts = array_map( 'trim', explode( ',', $devParam ) );
		$callContexts = is_array( $context ) ? $context : [ $context ];
		
		return $context !== null && ! empty( array_intersect( $callContexts, $activeContexts ) );
	}
	
	/**
	 * Resolve the active dev flag from the query string, persisting it to a
	 * cookie so it survives redirects and form posts. Falls back to the
	 * existing cookie when no query param is present.
	 *
	 * Uses native PHP string handling rather than WP sanitizers, and guards
	 * cookie-related constants, since this can fire before WP's bootstrap
	 * has defined them.
	 */
	private static function resolveDevParam(): ?string
	{
		if ( isset( $_GET['dev'] ) ) {
			$value = self::sanitizeDevValue( (string) $_GET['dev'] );
	
			if ( ! headers_sent() ) {
				setcookie(
					'wxc_dev',
					$value,
					time() + HOUR_IN_SECONDS,
					defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
					defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : ''
				);
			}
	
			return $value;
		}
	
		if ( isset( $_COOKIE['wxc_dev'] ) ) {
			return self::sanitizeDevValue( (string) $_COOKIE['wxc_dev'] );
		}
	
		return null;
	}
	
	/** Strip a dev-flag value down to safe characters without relying on WP being loaded. */
	private static function sanitizeDevValue( string $value ): string
	{
		return strtolower( trim( preg_replace( '/[^a-z0-9,_\-]/i', '', $value ) ) );
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
                'line'     => $frame['line'] ?? 0,
            ];
        }
        return [ 'class' => 'unknown', 'function' => 'unknown' ];
    }
}