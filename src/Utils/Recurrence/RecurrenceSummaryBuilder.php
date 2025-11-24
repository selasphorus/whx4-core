<?php

namespace atc\WXC\Utils\Recurrence;

use RRule\RRule;

class RecurrenceSummaryBuilder
{
    protected RRule $rrule;

    public function __construct( string $ruleString )
    {
        $this->rrule = new RRule( $ruleString );
    }

    public function getText(): string
    {
        $rule = $this->rrule->getRule();
        $parts = [];

        // Frequency + interval
        $interval = isset( $rule['INTERVAL'] ) && $rule['INTERVAL'] > 1
            ? "every {$rule['INTERVAL']} "
            : 'every ';

        $freqMap = [
            'DAILY'   => 'day',
            'WEEKLY'  => 'week',
            'MONTHLY' => 'month',
            'YEARLY'  => 'year',
        ];

        $unit = $freqMap[ strtoupper( $rule['FREQ'] ?? '' ) ] ?? 'time';
        $parts[] = ucfirst( $interval . $unit );

        // BYDAY
        if ( isset( $rule['BYDAY'] ) ) {
            $days = explode( ',', $rule['BYDAY'] );
            $dayNames = array_map( [self::class, 'expandDay'], $days );
            $parts[] = 'on ' . implode( ', ', $dayNames );
        }

        // BYMONTHDAY
        if ( isset( $rule['BYMONTHDAY'] ) ) {
            $days = explode( ',', $rule['BYMONTHDAY'] );
            $parts[] = 'on day ' . implode( ', ', $days );
        }

        // BYMONTH
        if ( isset( $rule['BYMONTH'] ) ) {
            $months = explode( ',', $rule['BYMONTH'] );
            $monthNames = array_map( 'intval', $months );
            $parts[] = 'in ' . self::expandMonths( $monthNames );
        }

        // COUNT
        if ( isset( $rule['COUNT'] ) ) {
            $parts[] = 'for ' . $rule['COUNT'] . ' times';
        }

        // UNTIL
        if ( isset( $rule['UNTIL'] ) ) {
            $until = $this->rrule->getUntil()->format( 'Y-m-d' );
            $parts[] = 'until ' . $until;
        }

        return implode( ' ', $parts );
    }

    protected static function expandDay( string $abbr ): string
    {
        $map = [
            'MO' => 'Monday',
            'TU' => 'Tuesday',
            'WE' => 'Wednesday',
            'TH' => 'Thursday',
            'FR' => 'Friday',
            'SA' => 'Saturday',
            'SU' => 'Sunday',
        ];

        return $map[ strtoupper( preg_replace( '/[^A-Z]/', '', $abbr ) ) ] ?? $abbr;
    }

    protected static function expandMonths( array $ints ): string
    {
        $names = [
            '', 'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December',
        ];

        $valid = array_filter( $ints, fn( $i ) => $i >= 1 && $i <= 12 );
        $labels = array_map( fn( $i ) => $names[$i], $valid );

        if ( count( $labels ) === 1 ) {
            return $labels[0];
        }

        return implode( ', ', array_slice( $labels, 0, -1 ) ) . ' and ' . end( $labels );
    }
}
