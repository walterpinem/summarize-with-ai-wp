<?php
/**
 * "Add as a preferred source on Google" button.
 *
 * Unlike the AI buttons this one is about the site, not the post: it always
 * points at the same Google preferences page for the site's domain and carries
 * no prompt. It therefore lives outside the AI service registry, but shares the
 * plugin's renderer, assets and settings plumbing.
 *
 * @package SUMMARIZEAI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get the placement choices for the preferred-source button.
 *
 * @since 1.1.0
 *
 * @return array<string, string> Placement value => label.
 */
function summarizewithai_get_google_source_placements() {
	return array(
		'none'         => __( 'Only where I place it (shortcode or block)', 'summarize-with-ai' ),
		'inline_first' => __( 'Among the Summarize with AI buttons, as the first button', 'summarize-with-ai' ),
		'inline_last'  => __( 'Among the Summarize with AI buttons, as the last button', 'summarize-with-ai' ),
		'with_buttons' => __( 'In its own row below the Summarize with AI buttons', 'summarize-with-ai' ),
		'before'       => __( 'Before the content, on its own', 'summarize-with-ai' ),
		'after'        => __( 'After the content, on its own', 'summarize-with-ai' ),
		'both'         => __( 'Before and after the content, on its own', 'summarize-with-ai' ),
	);
}

/**
 * Resolve how the button should attach to the Summarize with AI row.
 *
 * @since 1.1.0
 *
 * @param string $value Requested position: auto, no, yes, row, first or last.
 *                      "auto" follows the saved placement setting.
 * @return string One of 'first', 'last', 'row', or an empty string for none.
 */
function summarizewithai_resolve_google_source_position( $value = 'auto' ) {
	$value = strtolower( trim( (string) $value ) );

	if ( 'auto' === $value || '' === $value ) {
		$placement = (string) summarizewithai_get_option( 'google_source_placement' );

		$map = array(
			'inline_first' => 'first',
			'inline_last'  => 'last',
			'with_buttons' => 'row',
		);

		return isset( $map[ $placement ] ) ? $map[ $placement ] : '';
	}

	if ( in_array( $value, array( 'first', 'last', 'row' ), true ) ) {
		return $value;
	}

	// "yes" is the row position, "no" and anything unrecognised mean none.
	return 'yes' === $value ? 'row' : '';
}

/**
 * Reduce whatever the user typed into a bare domain Google will accept.
 *
 * Accepts `example.com`, `www.example.com`, `https://example.com/blog/` and so
 * on, and always returns the registrable host without protocol, `www.` or path.
 *
 * @since 1.1.0
 *
 * @param string $value Raw domain or URL.
 * @return string Bare domain, or an empty string when nothing usable was given.
 */
function summarizewithai_normalize_domain( $value ) {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return '';
	}

	// wp_parse_url() only finds a host when there is a scheme, so add one.
	if ( ! preg_match( '#^https?://#i', $value ) ) {
		$value = 'https://' . ltrim( $value, '/' );
	}

	$host = summarizewithai_extract_domain( $value );

	if ( '' === $host ) {
		return '';
	}

	// Google needs ASCII, so punycode an internationalised domain where PHP can.
	if ( preg_match( '/[^\x20-\x7f]/', $host ) && function_exists( 'idn_to_ascii' ) ) {
		$ascii = idn_to_ascii( $host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 );

		if ( is_string( $ascii ) && '' !== $ascii ) {
			$host = $ascii;
		}
	}

	/*
	 * parse_url() is happy to call most of a sentence a host, so require the
	 * shape of a real hostname. A stray note in the settings field must not
	 * become a link that sends readers to the wrong site.
	 */
	if ( ! preg_match( '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host ) ) {
		return '';
	}

	return $host;
}

/**
 * Get the domain the preferred-source link points at.
 *
 * @since 1.1.0
 *
 * @return string Bare domain.
 */
function summarizewithai_get_google_source_domain() {
	$configured = summarizewithai_normalize_domain( summarizewithai_get_option( 'google_source_domain' ) );

	if ( '' !== $configured ) {
		return $configured;
	}

	return summarizewithai_extract_domain( home_url() );
}

/**
 * Build the complete preferred-source link.
 *
 * @since 1.1.0
 *
 * @return string Absolute URL, or an empty string when no domain is resolvable.
 */
function summarizewithai_get_google_source_link() {
	$domain = summarizewithai_get_google_source_domain();

	if ( '' === $domain ) {
		return '';
	}

	$base = (string) summarizewithai_get_option( 'google_source_url' );

	if ( '' === trim( $base ) || ! wp_http_validate_url( $base ) ) {
		$defaults = summarizewithai_get_default_options();
		$base     = $defaults['google_source_url'];
	}

	/**
	 * Filter the preferred-source link.
	 *
	 * @since 1.1.0
	 *
	 * @param string $link   Complete URL.
	 * @param string $domain Domain the link was built for.
	 */
	return (string) apply_filters(
		'summarizewithai_google_source_link',
		$base . rawurlencode( $domain ),
		$domain
	);
}

/**
 * Render the "Add as a preferred source on Google" button.
 *
 * @since 1.1.0
 *
 * @param array<string, mixed> $args {
 *     Optional. Rendering arguments.
 *
 *     @type string $label     Button text. Supports the prompt placeholders.
 *     @type string $align     Horizontal alignment: left, center or right.
 *     @type string $class     Extra CSS classes for the wrapper.
 *     @type string $variant   'standalone' for its own block, 'inline' for a cell
 *                             inside the Summarize with AI row.
 *     @type bool   $show_text Whether the button text is visible. Inline only.
 * }
 * @return string HTML markup, or an empty string when nothing should render.
 */
function summarizewithai_render_google_source( $args = array() ) {
	if ( ! summarizewithai_should_display() ) {
		return '';
	}

	$link = summarizewithai_get_google_source_link();

	if ( '' === $link ) {
		return '';
	}

	$args = wp_parse_args(
		$args,
		array(
			'label'     => '',
			'align'     => (string) summarizewithai_get_option( 'google_source_align' ),
			'class'     => '',
			'variant'   => 'standalone',
			'show_text' => true,
		)
	);

	$inline = ( 'inline' === $args['variant'] );

	/*
	 * Sitting among ChatGPT and Claude, "Add {site_name} as a preferred source
	 * on Google" dwarfs its neighbours, so the inline form has its own shorter
	 * label rather than reusing the standalone one.
	 */
	if ( '' === trim( (string) $args['label'] ) ) {
		$args['label'] = (string) summarizewithai_get_option( $inline ? 'google_source_inline_label' : 'google_source_label' );
	}

	$placeholders = summarizewithai_get_placeholders( (string) $args['label'] );
	$label        = str_replace(
		array_keys( $placeholders ),
		array_values( $placeholders ),
		(string) $args['label']
	);
	$label        = trim( preg_replace( '/[ \t]{2,}/', ' ', $label ) );

	if ( '' === $label ) {
		$defaults = summarizewithai_get_default_options();
		$label    = str_replace(
			array_keys( $placeholders ),
			array_values( $placeholders ),
			$defaults[ $inline ? 'google_source_inline_label' : 'google_source_label' ]
		);
	}

	if ( $inline ) {
		/*
		 * Deliberately not `share-ai`: this is not an AI service button, and
		 * themes that restyle the row commonly target `.share-ai a`. Staying out
		 * of those selectors is the only way to keep the label legible against a
		 * theme using !important, which no amount of specificity can outrank.
		 */
		$classes = array( 'swi-google-source-inline' );
	} else {
		$align   = in_array( $args['align'], array( 'left', 'center', 'right' ), true ) ? $args['align'] : 'left';
		$classes = array( 'swi-google-source', 'swi-align-' . $align );
	}

	if ( '' !== trim( (string) $args['class'] ) ) {
		$classes = array_merge( $classes, explode( ' ', sanitize_text_field( $args['class'] ) ) );
	}

	$classes   = array_filter( array_map( 'sanitize_html_class', $classes ) );
	$analytics = (bool) summarizewithai_get_option( 'enable_analytics' );
	$new_tab   = (bool) summarizewithai_get_option( 'open_new_tab' );
	$post_id   = (int) get_the_ID();

	summarizewithai_enqueue_assets( $analytics );

	$show_text  = ! $inline || ! empty( $args['show_text'] );
	$link_class = $inline
		? 'swi-btn swi-google-source-button swi-google-source-button--inline'
		: 'swi-btn swi-google-source-button swi-google-source-button--standalone';
	$icon_size  = $inline ? 16 : 20;

	ob_start();
	?>
	<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php if ( $analytics && ! $inline ) : ?> data-swi-analytics="1"<?php endif; ?>>
		<a href="<?php echo esc_url( $link ); ?>"
			class="<?php echo esc_attr( $link_class ); ?>"
			<?php if ( $new_tab ) : ?>target="_blank" <?php endif; ?>rel="nofollow noopener noreferrer"
			title="<?php echo esc_attr( $label ); ?>"
			data-track-category="google"
			data-track-action="preferred_source_click"
			data-track-label="google_preferred_source"
			data-track-placement="summarize-with-ai"
			data-track-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
			data-track-post-url="<?php echo esc_attr( summarizewithai_get_current_url() ); ?>">
			<img src="<?php echo esc_url( SWI_URL . 'assets/img/google-g-icon.svg' ); ?>"
				alt=""
				aria-hidden="true"
				width="<?php echo esc_attr( (string) $icon_size ); ?>"
				height="<?php echo esc_attr( (string) $icon_size ); ?>"
				loading="lazy"
				decoding="async">
			<span class="<?php echo esc_attr( $show_text ? 'swi-google-source-text' : 'screen-reader-text' ); ?>"><?php echo esc_html( $label ); ?></span>
		</a>
	</div>
	<?php
	$html = (string) ob_get_clean();

	/**
	 * Filter the rendered preferred-source markup.
	 *
	 * @since 1.1.0
	 *
	 * @param string               $html Rendered markup.
	 * @param array<string, mixed> $args Rendering arguments.
	 */
	return (string) apply_filters( 'summarizewithai_google_source_output', $html, $args );
}

/**
 * Shortcode handler for `[summarizewithai_google_source]`.
 *
 * @since 1.1.0
 *
 * @param array<string, mixed>|string $atts Shortcode attributes.
 * @return string Rendered markup.
 */
function summarizewithai_google_source_shortcode( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'label' => '',
			'align' => (string) summarizewithai_get_option( 'google_source_align' ),
			'class' => '',
		),
		$atts,
		'summarizewithai_google_source'
	);

	return summarizewithai_render_google_source( $atts );
}
add_shortcode( 'summarizewithai_google_source', 'summarizewithai_google_source_shortcode' );

/**
 * Render callback for the preferred-source block.
 *
 * @since 1.1.0
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @return string Rendered markup.
 */
function summarizewithai_render_google_source_block( $attributes = array() ) {
	$attributes = is_array( $attributes ) ? $attributes : array();

	$html = summarizewithai_render_google_source(
		array(
			'label' => isset( $attributes['label'] ) ? (string) $attributes['label'] : '',
			'align' => isset( $attributes['align'] ) ? (string) $attributes['align'] : (string) summarizewithai_get_option( 'google_source_align' ),
		)
	);

	if ( '' === $html ) {
		return '';
	}

	$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : '';

	return '' === $wrapper ? $html : '<div ' . $wrapper . '>' . $html . '</div>';
}
