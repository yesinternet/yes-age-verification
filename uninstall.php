<?php
/**
 * Runs when the plugin is deleted from the WordPress admin.
 * Removes all stored plugin data from the database.
 *
 * @package YES_Age_Verification
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'yes_age_verification_options' );
