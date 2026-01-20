<?php

namespace atc\WXC;

final class WXC_Environment {
    // Certain operations should only be run in devmode
	function devmode( $arr_qvar_vals = [] ) {
	
		// TODO: enforce that user must be logged in as admin OR have proper cookie value saved in order to activate devmode(?)
		/*if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			$username = $current_user->user_login;
		} else {
			$username = null;
		}*/
	
		$qvar_val = get_query_var('dev');
	
		if ( !empty($qvar_val) && in_array($qvar_val, $arr_qvar_vals) ) { // && !empty($arr_qvar_vals)
			return true;
		} else if ( $qvar_val && in_array($qvar_val, array("true","yes") ) ) { //if ( empty($arr_qvar_vals) ) { $arr_qvar_vals = array("true","yes"); }
			return true;
		} else if ( !empty($qvar_val) ) {
			return $qvar_val;
		}
	
		return false;
	}

    /*public static function devmode_active() {
        return isset($_GET['devmode']) && $_GET['devmode'] === 'true';
    }*/
    
    // WIP -- phase this out? Check by some other option or move option to wxc settings?
	function devsite() 
	{
		//$options = get_option( 'wxc_settings' ); // TODO: update to add this setting -- see SDG
		$options = get_option( 'sdg_settings' );
	
		if ( isset($options['is_dev_site']) ) {
			if ( !empty($options['is_dev_site']) ) {
				return true;
			} else {
				return false;
			}
		}
	
		if ( isset($_SERVER['HTTP_HOST']) ) {
			$subdomain = explode('.', $_SERVER['HTTP_HOST'])[0];
			if ( $subdomain == "dev" ) { return true; } // RS dev site
		}
	
		return false;
	}
}