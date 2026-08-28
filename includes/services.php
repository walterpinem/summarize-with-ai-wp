<?php
/**
 * AI service registry.
 *
 * A single source of truth for every AI destination the plugin can link to.
 * Third parties can register their own services through the
 * `summarizewithai_services` filter.
 *
 * @package SUMMARIZEAI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get every registered AI service, keyed by service ID.
 *
 * Only services with a prefill parameter confirmed against the live site belong
 * here. A button that opens an empty chat is worse than no button, so two
 * popular assistants are deliberately absent: Gemini's web app at
 * gemini.google.com ignores both `?q=` and `?prompt=` (Google AI Mode, which is
 * bundled, is a different surface and does prefill), and Microsoft disabled
 * Copilot's `?q=` to harden it against prompt injection. The copy-prompt button
 * covers those tools instead.
 *
 * Each service is an array of:
 *  - label       (string) Brand name, shown on the button. Not translated.
 *  - url         (string) Deep-link base URL, the prompt is appended to it.
 *  - icon        (string) SVG file name inside assets/img/.
 *  - color       (string) Button background colour.
 *  - hover       (string) Button hover background colour.
 *  - is_default  (bool)   Whether the service is enabled on a fresh install.
 *
 * @since 1.1.0
 *
 * @return array<string, array<string, mixed>> Registered services.
 */
function summarizewithai_get_services() {
	$services = array(
		'chatgpt'    => array(
			'label'      => 'ChatGPT',
			'url'        => 'https://chatgpt.com/?q=',
			'icon'       => 'chatgpt-icon.svg',
			'color'      => '#000000',
			'hover'      => '#10a37f',
			'is_default' => true,
		),
		'grok'       => array(
			'label'      => 'Grok',
			'url'        => 'https://grok.com/?q=',
			'icon'       => 'grok-icon.svg',
			'color'      => '#000000',
			'hover'      => '#2f2f2f',
			'is_default' => true,
		),
		'perplexity' => array(
			'label'      => 'Perplexity',
			'url'        => 'https://www.perplexity.ai/search/new?q=',
			'icon'       => 'perplexity-icon.svg',
			'color'      => '#20808d',
			'hover'      => '#186670',
			'is_default' => true,
		),
		'claude'     => array(
			'label'      => 'Claude',
			'url'        => 'https://claude.ai/new?q=',
			'icon'       => 'claude-icon.svg',
			'color'      => '#d77655',
			'hover'      => '#c2603f',
			'is_default' => true,
		),
		'googleai'   => array(
			// Google AI Mode is a Search surface, so the ordinary `q=` carries
			// the prompt and `udm=50` selects AI Mode.
			'label'      => 'Google AI Mode',
			'url'        => 'https://www.google.com/search?udm=50&q=',
			'icon'       => 'google-ai-icon.svg',
			'color'      => '#1a73e8',
			'hover'      => '#1558b7',
			'is_default' => true,
		),
	);

	/**
	 * Filter the registered AI services.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, array<string, mixed>> $services Registered services.
	 */
	$services = (array) apply_filters( 'summarizewithai_services', $services );

	// Guarantee every entry has the keys the renderer relies on.
	foreach ( $services as $id => $service ) {
		$services[ $id ] = wp_parse_args(
			(array) $service,
			array(
				'label'      => ucfirst( (string) $id ),
				'url'        => '',
				'icon'       => '',
				'color'      => '#1e1e1e',
				'hover'      => '#3c3c3c',
				'is_default' => false,
			)
		);
	}

	return $services;
}

/**
 * Get the IDs of the services enabled on a fresh install.
 *
 * @since 1.1.0
 *
 * @return string[] Service IDs.
 */
function summarizewithai_get_default_service_ids() {
	$defaults = array();

	foreach ( summarizewithai_get_services() as $id => $service ) {
		if ( ! empty( $service['is_default'] ) ) {
			$defaults[] = $id;
		}
	}

	return $defaults;
}

/**
 * Get a single service definition.
 *
 * @since 1.1.0
 *
 * @param string $id Service ID.
 * @return array<string, mixed>|null Service definition, or null when unknown.
 */
function summarizewithai_get_service( $id ) {
	$services = summarizewithai_get_services();

	return isset( $services[ $id ] ) ? $services[ $id ] : null;
}
