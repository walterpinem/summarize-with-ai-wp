<?php
/**
 * Front-end output: prompt building, rendering, shortcode, block and placement.
 *
 * @package SUMMARIZEAI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Maximum length of a generated deep link.
 *
 * Browsers and CDNs start truncating or rejecting URLs well before the
 * theoretical limit, so the encoded prompt is trimmed to keep the whole link
 * comfortably inside what every target accepts.
 */
define( 'SWI_MAX_URL_LENGTH', 1800 );

/**
 * Get the URL of the object currently being rendered.
 *
 * @since 1.0.0
 *
 * @return string Absolute URL.
 */
function summarizewithai_get_current_url() {
	$url = '';

	// Whatever post is in context, in the loop or on a singular view, is the
	// one the visitor wants summarised.
	if ( get_the_ID() ) {
		$permalink = get_permalink( get_the_ID() );

		if ( $permalink ) {
			$url = $permalink;
		}
	}

	if ( '' === $url ) {
		global $wp;

		$url = isset( $wp->request )
			? home_url( add_query_arg( array(), $wp->request ) )
			: home_url( '/' );
	}

	/**
	 * Filter the URL handed to the AI services.
	 *
	 * @since 1.1.0
	 *
	 * @param string $url Absolute URL.
	 */
	return (string) apply_filters( 'summarizewithai_current_url', $url );
}

/**
 * Reduce a URL to its bare domain, without protocol or `www.` prefix.
 *
 * @since 1.0.0
 *
 * @param string $url URL to parse.
 * @return string Domain, or an empty string when the URL has no host.
 */
function summarizewithai_extract_domain( $url ) {
	$host = wp_parse_url( (string) $url, PHP_URL_HOST );

	if ( ! is_string( $host ) || '' === $host ) {
		return '';
	}

	// Hostnames are case insensitive, and lowercasing first means the www.
	// prefix is found however the URL was typed.
	$host = strtolower( $host );

	if ( 0 === strpos( $host, 'www.' ) ) {
		$host = substr( $host, 4 );
	}

	return $host;
}

/**
 * Get a post excerpt without re-entering the content filters.
 *
 * A post with no hand-written excerpt sends get_the_excerpt() through
 * wp_trim_excerpt(), which applies `the_content` — including this plugin's own
 * placement filter. Detaching that filter for the duration keeps a prompt from
 * recursing into the content it is describing.
 *
 * @since 1.1.0
 *
 * @param int $post_id Post ID.
 * @return string Plain-text excerpt.
 */
function summarizewithai_get_safe_excerpt( $post_id ) {
	$was_hooked = remove_filter( 'the_content', 'summarizewithai_auto_placement', 20 );

	$excerpt = get_the_excerpt( $post_id );

	if ( $was_hooked ) {
		add_filter( 'the_content', 'summarizewithai_auto_placement', 20 );
	}

	return wp_strip_all_tags( (string) $excerpt );
}

/**
 * Get the placeholder tokens available inside the prompt, mapped to their values.
 *
 * The excerpt and category lookups are the only expensive ones, so they are
 * resolved only when the text being rendered actually asks for them.
 *
 * @since 1.1.0
 *
 * @param string|null $subject Optional. Text the placeholders will be substituted
 *                             into. Pass null to resolve every placeholder.
 * @return array<string, string> Token => replacement.
 */
function summarizewithai_get_placeholders( $subject = null ) {
	$post_id = get_the_ID();

	$wanted = static function ( $token ) use ( $subject ) {
		return null === $subject || false !== strpos( (string) $subject, $token );
	};

	$categories = '';

	if ( $post_id && $wanted( '{categories}' ) ) {
		$terms = get_the_terms( $post_id, 'category' );

		if ( is_array( $terms ) ) {
			$categories = implode( ', ', wp_list_pluck( $terms, 'name' ) );
		}
	}

	$placeholders = array(
		'{url}'              => summarizewithai_get_current_url(),
		'{site_name}'        => get_bloginfo( 'name' ),
		'{site_url}'         => summarizewithai_extract_domain( home_url() ),
		'{site_description}' => get_bloginfo( 'description' ),
		'{title}'            => $post_id ? wp_strip_all_tags( get_the_title( $post_id ) ) : '',
		'{excerpt}'          => ( $post_id && $wanted( '{excerpt}' ) ) ? summarizewithai_get_safe_excerpt( $post_id ) : '',
		'{author}'           => $post_id ? get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) ) : '',
		'{date}'             => $post_id ? get_the_date( '', $post_id ) : '',
		'{categories}'       => $categories,
		'{language}'         => determine_locale(),
	);

	/**
	 * Filter the prompt placeholders and their values.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, string> $placeholders Token => replacement.
	 * @param int|false             $post_id      Current post ID, or false.
	 * @param string|null           $subject      Text being substituted into.
	 */
	return (array) apply_filters( 'summarizewithai_placeholders', $placeholders, $post_id, $subject );
}

/**
 * Build the prompt for the current request, with every placeholder resolved.
 *
 * @since 1.1.0
 *
 * @param string $template Optional. Prompt template. Defaults to the saved prompt.
 * @return string Resolved prompt.
 */
function summarizewithai_build_prompt( $template = '' ) {
	if ( '' === $template ) {
		$template = (string) summarizewithai_get_option( 'ai_prompt' );
	}

	$placeholders = summarizewithai_get_placeholders( $template );

	$prompt = str_replace(
		array_keys( $placeholders ),
		array_values( $placeholders ),
		$template
	);

	// Collapse the whitespace left behind by placeholders that resolved to nothing.
	$prompt = trim( preg_replace( '/[ \t]{2,}/', ' ', $prompt ) );

	/**
	 * Filter the resolved prompt before it is URL encoded.
	 *
	 * @since 1.1.0
	 *
	 * @param string $prompt   Resolved prompt.
	 * @param string $template Prompt template it was built from.
	 */
	return (string) apply_filters( 'summarizewithai_prompt', $prompt, $template );
}

/**
 * Join a service base URL and an encoded prompt without exceeding the URL budget.
 *
 * @since 1.1.0
 *
 * @param string $base_url Service deep-link base URL.
 * @param string $prompt   Resolved, unencoded prompt.
 * @return string Complete deep link.
 */
function summarizewithai_build_service_url( $base_url, $prompt ) {
	$budget = SWI_MAX_URL_LENGTH - strlen( $base_url );

	if ( $budget < 1 ) {
		return $base_url;
	}

	$encoded = rawurlencode( $prompt );

	// Trim whole characters until the encoded prompt fits the remaining budget.
	while ( strlen( $encoded ) > $budget && '' !== $prompt ) {
		$prompt  = function_exists( 'mb_substr' )
			? mb_substr( $prompt, 0, max( 0, mb_strlen( $prompt ) - 16 ) )
			: substr( $prompt, 0, max( 0, strlen( $prompt ) - 16 ) );
		$encoded = rawurlencode( $prompt );
	}

	return $base_url . $encoded;
}

/**
 * Decide whether the buttons may be rendered for the current request.
 *
 * @since 1.1.0
 *
 * @return bool True when rendering is allowed.
 */
function summarizewithai_should_display() {
	$display = true;
	$post_id = get_the_ID();

	if ( $post_id ) {
		$excluded = array_filter( array_map( 'absint', explode( ',', (string) summarizewithai_get_option( 'excluded_post_ids' ) ) ) );

		if ( in_array( (int) $post_id, $excluded, true ) ) {
			$display = false;
		}
	}

	if ( is_feed() ) {
		$display = false;
	}

	/**
	 * Filter whether the buttons are rendered for the current request.
	 *
	 * @since 1.1.0
	 *
	 * @param bool      $display Whether to render.
	 * @param int|false $post_id Current post ID, or false.
	 */
	return (bool) apply_filters( 'summarizewithai_should_display', $display, $post_id );
}

/**
 * Register the front-end assets so they can be enqueued on demand.
 *
 * @since 1.1.0
 *
 * @return void
 */
function summarizewithai_register_assets() {
	wp_register_style(
		'summarize-with-ai',
		SWI_URL . 'assets/css/public.css',
		array(),
		SWI_VERSION
	);

	wp_register_script(
		'summarize-with-ai',
		SWI_URL . 'assets/js/frontend.js',
		array(),
		SWI_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'summarizewithai_register_assets' );
add_action( 'admin_enqueue_scripts', 'summarizewithai_register_assets' );

/**
 * Enqueue the assets the rendered markup actually needs.
 *
 * Called from the renderer so that pages without buttons stay asset free.
 * Styles and scripts enqueued this late are printed in the footer by core.
 *
 * @since 1.1.0
 *
 * @param bool $needs_script Whether the interactive script is required.
 * @return void
 */
function summarizewithai_enqueue_assets( $needs_script = false ) {
	if ( ! wp_style_is( 'summarize-with-ai', 'registered' ) ) {
		summarizewithai_register_assets();
	}

	wp_enqueue_style( 'summarize-with-ai' );

	if ( $needs_script ) {
		wp_enqueue_script( 'summarize-with-ai' );
	}
}

/**
 * Render the Summarize with AI buttons.
 *
 * @since 1.1.0
 *
 * @param array<string, mixed> $args {
 *     Optional. Rendering arguments.
 *
 *     @type string $services   Comma separated service IDs. Defaults to the enabled services.
 *     @type string $label      Text shown before the buttons. Empty string hides it.
 *     @type string $style      Button style: filled, outline or minimal.
 *     @type string $layout     Layout: inline or stacked.
 *     @type string $show_text  Whether button text is visible: yes or no.
 *     @type string $copy       Whether to show the copy-prompt button: yes or no.
 *     @type string $prompt     Prompt template override.
 *     @type string $class      Extra CSS classes for the wrapper.
 *     @type string $google_source Where the Google preferred-source button goes:
 *                                 auto (follow the setting), no, first, last or row.
 * }
 * @return string HTML markup, or an empty string when nothing should render.
 */
function summarizewithai_render( $args = array() ) {
	if ( ! summarizewithai_should_display() ) {
		return '';
	}

	$defaults = array(
		'services'  => implode( ',', summarizewithai_get_enabled_service_ids() ),
		'label'     => (string) summarizewithai_get_option( 'summarize_label' ),
		'style'     => (string) summarizewithai_get_option( 'button_style' ),
		'layout'    => (string) summarizewithai_get_option( 'layout' ),
		'show_text' => summarizewithai_get_option( 'show_button_text' ) ? 'yes' : 'no',
		'copy'      => summarizewithai_get_option( 'enable_copy' ) ? 'yes' : 'no',
		'prompt'    => '',
		'class'     => '',

		'google_source' => 'auto',
	);

	$args = wp_parse_args( $args, $defaults );

	// Resolve the requested services against the registry, preserving the requested order.
	$registry    = summarizewithai_get_services();
	$requested   = array_filter( array_map( 'sanitize_key', explode( ',', (string) $args['services'] ) ) );
	$service_ids = array();

	foreach ( $requested as $id ) {
		if ( isset( $registry[ $id ] ) && ! in_array( $id, $service_ids, true ) ) {
			$service_ids[] = $id;
		}
	}

	$show_copy   = ( 'yes' === $args['copy'] );
	$gs_position = summarizewithai_resolve_google_source_position( (string) $args['google_source'] );

	if ( empty( $service_ids ) && ! $show_copy ) {
		// With no AI buttons to sit among, an inline position has nothing to be
		// inline with, so fall back to the standalone form.
		return '' === $gs_position ? '' : summarizewithai_render_google_source();
	}

	$style     = in_array( $args['style'], array( 'filled', 'outline', 'minimal' ), true ) ? $args['style'] : 'filled';
	$layout    = in_array( $args['layout'], array( 'inline', 'stacked' ), true ) ? $args['layout'] : 'inline';
	$show_text = ( 'no' !== $args['show_text'] );
	$analytics = (bool) summarizewithai_get_option( 'enable_analytics' );
	$new_tab   = (bool) summarizewithai_get_option( 'open_new_tab' );

	$prompt = summarizewithai_build_prompt( (string) $args['prompt'] );

	summarizewithai_enqueue_assets( $show_copy || $analytics );

	// Tracking context, resolved once and stamped on every button.
	$post_id    = (int) get_the_ID();
	$post_title = $post_id ? get_the_title( $post_id ) : get_bloginfo( 'name' );
	$post_type  = $post_id ? (string) get_post_type( $post_id ) : 'unknown';
	$post_url   = summarizewithai_get_current_url();

	$classes = array(
		'share-with-ai',
		'swi-style-' . $style,
		'swi-layout-' . $layout,
	);

	if ( ! $show_text ) {
		$classes[] = 'swi-icons-only';
	}

	if ( '' !== trim( (string) $args['class'] ) ) {
		$classes = array_merge( $classes, explode( ' ', sanitize_text_field( $args['class'] ) ) );
	}

	$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );

	$google_inline = in_array( $gs_position, array( 'first', 'last' ), true )
		? summarizewithai_render_google_source(
			array(
				'variant'   => 'inline',
				'show_text' => $show_text,
			)
		)
		: '';

	ob_start();
	?>
	<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php if ( $analytics ) : ?> data-swi-analytics="1"<?php endif; ?>>
		<?php if ( '' !== trim( (string) $args['label'] ) ) : ?>
			<div class="share-ai-text">
				<span><?php echo esc_html( $args['label'] ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( 'first' === $gs_position ) : ?>
			<?php echo $google_inline; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped by summarizewithai_render_google_source(). ?>
		<?php endif; ?>

		<?php foreach ( $service_ids as $id ) : ?>
			<?php
			$service  = $registry[ $id ];
			$base_url = summarizewithai_get_service_url( $id );

			if ( '' === $base_url ) {
				continue;
			}

			$href = summarizewithai_build_service_url( $base_url, $prompt );

			/* translators: %s: AI service name, for example ChatGPT. */
			$action_label = sprintf( __( 'Summarize with %s', 'summarize-with-ai' ), $service['label'] );

			$inline_style = sprintf(
				'--swi-bg:%1$s;--swi-bg-hover:%2$s;',
				sanitize_hex_color( $service['color'] ) ? sanitize_hex_color( $service['color'] ) : '#1e1e1e',
				sanitize_hex_color( $service['hover'] ) ? sanitize_hex_color( $service['hover'] ) : '#3c3c3c'
			);
			?>
			<div class="share-ai <?php echo esc_attr( $id ); ?>">
				<a href="<?php echo esc_url( $href ); ?>"
					class="swi-btn summarize-with-ai-icon summarize-with-<?php echo esc_attr( $id ); ?>"
					style="<?php echo esc_attr( $inline_style ); ?>"
					<?php if ( $new_tab ) : ?>target="_blank" <?php endif; ?>rel="nofollow noopener noreferrer"
					title="<?php echo esc_attr( $action_label ); ?>"
					data-track-category="ai_tool"
					data-track-action="summarize_click"
					data-track-label="<?php echo esc_attr( $id ); ?>"
					data-track-placement="summarize-with-ai"
					data-track-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
					data-track-post-title="<?php echo esc_attr( $post_title ); ?>"
					data-track-post-url="<?php echo esc_attr( $post_url ); ?>"
					data-track-post-type="<?php echo esc_attr( $post_type ); ?>">
					<?php if ( '' !== $service['icon'] ) : ?>
						<img src="<?php echo esc_url( SWI_URL . 'assets/img/' . $service['icon'] ); ?>"
							alt=""
							aria-hidden="true"
							width="16"
							height="16"
							loading="lazy"
							decoding="async">
					<?php endif; ?>
					<span class="<?php echo esc_attr( $show_text ? 'swi-button-text' : 'screen-reader-text' ); ?>"><?php echo esc_html( $show_text ? $service['label'] : $action_label ); ?></span>
				</a>
			</div>
		<?php endforeach; ?>

		<?php if ( $show_copy ) : ?>
			<?php $copy_label = (string) summarizewithai_get_option( 'copy_label' ); ?>
			<div class="share-ai swi-copy">
				<button type="button"
					class="swi-btn summarize-with-ai-icon swi-copy-button"
					data-swi-prompt="<?php echo esc_attr( $prompt ); ?>"
					data-swi-copied="<?php esc_attr_e( 'Copied!', 'summarize-with-ai' ); ?>"
					data-track-category="ai_tool"
					data-track-action="summarize_click"
					data-track-label="copy"
					data-track-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
					data-track-post-url="<?php echo esc_attr( $post_url ); ?>">
					<span aria-hidden="true" class="swi-copy-icon"></span>
					<span class="<?php echo esc_attr( $show_text ? 'swi-button-text' : 'screen-reader-text' ); ?>"><?php echo esc_html( $copy_label ); ?></span>
				</button>
			</div>
		<?php endif; ?>

		<?php if ( 'last' === $gs_position ) : ?>
			<?php echo $google_inline; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped by summarizewithai_render_google_source(). ?>
		<?php endif; ?>
	</div>
	<?php
	$html = (string) ob_get_clean();

	// The "row" position puts it in a block of its own below the buttons.
	if ( 'row' === $gs_position ) {
		$html .= summarizewithai_render_google_source();
	}

	/**
	 * Filter the rendered markup.
	 *
	 * @since 1.1.0
	 *
	 * @param string               $html Rendered markup.
	 * @param array<string, mixed> $args Rendering arguments.
	 */
	return (string) apply_filters( 'summarizewithai_output', $html, $args );
}

/**
 * Shortcode handler for `[summarizewithai]`.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed>|string $atts Shortcode attributes.
 * @return string Rendered markup.
 */
function summarizewithai_shortcode( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'services'  => implode( ',', summarizewithai_get_enabled_service_ids() ),
			'label'     => (string) summarizewithai_get_option( 'summarize_label' ),
			'style'     => (string) summarizewithai_get_option( 'button_style' ),
			'layout'    => (string) summarizewithai_get_option( 'layout' ),
			'show_text' => summarizewithai_get_option( 'show_button_text' ) ? 'yes' : 'no',
			'copy'      => summarizewithai_get_option( 'enable_copy' ) ? 'yes' : 'no',
			'prompt'    => '',
			'class'     => '',

			'google_source' => 'auto',
		),
		$atts,
		'summarizewithai'
	);

	return summarizewithai_render( $atts );
}
add_shortcode( 'summarizewithai', 'summarizewithai_shortcode' );

/**
 * Append or prepend the buttons to post content when automatic placement is on.
 *
 * @since 1.1.0
 *
 * @param string $content Post content.
 * @return string Filtered content.
 */
function summarizewithai_auto_placement( $content ) {
	static $running = false;

	/*
	 * Anything that applies `the_content` while this filter is already running
	 * is describing the same post, not asking for another set of buttons.
	 */
	if ( $running || is_admin() || ! is_singular() ) {
		return $content;
	}

	$ai_placement = (string) summarizewithai_get_option( 'auto_placement' );
	$gs_placement = (string) summarizewithai_get_option( 'google_source_placement' );
	$gs_standalone = in_array( $gs_placement, array( 'before', 'after', 'both' ), true );

	if ( 'none' === $ai_placement && ! $gs_standalone ) {
		return $content;
	}

	/*
	 * Only touch the post the request is actually for. Checking the queried
	 * object rather than in_the_loop() keeps this working in block themes,
	 * where core/post-content applies the filter outside the loop, while still
	 * skipping secondary loops, widgets and related-post lists.
	 */
	$post = get_post();

	if ( ! $post || (int) $post->ID !== (int) get_queried_object_id() ) {
		return $content;
	}

	// A manually placed shortcode or block always wins over automatic placement.
	$has_manual_ai = has_shortcode( $post->post_content, 'summarizewithai' )
		|| has_block( 'summarizewithai/buttons', $post );

	$has_manual_gs = has_shortcode( $post->post_content, 'summarizewithai_google_source' )
		|| has_block( 'summarizewithai/google-source', $post );

	$before = '';
	$after  = '';

	$running = true;

	// Summarize with AI buttons.
	$ai_types = array_filter( (array) summarizewithai_get_option( 'auto_post_types' ) );

	if ( 'none' !== $ai_placement && ! $has_manual_ai && $ai_types && is_singular( $ai_types ) ) {
		$attached = $has_manual_gs ? '' : summarizewithai_resolve_google_source_position( 'auto' );
		$attached = '' === $attached ? 'no' : $attached;

		if ( 'both' === $ai_placement ) {
			// The buttons are worth repeating; the invitation to follow the
			// site is not, so it goes with the last copy only.
			$before .= summarizewithai_render( array( 'google_source' => 'no' ) );
			$after  .= summarizewithai_render( array( 'google_source' => $attached ) );
		} elseif ( 'before' === $ai_placement ) {
			$before .= summarizewithai_render( array( 'google_source' => $attached ) );
		} else {
			$after .= summarizewithai_render( array( 'google_source' => $attached ) );
		}
	}

	// Preferred-source button placed on its own.
	$gs_types = array_filter( (array) summarizewithai_get_option( 'google_source_post_types' ) );

	if ( $gs_standalone && ! $has_manual_gs && $gs_types && is_singular( $gs_types ) ) {
		$google = summarizewithai_render_google_source();

		if ( 'before' === $gs_placement || 'both' === $gs_placement ) {
			$before .= $google;
		}

		if ( 'after' === $gs_placement || 'both' === $gs_placement ) {
			$after .= $google;
		}
	}

	$running = false;

	return $before . $content . $after;
}
add_filter( 'the_content', 'summarizewithai_auto_placement', 20 );

/**
 * Register the editor block backed by the same renderer as the shortcode.
 *
 * @since 1.1.0
 *
 * @return void
 */
function summarizewithai_register_block() {
	if ( ! function_exists( 'register_block_type' ) || ! file_exists( SWI_DIR . 'blocks/summarize-with-ai/block.json' ) ) {
		return;
	}

	wp_register_script(
		'summarize-with-ai-block',
		SWI_URL . 'assets/js/block.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
		SWI_VERSION,
		true
	);

	wp_set_script_translations( 'summarize-with-ai-block', 'summarize-with-ai', SWI_DIR . 'languages' );

	$block_data = array(
		'services' => array(),
		'defaults' => array(
			'services'    => summarizewithai_get_enabled_service_ids(),
			'label'       => (string) summarizewithai_get_option( 'summarize_label' ),
			'buttonStyle' => (string) summarizewithai_get_option( 'button_style' ),
			'layout'      => (string) summarizewithai_get_option( 'layout' ),
			'googleLabel' => (string) summarizewithai_get_option( 'google_source_label' ),
		),
	);

	foreach ( summarizewithai_get_services() as $id => $service ) {
		$block_data['services'][] = array(
			'id'    => $id,
			'label' => $service['label'],
		);
	}

	wp_add_inline_script(
		'summarize-with-ai-block',
		'window.summarizeWithAIBlock = ' . wp_json_encode( $block_data ) . ';',
		'before'
	);

	register_block_type(
		SWI_DIR . 'blocks/summarize-with-ai',
		array(
			'render_callback' => 'summarizewithai_render_block',
		)
	);

	if ( file_exists( SWI_DIR . 'blocks/summarize-with-ai-google-source/block.json' ) ) {
		register_block_type(
			SWI_DIR . 'blocks/summarize-with-ai-google-source',
			array(
				'render_callback' => 'summarizewithai_render_google_source_block',
			)
		);
	}
}
add_action( 'init', 'summarizewithai_register_block' );

/**
 * Render callback for the editor block.
 *
 * @since 1.1.0
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @return string Rendered markup.
 */
function summarizewithai_render_block( $attributes = array() ) {
	$attributes = is_array( $attributes ) ? $attributes : array();

	$args = array(
		'label'     => isset( $attributes['label'] ) ? (string) $attributes['label'] : (string) summarizewithai_get_option( 'summarize_label' ),
		'style'     => isset( $attributes['buttonStyle'] ) ? (string) $attributes['buttonStyle'] : (string) summarizewithai_get_option( 'button_style' ),
		'layout'    => isset( $attributes['layout'] ) ? (string) $attributes['layout'] : (string) summarizewithai_get_option( 'layout' ),
		'show_text' => ( ! isset( $attributes['showText'] ) || $attributes['showText'] ) ? 'yes' : 'no',
		'copy'      => ! empty( $attributes['showCopy'] ) ? 'yes' : 'no',

		'google_source' => isset( $attributes['googleSource'] ) ? (string) $attributes['googleSource'] : 'auto',
	);

	if ( ! empty( $attributes['services'] ) && is_array( $attributes['services'] ) ) {
		$args['services'] = implode( ',', array_map( 'sanitize_key', $attributes['services'] ) );
	}

	$html = summarizewithai_render( $args );

	if ( '' === $html ) {
		return '';
	}

	$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : '';

	return '' === $wrapper ? $html : '<div ' . $wrapper . '>' . $html . '</div>';
}
