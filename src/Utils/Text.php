<?php

declare(strict_types=1);

namespace atc\WXC\Utils;

/**
 * Generic string utilities for WXC.
 */
final class Text
{
    // Normalize a slug-like string by trimming whitespace and converting to lowercase
    public static function slugify(string $value): string
    {
        return strtolower(trim($value));
    }

    // Translate string to studly caps to match class naming conventions
    // e.g. "habitat" -> "Habitat", "event_tag" -> "EventTag", "event-tag" -> "EventTag"
    public static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    public static function camel(string $value): string
    {
        return lcfirst(self::studly($value));
    }

    public static function kebab(string $value): string
    {
        return strtolower(preg_replace('/[A-Z]/', '-$0', lcfirst($value)));
    }

    /**
     * Convert a string to lower_snake_case.
     *
     * - Inserts separators at camelCase / StudlyCaps boundaries (incl. acronyms).
     * - Replaces any non-alphanumeric runs with the separator.
     * - Collapses repeated separators and trims from both ends.
     * - Lowercases (mb_strtolower if available).
     */
    public static function snake(string $value, string $separator = '_'): string
    {
        //return strtolower(str_replace(' ', '_', trim($value))); // original inadequate version ffr

        $v = trim($value);
        if ($v === '') {
            return '';
        }

        // Split StudlyCaps/acronyms and camelCase boundaries.
        $v = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1' . $separator . '$2', $v);
        $v = preg_replace('/([a-z\d])([A-Z])/', '$1' . $separator . '$2', $v);

        // Replace non-alphanumeric runs with the separator.
        $v = preg_replace('/[^A-Za-z0-9]+/u', $separator, $v);

        // Collapse repeated separators.
        $sep = preg_quote($separator, '/');
        $v = preg_replace('/' . $sep . '+/', $separator, $v);

        // Trim separators from both ends.
        $v = trim($v, $separator);

        // Lowercase.
        if (function_exists('mb_strtolower')) {
            $v = mb_strtolower($v, 'UTF-8');
        } else {
            $v = strtolower($v);
        }

        return $v;
    }

    // Hyperlinks -- TODO: move this to separate class? maybe...

    // Make hyperlink
    function makeLink( $url, $text, $title = null, $class = null, $target = null) {

        // TODO: sanitize URL?
        $link = '<a href="'.$url.'"';
        if ( $text && empty($title) ) { $title = $text; } // Use text as title if title is empty
        if ( $title ) { $link .= ' title="'.$title.'"'; }
        if ( $target ) { $link .= ' target="'.$target.'"'; }
        if ( $class ) { $link .= ' class="'.$class.'"'; }
        $link .= '>'.$text.'</a>';
        //return '<a href="'.$url.'">'.$linktext.'</a>';

        return $link;
    }
    
    // WIP
    // digit_to_word >> used currently only by Display Content plugin...
	function digitToWord ( $number ) 
	{
		switch($number){
			case 0:$word = "zero";break;
			case 1:$word = "one";break;
			case 2:$word = "two";break;
			case 3:$word = "three";break;
			case 4:$word = "four";break;
			case 5:$word = "five";break;
			case 6:$word = "six";break;
			case 7:$word = "seven";break;
			case 8:$word = "eight";break;
			case 9:$word = "nine";break;
		}
		return $word;
	}
	
	function word_to_digit ( $word ) 
	{
		$words_to_digits = [
			'zero' => 0,
			'one' => 1,
			'first' => 1,
			'two' => 2,
			'second' => 2,
			'three' => 3,
			'third' => 3,
			'four' => 4,
			'fourth' => 4,
			'five' => 5,
			'fifth' => 5,
			'six' => 6,
			'sixth' => 6,
			'seven' => 7,
			'seventh' => 7,
			'eight' => 8,
			'eighth' => 8,
			'nine' => 9,
			'ninth' => 9,
			'ten' => 10,
			'tenth' => 10,
		];
	
		return isset($words_to_digits[$word]) ? $words_to_digits[$word] : null;
	}
	
	function containsNumbers ( $string )
	{
		if ( preg_match('/first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|one|two|three|four|five|six|seven|eight|nine|ten|[0-9]+/', $string) ) {
			return true;
		}
		return false;
	}
	
	function extractNumbers ( $string ) 
	{
	
		$numbers = array();
		preg_match_all('/first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|one|two|three|four|five|six|seven|eight|nine|ten|[0-9]+/', $string, $matches); //PREG_OFFSET_CAPTURE
	
		// Make sure the numbers are digits -- convert them as needed
		foreach ( $matches as $match ) {
			$word = $match[0];
			$num = sdg_word_to_digit ( $word );
			if ( $num ) { $numbers[]= $num; } else { $numbers[]= $word; }
		}
		return $numbers;
	}
	
}
