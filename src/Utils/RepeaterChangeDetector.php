<?php

namespace atc\WXC\Utils;

class RepeaterChangeDetector
{
    /**
     * Detect removed repeater rows based on a subfield.
     *
     * @param int $post_id
     * @param string $repeater_field_name The meta key (e.g. 'wxc_events_excluded_dates')
     * @param string $subfield_name The subfield name (e.g. 'wxc_events_exdate_date')
     * @return string[] List of removed values
     */
    public static function detectRemovedValues( int $post_id, string $repeater_field_name, string $subfield_name ): array
    {
        error_log( '=== RepeaterChangeDetector::detectRemovedValues ===' );
        //error_log( 'post_id: '. $post_id . '; repeater_field_name: '.$repeater_field_name . '; subfield_name: '.$subfield_name );

        $old_rows = get_field( $repeater_field_name, $post_id ) ?: [];
        $old_values = [];

        foreach ( $old_rows as $row ) {
            if ( isset( $row[ $subfield_name ] ) ) {
                $old_values[] = $row[ $subfield_name ];
            }
        }
        error_log( 'old_values: ' . print_r($old_values,true) );

        // Get field keys
        $repeater_field = get_field_object( $repeater_field_name, $post_id );
        if ( ! $repeater_field || empty( $repeater_field['key'] ) || empty( $repeater_field['sub_fields'] ) ) {
            return [];
        }

        $repeater_key = $repeater_field['key'];
        $subfield_key = null;
        $subfield_type = null;

        // Find subfield key + type
        foreach ( $repeater_field['sub_fields'] as $subfield ) {
            if ( $subfield['name'] === $subfield_name ) {
                $subfield_key = $subfield['key'];
                $subfield_type = $subfield['type'];
                break;
            }
        }

        if ( ! $subfield_key ) {
            return [];
        }

        $submitted_rows = $_POST['acf'][ $repeater_key ] ?? [];
        $new_values = [];

        foreach ( $submitted_rows as $row ) {
            if ( ! is_array( $row ) || ! isset( $row[ $subfield_key ] ) ) {
                continue;
            }

            $value = $row[ $subfield_key ];

            if ( $subfield_type === 'date_picker' ) {
                $dt = \DateTime::createFromFormat( 'Ymd', $value );
                if ( $dt ) {
                    $value = $dt->format( 'Y-m-d' );
                }
            }

            $new_values[] = $value;
        }
        error_log( 'new_values: ' . print_r($new_values,true) );

        return array_diff( $old_values, $new_values );
    }

}
