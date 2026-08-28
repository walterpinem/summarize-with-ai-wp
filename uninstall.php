<?php
/**
 * Removes everything the plugin stored when it is deleted from WordPress.
 *
 * @package SUMMARIZEAI
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Delete the plugin options for the current site.
 *
 * @return void
 */
function summarizewithai_delete_site_options() {
	delete_option( 'summarizewithai_options' );
	delete_option( 'summarizewithai_version' );
}

if ( is_multisite() ) {
	$swi_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $swi_site_ids as $swi_site_id ) {
		switch_to_blog( (int) $swi_site_id );
		summarizewithai_delete_site_options();
		restore_current_blog();
	}
} else {
	summarizewithai_delete_site_options();
}
