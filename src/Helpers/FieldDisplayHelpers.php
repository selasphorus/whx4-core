<?php

namespace atc\WXC\Helpers;

class FieldDisplayHelpers
{
    /**
     * Format serialized array data for display in ACF textareas.
     *
     * @param mixed $value The raw field value.
     * @param string $separator The separator to use (default: newline).
     * @return string
     */
    public static function formatArrayForDisplay( $value, string $separator = "\n" ): string
    {
        if ( is_array( $value ) ) {
            return implode( $separator, $value );
        }

        if ( is_string( $value ) && is_serialized( $value ) ) {
            $decoded = maybe_unserialize( $value );
            if ( is_array( $decoded ) ) {
                return implode( $separator, $decoded );
            }
        }

        return (string) $value;
    }
}
