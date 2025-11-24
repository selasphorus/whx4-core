<?php

namespace atc\WXC\Utils;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use atc\WXC\App;
use atc\WXC\Query\ScopedDateResolver;


class DateHelper
{
    /**
     * Normalize date input to standardized Y-m-d values or DateTimeImmutable objects.
     *
     * Delegates all scope logic to ScopedDateResolver.
     *
     * Args:
     * - scope: string|null -- Optional keyword like 'this_month' or 'Easter 2025'
     * - date: string|DateTimeInterface|null (single date or "start,end") -- (?) A date string, range, or DateTime object
     * - year: int|null (used when month is present) -- ??? WIP -- Optional year fallback
     * - month: int|string|null (1-12 or month name/abbr — will be normalized) -- Optional month fallback
     * - asDateObjects: bool (default false) -- If true, returns DateTimeImmutable objects
     *
     * Returns:
     * - string "Y-m-d"
     * - DateTimeImmutable
     * - array{startDate: string, endDate: string} or array{startDate: DateTimeImmutable, endDate: DateTimeImmutable}
     * i.e. @return array|DateTimeImmutable|string     Array with 'startDate' and 'endDate' or single string if same
     */
    public static function normalizeDateInput( array $args = [] ): array|DateTimeImmutable|string
    {
        $defaults = [
            'scope' => null,
            'date' => null,
            'year' => null,
            'month' => null,
            'asDateObjects' => false,
        ];
        $args = function_exists('wp_parse_args') ? wp_parse_args($args, $defaults) : array_merge($defaults, $args);

        $scope = $args['scope'];
        $date = $args['date'];
        $year = $args['year'];
        $month = $args['month'];
        $asObjects = (bool)$args['asDateObjects'];

        // 1) Scope wins — centralize in ScopedDateResolver
        if (is_string($scope) && $scope !== '') {
            $resolved = ScopedDateResolver::resolve($scope, [
                'year' => $year,
                'month' => is_string($month) ? self::normalizeMonthToInt($month) : $month,
            ]);
            //
            $start = $resolved['start'];
            $end = $resolved['end'];
        }

        // 2) Explicit date(s)
        if ( $date instanceof DateTimeInterface ) {
            $d = DateTimeImmutable::createFromInterface($date);
            return $asObjects ? $d : $d->format('Y-m-d');
        }
        // date string, representing either a single date or date range
        if (is_string($date) && $date !== '') {
            if (strpos($date, ',') !== false) { // date range, comma-separated
                [$rawStart, $rawEnd] = array_map('trim', explode(',', $date, 2));
                $start = self::parseFlexibleDate($rawStart, true);
                $end = self::parseFlexibleDate($rawEnd, true);
            } else {
                return self::parseFlexibleDate($date, $asObjects);
            }
        }

        // 3) Month/year helper (no scope, no explicit date)
        if ($month !== null) {
            //$month = str_pad( (string)(int) $month, 2, '0', STR_PAD_LEFT );
            $m = is_string($month) ? self::normalizeMonthToInt($month) : (int)$month;
            if ($m >= 1 && $m <= 12) {
                $y = $year !== null ? (int)$year : (int)(new DateTimeImmutable())->format('Y');
                $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', sprintf('%04d-%02d-01 00:00:00', $y, $m)); // hour/minute/second because: XXX //$start = DateTimeImmutable::createFromFormat( 'Y-m-d', "{$year}-{$month}-01" );
                $end = $start->modify('last day of this month')->setTime(23, 59, 59); //$end = $start->modify( 'last day of this month' );
            }
        }

        if ( $start && $end ) {
            // Both start and end are non-null => return an array
            return $asObjects
                ? [ 'startDate' => $start, 'endDate' => $end ]
                : [ 'startDate' => $start->format( 'Y-m-d' ), 'endDate' => $end->format( 'Y-m-d' ) ];
        } elseif ( $start ) {
            // Only start is set => return single date object or string
            return $asObjects ? $start : $start->format( 'Y-m-d' );
        } elseif ( $end ) {
            // Only end is set => return single date object or string
            return $asObjects ? $end : $end->format( 'Y-m-d' );
        }

        // 4) Failsafe: return today's date
        $today = new DateTimeImmutable();
        return $asObjects ? $today : $today->format('Y-m-d');
        //return $asDateObjects ? $now : $now->format( 'Y-m-d' ); // failsafe
    }

    /**
     * Parse a flexible natural-language date string.
     *
     * @param string $input
     * @param bool $asDateObject
     * @return string|DateTimeImmutable
     */
    public static function parseFlexibleDate( string $input, bool $asDateObject = false ): string|DateTimeImmutable
    {
        try {
            $dt = new DateTimeImmutable( $input );
            return $asDateObject ? $dt : $dt->format( 'Y-m-d' );
        } catch ( Exception $e ) {
            return $asDateObject ? new DateTimeImmutable() : '';
        }
    }

    public static function normalizeMonthToInt( string $month ): ?int
    {
        $month = strtolower( trim( $month ) );

        $map = [
            'jan' => 1, 'january' => 1,
            'feb' => 2, 'february' => 2,
            'mar' => 3, 'march' => 3,
            'apr' => 4, 'april' => 4,
            'may' => 5,
            'jun' => 6, 'june' => 6,
            'jul' => 7, 'july' => 7,
            'aug' => 8, 'august' => 8,
            'sep' => 9, 'sept' => 9, 'september' => 9,
            'oct' => 10, 'october' => 10,
            'nov' => 11, 'november' => 11,
            'dec' => 12, 'december' => 12,
        ];

        return $map[ $month ] ?? null;
    }
    /**
     * Combine a date and optional time string into a DateTimeImmutable object.
     *
     * @param string|null $date A date string (e.g. '2025-06-21')
     * @param string|null $time A time string (e.g. '14:30') — optional
     * @return \DateTimeImmutable|null
     */
    public static function combineDateAndTime( ?string $date, ?string $time = null ): ?DateTimeImmutable
    {
        if (!$date) {
            return null;
        }

        $datetimeString = trim($date . ' ' . ($time ?? ''));

        try {
            return new DateTimeImmutable( $datetimeString );
        } catch ( Exception $e ) {
            return null;
        }
    }
    
    /**
	 * Convert resolved scope bounds (strings like 'YYYY-mm-dd' or 'YYYY-mm-dd HH:ii:ss')
	 * into a year window.
	 *
	 * @param array{start:?string,end:?string} $bounds
	 * @return array{min:int,max:int,years:int[]}
	 */
	public static function yearsWindow(array $bounds): array
	{
		$startY = self::extractYear($bounds['start'] ?? null);
		$endY   = self::extractYear($bounds['end'] ?? null);
	
		if ($startY === null && $endY === null) {
			return ['min' => 0, 'max' => 0, 'years' => []];
		}
	
		// If one side is missing, use the other.
		if ($startY === null) {
			$startY = $endY;
		}
		if ($endY === null) {
			$endY = $startY;
		}
	
		$min = (int)min($startY, $endY);
		$max = (int)max($startY, $endY);
	
		return [
			'min'   => $min,
			'max'   => $max,
			'years' => $min <= $max ? range($min, $max) : [],
		];
	}
	
	/**
	 * Extract a 4-digit year from a date/datetime string (returns null if none).
	 */
	private static function extractYear(?string $value): ?int
	{
		if (!is_string($value) || $value === '') {
			return null;
		}
		if (preg_match('/^\s*(\d{4})\b/', $value, $m) === 1) {
			return (int)$m[1];
		}
		return null;
	}
    
    /**
     * Get month names
     * 
     * @param string $format 'short' (Jan, Feb) or 'long' (January, February)
     * @return array Associative array with month numbers as keys ('01' => 'Jan', etc.)
     */
    public static function getMonthNames(string $format = 'short'): array
    {
        if ($format === 'long') {
            return [
                '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
                '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
                '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
            ];
        }
        
        return [
            '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
            '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Aug',
            '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dec'
        ];
    }

	/**
	 * Generate array of month periods (YYYY-MM) between start and end dates
	 * 
	 * @param string|DateTimeInterface|null $start Start date
	 * @param string|DateTimeInterface|null $end End date
	 * @return array Array of period strings in YYYY-MM format
	 */
	public static function generateMonthPeriods($start, $end): array
	{
		if (!$start || !$end) {
			return [];
		}
		
		$startDate = $start instanceof \DateTimeInterface 
			? \DateTimeImmutable::createFromInterface($start)
			: new \DateTimeImmutable($start);
		
		$endDate = $end instanceof \DateTimeInterface
			? \DateTimeImmutable::createFromInterface($end)
			: new \DateTimeImmutable($end);
		
		$periods = [];
		$current = $startDate->modify('first day of this month');
		$end = $endDate->modify('first day of this month');
		
		while ($current <= $end) {
			$periods[] = $current->format('Y-m');
			$current = $current->modify('+1 month');
		}
		
		return $periods;
	}
	
	/**
	 * Generate array of year periods between start and end dates
	 * 
	 * @param string|DateTimeInterface|null $start Start date
	 * @param string|DateTimeInterface|null $end End date
	 * @return array Array of year strings
	 */
	public static function generateYearPeriods($start, $end): array
	{
		if (!$start || !$end) {
			return [];
		}
		
		$startDate = $start instanceof \DateTimeInterface 
			? \DateTimeImmutable::createFromInterface($start)
			: new \DateTimeImmutable($start);
		
		$endDate = $end instanceof \DateTimeInterface
			? \DateTimeImmutable::createFromInterface($end)
			: new \DateTimeImmutable($end);
		
		$startYear = (int)$startDate->format('Y');
		$endYear = (int)$endDate->format('Y');
		
		return range($startYear, $endYear);
	}

	/// Really necessary? TBD -- feels redundant
	public static function isDateLike(string $s): bool
	{
	    if ($s === '') { return false; }
	    if (preg_match('/^\d{8}$/', $s)) { return true; }                       // Ymd
	    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) { return true; }           // Y-m-d
	    if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/', $s)) {     // Y-m-d HH:MM:SS
	        return true;
	    }
	    return strtotime($s) !== false;
	}

	public static function isYmd(string $s): bool
	{
		return (bool)preg_match('/^\d{8}$/', $s); // YYYYMMDD
	}
	
	public static function isDate(string $s): bool
	{
		return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s); // YYYY-MM-DD
	}
	
	public static function isDateTime(string $s): bool
	{
		return (bool)preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/', $s); // YYYY-MM-DD HH:MM:SS or T
	}
	
	public static function toYmd(string|\DateTimeInterface $v): string
	{
		if ($v instanceof \DateTimeInterface) {
			return $v->format('Ymd');
		}
		if (self::isYmd($v)) {
			return $v;
		}
		if (self::isDate($v) || self::isDateTime($v)) {
			return str_replace('-', '', substr($v, 0, 10));
		}
		$dt = self::parseFlexibleDate($v, true);
		return $dt->format('Ymd');
	}
	
	public static function toDate(string|\DateTimeInterface $v): string
	{
		if ($v instanceof \DateTimeInterface) {
			return $v->format('Y-m-d');
		}
		if (self::isDate($v)) {
			return $v;
		}
		if (self::isYmd($v)) {
			return substr($v, 0, 4) . '-' . substr($v, 4, 2) . '-' . substr($v, 6, 2);
		}
		if (self::isDateTime($v)) {
			return substr($v, 0, 10);
		}
		$dt = self::parseFlexibleDate($v, true);
		return $dt->format('Y-m-d');
	}
	
	public static function toDateTime(string|\DateTimeInterface $v): string
	{
		if ($v instanceof \DateTimeInterface) {
			return $v->format('Y-m-d H:i:s');
		}
		if (self::isDateTime($v)) {
			return str_replace('T', ' ', $v);
		}
		if (self::isDate($v)) {
			return $v . ' 00:00:00';
		}
		if (self::isYmd($v)) {
			return self::toDate($v) . ' 00:00:00';
		}
		$dt = self::parseFlexibleDate($v, true);
		return $dt->format('Y-m-d H:i:s');
	}
	
	///

    /**
     * Resolve the site timezone with sensible fallbacks.
     *
     * Order of precedence:
     * 1) WXC context (if available) via App::ctx()->getTimezone()
     * 2) WordPress helper wp_timezone()
     * 3) WordPress options: timezone_string, then gmt_offset
     * 4) PHP ini setting, else UTC
     */
    public static function siteTimezone(): DateTimeZone
    {
        // 1) WXC context (soft dependency; ignore if unavailable)
        try {
            if (class_exists(WXC::class)) {
                $ctx = App::ctx();
                if ($ctx && method_exists($ctx, 'getTimezone')) {
                    $tz = $ctx->getTimezone();
                    if ($tz instanceof DateTimeZone) {
                        return $tz;
                    }
                }
            }
        } catch (\Throwable) {
            // noop: fall through to WP/PHP fallbacks
        }

        // 2) WordPress helper (handles timezone_string OR gmt_offset)
        if (function_exists('wp_timezone')) {
            /** @var DateTimeZone $wpTz */
            $wpTz = wp_timezone();
            return $wpTz;
        }

        // 3a) WP option: timezone_string
        if (function_exists('get_option')) {
            $tzString = (string) get_option('timezone_string', '');
            if ($tzString !== '') {
                try {
                    return new DateTimeZone($tzString);
                } catch (Exception) {
                    // fall through
                }
            }

            // 3b) WP option: gmt_offset (best-effort mapping)
            $offset = (float) get_option('gmt_offset', 0.0);
            if ($offset !== 0.0) {
                $name = timezone_name_from_abbr('', (int) round($offset * 3600), 0);
                if ($name !== false) {
                    try {
                        return new DateTimeZone($name);
                    } catch (Exception) {
                        // fall through
                    }
                }
            }
        }

        // 4) PHP ini fallback, then UTC
        $iniTz = (string) (ini_get('date.timezone') ?: '');
        try {
            return new DateTimeZone($iniTz !== '' ? $iniTz : 'UTC');
        } catch (Exception) {
            return new DateTimeZone('UTC');
        }
    }

    /**
     * Calculate Easter Sunday for a given year (kept for convenience; ScopedDateResolver uses its own internal helper).
     *
     * @param int $year
     * @return DateTimeImmutable
     */
    /*private static function calculateEasterDate(int $year, DateTimeZone $tz): DateTimeImmutable
    {
        $ts = function_exists('easter_date') ? easter_date($year) : strtotime('+' . (easter_days($year) ?? 0) . " days", strtotime("$year-03-21"));
        $utc = (new DateTimeImmutable())->setTimestamp($ts);
        return $utc->setTimezone($tz);
    }*/
    public static function calculateEasterDate(int $year, ?DateTimeZone $tz = null): DateTimeImmutable
    {
        $tz = $tz ?? self::siteTimezone();

        // 1) Prefer native easter_date() when available
        try {
            if (function_exists('easter_date')) {
                $ts = easter_date($year);                     // epoch for Easter at 00:00:00 *UTC*
                $ymd = (new DateTimeImmutable('@' . $ts))
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format('Y-m-d');                        // Normalize to a date string (UTC)

                $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, $tz); // local midnight in $tz
                if ($dt instanceof DateTimeImmutable) {
                    return $dt;                               // already 00:00:00 in $tz
                }
            }
        } catch (\Throwable $e) {
            // fall through to algorithm
        }

        // 2) Fallback: Meeus/Jones/Butcher algorithm (Gregorian)
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);         // 3 = March, 4 = April
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        $ymd = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, $tz);
        return $dt instanceof DateTimeImmutable ? $dt : new DateTimeImmutable($ymd, $tz);
    }

}
