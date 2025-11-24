<?php

namespace atc\WXC\ACF;

class RestrictAccess
{  
    public static function register(): void
    {
        add_filter( 'acf/settings/show_admin', [self::class, 'maybeHideAdmin'] );
        add_filter( 'block_editor_settings_all', [self::class, 'maybeRestrictEditorUI'], 10, 2 );
    }

    public static function maybeHideAdmin( bool $show ): bool
    {
        // Only allow admins to see ACF
        return current_user_can( 'manage_options' );
        
        /*
        // If our user can manage site options.
		if ( current_user_can( 'manage_options' ) ) {
		
			$user = wp_get_current_user();
	
			// Make sure we have a WP_User object and email address.
			if ( $user && isset( $user->user_email ) ) {
				
				// Compare current logged in user's email with our allow list.
				//if ( in_array( $email_domain, $allowed_email_domains, true ) ) {
				if ( $user->user_email == "birdhive@gmail.com" || $user->user_email == "alphameric@protonmail.com" ) {
					return true;
				}
			}
		}
		*/
    }

    public static function maybeRestrictEditorUI( array $settings, \WP_Block_Editor_Context $context ): array
    {
        $settings['canLockBlocks'] = current_user_can( 'manage_options' );
        return $settings;
    }
}
