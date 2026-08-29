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

	// The live preview renders real front-end markup, so it needs the real
	// front-end stylesheet.
	summarizewithai_register_assets();
	wp_enqueue_style( 'summarize-with-ai' );
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
 * Build the prompt exactly as a visitor would see it, for the settings preview.
 *
 * There is no post in scope on an admin screen, so the newest published post is
 * borrowed and the previous global restored afterwards.
 *
 * @since 1.1.0
 *
 * @param string $template Optional. Prompt template. Defaults to the saved prompt.
 * @return array{prompt:string,post:WP_Post|null} Resolved prompt and the post it used.
 */
function summarizewithai_get_prompt_preview( $template = '' ) {
	$posts = get_posts(
		array(
			'numberposts'      => 1,
			'post_status'      => 'publish',
			'suppress_filters' => false,
		)
	);

	if ( empty( $posts ) ) {
		return array(
			'prompt' => summarizewithai_build_prompt( $template ),
			'post'   => null,
		);
	}

	$previous        = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
	$GLOBALS['post'] = $posts[0]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
	$prompt          = summarizewithai_build_prompt( $template );
	$GLOBALS['post'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring.

	return array(
		'prompt' => $prompt,
		'post'   => $posts[0],
	);
}

/**
 * Add WordPress's own contextual help to the settings screen.
 *
 * This is where a walkthrough belongs: reachable from the Help button on every
 * tab, without pushing the settings themselves further down the page.
 *
 * @since 1.1.0
 *
 * @return void
 */
function summarizewithai_add_help_tabs() {
	$screen = get_current_screen();

	if ( ! $screen || 'settings_page_summarize-with-ai' !== $screen->id ) {
		return;
	}

	$screen->add_help_tab(
		array(
			'id'      => 'summarizewithai-start',
			'title'   => __( 'Getting started', 'summarize-with-ai' ),
			'content' =>
				'<p>' . esc_html__( 'Three steps and you are done:', 'summarize-with-ai' ) . '</p>'
				. '<ol>'
				. '<li>' . esc_html__( 'On the AI Services tab, tick the assistants your readers actually use. All five are on by default.', 'summarize-with-ai' ) . '</li>'
				. '<li>' . esc_html__( 'On the Placement tab, choose whether the buttons appear before or after your content, and pick the post types. That is the only step most sites need.', 'summarize-with-ai' ) . '</li>'
				. '<li>' . esc_html__( 'Open one of those posts on the front end and click a button to see exactly what your readers get.', 'summarize-with-ai' ) . '</li>'
				. '</ol>'
				. '<p>' . esc_html__( 'Prefer to place the buttons yourself? Leave automatic placement off and use the block or the shortcode instead. Both are on the Usage tab.', 'summarize-with-ai' ) . '</p>',
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'summarizewithai-prompt',
			'title'   => __( 'Writing a good prompt', 'summarize-with-ai' ),
			'content' =>
				'<p>' . esc_html__( 'The prompt is the message your reader sends to the AI. It travels inside the link, so the AI receives it the moment the page opens.', 'summarize-with-ai' ) . '</p>'
				. '<ul>'
				. '<li>' . esc_html__( 'Always include {url}. Without it the AI has no idea which page to read.', 'summarize-with-ai' ) . '</li>'
				. '<li>' . esc_html__( 'Say what a good answer looks like: a word count, whether to use headings, whether to mention images.', 'summarize-with-ai' ) . '</li>'
				. '<li>' . esc_html__( 'Keep it honest. Anyone can read the prompt in the link, so a prompt that quietly tells the AI to promote you is visible to the people you would be hiding it from.', 'summarize-with-ai' ) . '</li>'
				. '<li>' . sprintf(
					/* translators: %d: maximum number of characters in a generated link. */
					esc_html__( 'Keep it short. The whole link is trimmed at %d characters, so a very long prompt loses its ending.', 'summarize-with-ai' ),
					(int) SWI_MAX_URL_LENGTH
				) . '</li>'
				. '</ul>'
				. '<p>' . esc_html__( 'The preview under the prompt field shows exactly what gets sent, resolved against your most recent post.', 'summarize-with-ai' ) . '</p>',
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'summarizewithai-google',
			'title'   => __( 'Preferred sources', 'summarize-with-ai' ),
			'content' =>
				'<p>' . esc_html__( 'The Google button is not a summarize button. It asks a reader to mark your site as a preferred source in Google Search, which changes what that reader sees in Top Stories, and in AI Overviews and AI Mode where those exist.', 'summarize-with-ai' ) . '</p>'
				. '<p>' . esc_html__( 'It does not change your rankings for everyone and it does not guarantee placement. Your site also has to appear in the Google source preferences tool already, and only domains and subdomains are eligible, never subdirectories.', 'summarize-with-ai' ) . '</p>'
				. '<p>' . esc_html__( 'You can render the plugin link, which contacts nobody, or the official Google button, which loads a script from news.google.com on pages where it appears.', 'summarize-with-ai' ) . '</p>',
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'summarizewithai-trouble',
			'title'   => __( 'Buttons not showing?', 'summarize-with-ai' ),
			'content' =>
				'<ul>'
				. '<li>' . esc_html__( 'Check that at least one service is ticked on the AI Services tab.', 'summarize-with-ai' ) . '</li>'
				. '<li>' . esc_html__( 'Check the post type is ticked on the Placement tab. Automatic placement only runs on single posts, never on archives or the home page.', 'summarize-with-ai' ) . '</li>'
				. '<li>' . esc_html__( 'Check the post ID is not in the exclusion list.', 'summarize-with-ai' ) . '</li>'
				. '<li>' . esc_html__( 'Automatic placement is skipped on any post that already contains the shortcode or the block, so you never get two sets on one page.', 'summarize-with-ai' ) . '</li>'
				. '<li>' . esc_html__( 'Clear your caching plugin. The buttons are part of the cached HTML.', 'summarize-with-ai' ) . '</li>'
				. '</ul>',
		)
	);

	$screen->set_help_sidebar(
		'<p><strong>' . esc_html__( 'More information', 'summarize-with-ai' ) . '</strong></p>'
		. '<p><a href="https://github.com/walterpinem/summarize-with-ai-wp" target="_blank" rel="noopener noreferrer">'
		. esc_html__( 'Documentation on GitHub', 'summarize-with-ai' ) . '</a></p>'
		. '<p><a href="https://developers.google.com/search/docs/appearance/preferred-sources" target="_blank" rel="noopener noreferrer">'
		. esc_html__( 'Google preferred sources docs', 'summarize-with-ai' ) . '</a></p>'
	);
}
add_action( 'current_screen', 'summarizewithai_add_help_tabs' );

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
