<?php
/**
 * Summarize with AI
 *
 * @package       SUMMARIZEAI
 * @author        Walter Pinem
 * @version       1.1.0
 *
 * @wordpress-plugin
 * Plugin Name:       Summarize with AI
 * Plugin URI:        https://walterpinem.com/summarize-with-ai-wordpress-plugin/
 * Description:       Adds "Summarize with AI" buttons to your content, plus an "Add as a preferred source on Google" button. Supports ChatGPT, Claude, Grok, Perplexity and Google AI Mode, with shortcodes, blocks and automatic placement.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Walter Pinem
 * Author URI:        https://walterpinem.com/
 * Text Domain:       summarize-with-ai
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin name.
 */
define( 'SWI_NAME', 'Summarize with AI' );

/**
 * Plugin version.
 */
define( 'SWI_VERSION', '1.1.0' );

/**
 * Plugin root file.
 */
define( 'SWI_FILE', __FILE__ );

/**
 * Plugin basename.
 */
define( 'SWI_BASE', plugin_basename( SWI_FILE ) );

/**
 * Plugin folder path.
 */
define( 'SWI_DIR', plugin_dir_path( SWI_FILE ) );

/**
 * Plugin folder URL.
 */
define( 'SWI_URL', plugin_dir_url( SWI_FILE ) );

require_once SWI_DIR . 'includes/services.php';
require_once SWI_DIR . 'includes/options.php';
require_once SWI_DIR . 'includes/frontend.php';
require_once SWI_DIR . 'includes/google-source.php';

if ( is_admin() ) {
	require_once SWI_DIR . 'includes/admin.php';
}

/**
 * Load the plugin translations.
 *
 * @since 1.0.0
 *
 * @return void
 */
function summarizewithai_load_textdomain() {
	load_plugin_textdomain( 'summarize-with-ai', false, dirname( SWI_BASE ) . '/languages' );
}
add_action( 'init', 'summarizewithai_load_textdomain', 1 );

/**
 * Seed the default options on activation.
 *
 * Existing settings are preserved, so reactivating never overwrites a
 * configured site.
 *
 * @since 1.1.0
 *
 * @return void
 */
function summarizewithai_activate() {
	$existing = get_option( 'summarizewithai_options' );

	if ( ! is_array( $existing ) || empty( $existing ) ) {
		add_option( 'summarizewithai_options', summarizewithai_get_default_options() );
	}

	update_option( 'summarizewithai_version', SWI_VERSION );
}
register_activation_hook( SWI_FILE, 'summarizewithai_activate' );
