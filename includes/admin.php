<?php
/**
 * Admin bootstrap: menu, setting registration, assets and upgrade routine.
 *
 * @package SUMMARIZEAI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add the settings page under the Settings menu.
 *
 * @since 1.0.0
 *
 * @return void
 */
function summarizewithai_admin_menu() {
	add_options_page(
		__( 'Summarize with AI Settings', 'summarize-with-ai' ),
		__( 'Summarize with AI', 'summarize-with-ai' ),
		'manage_options',
		'summarize-with-ai',
		'summarizewithai_render_settings_page'
	);
}
add_action( 'admin_menu', 'summarizewithai_admin_menu' );

/**
 * Render the settings page.
 *
 * @since 1.0.0
 *
 * @return void
 */
function summarizewithai_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'summarize-with-ai' ) );
	}

	require SWI_DIR . 'admin/settings.php';
}

/**
 * Register the plugin setting with its sanitisation callback.
 *
 * The form posts to `options.php`, so WordPress handles the nonce, the
 * capability check and the redirect, and this callback is the single place
 * where untrusted input is cleaned.
 *
 * @since 1.0.0
 *
 * @return void
 */
function summarizewithai_register_settings() {
	register_setting(
		'summarizewithai_settings',
		'summarizewithai_options',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'summarizewithai_sanitize_options',
			'default'           => summarizewithai_get_default_options(),
			'show_in_rest'      => false,
		)
	);
}
add_action( 'admin_init', 'summarizewithai_register_settings' );

/**
 * Enqueue the admin stylesheet and tab script on the settings screen only.
 *
 * @since 1.0.0
 *
 * @param string $hook_suffix Current admin page.
 * @return void
 */
function summarizewithai_admin_enqueue_styles( $hook_suffix ) {
	if ( 'settings_page_summarize-with-ai' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style(
		'summarize-with-ai-admin',
		SWI_URL . 'assets/css/admin.css',
		array(),
		SWI_VERSION
	);

	wp_enqueue_script(
		'summarize-with-ai-admin',
		SWI_URL . 'assets/js/admin.js',
		array(),
		SWI_VERSION,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'summarizewithai_admin_enqueue_styles' );

/**
 * Add a Settings link to the plugin row on the Plugins screen.
 *
 * @since 1.1.0
 *
 * @param string[] $links Existing action links.
 * @return string[] Filtered action links.
 */
function summarizewithai_plugin_action_links( $links ) {
	$settings_link = sprintf(
		'<a href="%1$s">%2$s</a>',
		esc_url( admin_url( 'options-general.php?page=summarize-with-ai' ) ),
		esc_html__( 'Settings', 'summarize-with-ai' )
	);

	array_unshift( $links, $settings_link );

	return $links;
}
add_filter( 'plugin_action_links_' . SWI_BASE, 'summarizewithai_plugin_action_links' );

/**
 * Add a documentation link to the plugin row meta.
 *
 * @since 1.1.0
 *
 * @param string[] $links Existing row meta links.
 * @param string   $file  Plugin file the row belongs to.
 * @return string[] Filtered row meta links.
 */
function summarizewithai_plugin_row_meta( $links, $file ) {
	if ( SWI_BASE !== $file ) {
		return $links;
	}

	$links[] = sprintf(
		'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
		esc_url( 'https://github.com/walterpinem/summarize-with-ai-wp' ),
		esc_html__( 'Documentation', 'summarize-with-ai' )
	);

	return $links;
}
add_filter( 'plugin_row_meta', 'summarizewithai_plugin_row_meta', 10, 2 );

/**
 * Warn on the settings screen when every AI service has been switched off.
 *
 * @since 1.1.0
 *
 * @return void
 */
function summarizewithai_admin_notices() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen || 'settings_page_summarize-with-ai' !== $screen->id ) {
		return;
	}

	if ( summarizewithai_get_enabled_service_ids() || summarizewithai_get_option( 'enable_copy' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'No AI services are enabled, so the buttons will not appear anywhere. Enable at least one on the AI Services tab.', 'summarize-with-ai' )
	);
}
add_action( 'admin_notices', 'summarizewithai_admin_notices' );

/**
 * Run one-time upgrade routines when the stored version is behind the code.
 *
 * @since 1.1.0
 *
 * @return void
 */
function summarizewithai_maybe_upgrade() {
	$installed = get_option( 'summarizewithai_version' );

	if ( SWI_VERSION === $installed ) {
		return;
	}

	$options = get_option( 'summarizewithai_options', array() );

	if ( is_array( $options ) && ! empty( $options ) ) {
		// ChatGPT moved off chat.openai.com; rewrite the value only when it is
		// still the untouched legacy default so custom URLs are left alone.
		if ( isset( $options['chatgpt_url'] ) && 'https://chat.openai.com/?q=' === $options['chatgpt_url'] ) {
			$options['chatgpt_url'] = 'https://chatgpt.com/?q=';
		}

		/*
		 * 1.0.0 had no service toggles and always rendered exactly these four.
		 * Pin that list rather than reading the current defaults, so adding a
		 * service later never switches one on behind an existing site's back.
		 */
		if ( ! isset( $options['enabled_services'] ) ) {
			$options['enabled_services'] = array( 'chatgpt', 'grok', 'perplexity', 'claude' );
		}

		/*
		 * register_setting() hooks the sanitisation callback into
		 * sanitize_option_summarizewithai_options, so update_option() runs it
		 * too. That callback expects a complete form submission and reads a
		 * missing checkbox as "off", so fill in the defaults for keys an older
		 * version never stored before writing.
		 */
		$options = wp_parse_args( $options, summarizewithai_get_default_options() );

		update_option( 'summarizewithai_options', $options );
	}

	update_option( 'summarizewithai_version', SWI_VERSION );
}
add_action( 'admin_init', 'summarizewithai_maybe_upgrade' );
