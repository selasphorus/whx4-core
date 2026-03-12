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
 *   Logger::log( 'Message', Logger::INFO, $context );
 */
class Logger
{
    public const DEBUG = 'debug';
    public const INFO  = 'info';
    public const WARN  = 'warn';
    public const ERROR = 'error';

    public static function log( string $message, string $level = self::DEBUG, mixed $context = null ): void
    {
        if ( ! self::shouldLog( $level ) ) {
            return;
        }

        $caller = self::resolveCaller( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 5 ) );

        $entry = sprintf( '(%s::%s) [%s] %s', $caller['class'], $caller['function'], strtoupper( $level ), $message );

        if ( $context !== null ) {
            $entry .= ' | ' . ( is_string( $context ) ? $context : print_r( $context, true ) );
        }

        error_log( $entry );
    }

    public static function debug( string $message, mixed $context = null ): void { self::log( $message, self::DEBUG, $context ); }
    public static function info(  string $message, mixed $context = null ): void { self::log( $message, self::INFO,  $context ); }
    public static function warn(  string $message, mixed $context = null ): void { self::log( $message, self::WARN,  $context ); }
    public static function error( string $message, mixed $context = null ): void { self::log( $message, self::ERROR, $context ); }

    // -------------------------------------------------------------------------

    /** ERROR always logs; all other levels require WP_DEBUG. */
    private static function shouldLog( string $level ): bool
    {
        return $level === self::ERROR || ( defined( 'WP_DEBUG' ) && WP_DEBUG );
    }

    /** Return the first backtrace frame that isn't Logger itself. */
    private static function resolveCaller( array $trace ): array
    {
        foreach ( $trace as $frame ) {
            if ( ( $frame['class'] ?? '' ) === self::class ) {
                continue;
            }
            return [
                'class'    => $frame['class'] ?? basename( $frame['file'] ?? 'unknown' ),
                'function' => $frame['function'] ?? 'unknown',
            ];
        }
        return [ 'class' => 'unknown', 'function' => 'unknown' ];
    }
}