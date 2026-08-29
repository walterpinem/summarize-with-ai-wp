<?php
/**
 * Admin settings page view.
 *
 * Loaded by summarizewithai_render_settings_page(), which has already checked
 * the `manage_options` capability. The form posts to options.php, so WordPress
 * verifies the nonce and runs summarizewithai_sanitize_options() on the input.
 *
 * The tabs are presentation only: every panel lives inside the one form, so a
 * single save writes every section. Without JavaScript the panels simply stack,
 * and the page keeps working exactly as before.
 *
 * @package SUMMARIZEAI
 */

defined( 'ABSPATH' ) || exit;

$swi_options    = summarizewithai_get_options();
$swi_services   = summarizewithai_get_services();
$swi_post_types = summarizewithai_get_selectable_post_types();
$swi_enabled    = summarizewithai_get_enabled_service_ids();
$swi_auto_types = (array) $swi_options['auto_post_types'];

$swi_tabs = array(
	'prompt'     => __( 'Prompt', 'summarize-with-ai' ),
	'services'   => __( 'AI Services', 'summarize-with-ai' ),
	'placement'  => __( 'Placement', 'summarize-with-ai' ),
	'appearance' => __( 'Appearance', 'summarize-with-ai' ),
	'behaviour'  => __( 'Behaviour', 'summarize-with-ai' ),
	'google'     => __( 'Google', 'summarize-with-ai' ),
	'usage'      => __( 'Usage', 'summarize-with-ai' ),
);
?>
<div class="wrap summarizewithai-settings">
	<h1><?php esc_html_e( 'Summarize with AI Settings', 'summarize-with-ai' ); ?></h1>

	<p class="description">
		<?php
		printf(
			wp_kses(
				/* translators: 1: GitHub repository URL, 2: link text, 3: blog post URL, 4: link text. */
				__( 'For more information about this plugin, visit <a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a> or refer to the related <a href="%3$s" target="_blank" rel="noopener noreferrer">%4$s</a>.', 'summarize-with-ai' ),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
				)
			),
			esc_url( 'https://github.com/walterpinem/summarize-with-ai-wp' ),
			esc_html__( 'GitHub', 'summarize-with-ai' ),
			esc_url( 'https://walterpinem.com/summarize-with-ai-wordpress-plugin/' ),
			esc_html__( 'blog post', 'summarize-with-ai' )
		);
		?>
	</p>

	<?php settings_errors( 'summarizewithai_options' ); ?>

	<nav class="nav-tab-wrapper swi-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'summarize-with-ai' ); ?>">
		<?php foreach ( $swi_tabs as $swi_tab_id => $swi_tab_label ) : ?>
			<a href="#swi-panel-<?php echo esc_attr( $swi_tab_id ); ?>"
				class="nav-tab"
				data-swi-tab="<?php echo esc_attr( $swi_tab_id ); ?>">
				<?php echo esc_html( $swi_tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<form method="post" action="options.php">
		<?php settings_fields( 'summarizewithai_settings' ); ?>

		<!-- Prompt ------------------------------------------------------- -->
		<div class="swi-tab-panel" id="swi-panel-prompt">
			<h2 class="title"><?php esc_html_e( 'Prompt', 'summarize-with-ai' ); ?></h2>
			<p class="description"><?php esc_html_e( 'The text every button sends to the AI service.', 'summarize-with-ai' ); ?></p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="swi-ai-prompt"><?php esc_html_e( 'AI Prompt', 'summarize-with-ai' ); ?></label>
						</th>
						<td>
							<textarea name="summarizewithai_options[ai_prompt]" id="swi-ai-prompt" rows="6" cols="80" class="large-text code"><?php echo esc_textarea( $swi_options['ai_prompt'] ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Placeholders are replaced with the values of the page the buttons appear on.', 'summarize-with-ai' ); ?>
								<?php
								printf(
									/* translators: %d: maximum number of characters in a generated link. */
									esc_html__( 'Very long prompts are trimmed so the generated link stays under %d characters.', 'summarize-with-ai' ),
									(int) SWI_MAX_URL_LENGTH
								);
								?>
							</p>

							<?php
						$swi_preview = summarizewithai_get_prompt_preview();
						?>
						<div class="swi-callout">
							<h4><?php esc_html_e( 'What your readers actually send', 'summarize-with-ai' ); ?></h4>
							<p class="swi-prompt-preview"><?php echo esc_html( $swi_preview['prompt'] ); ?></p>
							<p class="description">
								<?php
								if ( $swi_preview['post'] ) {
									printf(
										/* translators: %s: post title used for the preview. */
										esc_html__( 'Resolved against your most recent post, %s. Each page fills in its own values.', 'summarize-with-ai' ),
										'<em>' . esc_html( get_the_title( $swi_preview['post'] ) ) . '</em>'
									);
								} else {
									esc_html_e( 'Publish a post to see the placeholders resolved against real content.', 'summarize-with-ai' );
								}

								echo ' ';

								printf(
									/* translators: 1: current prompt length, 2: maximum link length. */
									esc_html__( 'Length: %1$d characters once encoded, out of roughly %2$d available.', 'summarize-with-ai' ),
									strlen( rawurlencode( $swi_preview['prompt'] ) ),
									(int) SWI_MAX_URL_LENGTH
								);
								?>
							</p>
						</div>

						<p class="description"><strong><?php esc_html_e( 'Placeholders you can use', 'summarize-with-ai' ); ?></strong></p>

						<table class="widefat striped summarizewithai-placeholders">
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Placeholder', 'summarize-with-ai' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Replaced with', 'summarize-with-ai' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									$swi_placeholder_help = array(
										'{url}'              => __( 'URL of the current page or post', 'summarize-with-ai' ),
										'{title}'            => __( 'Title of the current post', 'summarize-with-ai' ),
										'{excerpt}'          => __( 'Excerpt of the current post', 'summarize-with-ai' ),
										'{author}'           => __( 'Display name of the post author', 'summarize-with-ai' ),
										'{date}'             => __( 'Publish date of the current post', 'summarize-with-ai' ),
										'{categories}'       => __( 'Comma separated category names', 'summarize-with-ai' ),
										'{language}'         => __( 'Locale of the current page', 'summarize-with-ai' ),
										'{site_name}'        => sprintf(
											/* translators: %s: site title. */
											__( 'Site title: %s', 'summarize-with-ai' ),
											get_bloginfo( 'name' )
										),
										'{site_url}'         => sprintf(
											/* translators: %s: site domain without protocol. */
											__( 'Site domain only: %s', 'summarize-with-ai' ),
											summarizewithai_extract_domain( home_url() )
										),
										'{site_description}' => __( 'Site tagline', 'summarize-with-ai' ),
									);

									foreach ( $swi_placeholder_help as $swi_token => $swi_description ) :
										?>
										<tr>
											<td><code><?php echo esc_html( $swi_token ); ?></code></td>
											<td><?php echo esc_html( $swi_description ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- AI Services -------------------------------------------------- -->
		<div class="swi-tab-panel" id="swi-panel-services">
			<h2 class="title"><?php esc_html_e( 'AI Services', 'summarize-with-ai' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Enable the services you want to show and, if needed, adjust the URL each button points to. Buttons appear in the order listed here.', 'summarize-with-ai' ); ?></p>
			<p class="description">
				<?php esc_html_e( 'Each button is an ordinary link. Clicking one opens that service with your prompt already typed in. There is no API call, no API key and no cost, and nothing is sent from your server.', 'summarize-with-ai' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'You rarely need to touch the URL fields. They exist so you can follow a service if it moves, or point a button somewhere else entirely.', 'summarize-with-ai' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<?php foreach ( $swi_services as $swi_id => $swi_service ) : ?>
						<tr>
							<th scope="row">
								<label for="swi-service-<?php echo esc_attr( $swi_id ); ?>">
									<?php echo esc_html( $swi_service['label'] ); ?>
								</label>
							</th>
							<td>
								<fieldset>
									<legend class="screen-reader-text">
										<?php
										printf(
											/* translators: %s: AI service name. */
											esc_html__( '%s settings', 'summarize-with-ai' ),
											esc_html( $swi_service['label'] )
										);
										?>
									</legend>

									<label for="swi-service-<?php echo esc_attr( $swi_id ); ?>">
										<input type="checkbox"
											id="swi-service-<?php echo esc_attr( $swi_id ); ?>"
											name="summarizewithai_options[enabled_services][]"
											value="<?php echo esc_attr( $swi_id ); ?>"
											<?php checked( in_array( $swi_id, $swi_enabled, true ) ); ?> />
										<?php
										printf(
											/* translators: %s: AI service name. */
											esc_html__( 'Show the %s button', 'summarize-with-ai' ),
											esc_html( $swi_service['label'] )
										);
										?>
									</label>

									<br />

									<input type="url"
										name="summarizewithai_options[<?php echo esc_attr( $swi_id ); ?>_url]"
										id="swi-service-url-<?php echo esc_attr( $swi_id ); ?>"
										value="<?php echo esc_attr( summarizewithai_get_service_url( $swi_id ) ); ?>"
										class="regular-text code"
										inputmode="url"
										placeholder="<?php echo esc_attr( $swi_service['url'] ); ?>" />

									<p class="description">
										<?php esc_html_e( 'The prompt is URL encoded and appended to this address. Leave empty to restore the default.', 'summarize-with-ai' ); ?>
									</p>
								</fieldset>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="description">
				<?php esc_html_e( 'Only assistants with a prefill parameter confirmed to work are bundled. Gemini and Microsoft Copilot ignore theirs, so the copy-prompt button on the Behaviour tab covers those tools instead.', 'summarize-with-ai' ); ?>
			</p>
		</div>

		<!-- Placement ---------------------------------------------------- -->
		<div class="swi-tab-panel" id="swi-panel-placement">
			<h2 class="title"><?php esc_html_e( 'Placement', 'summarize-with-ai' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Where the buttons appear without adding a shortcode or a block by hand. For most sites this tab is the only one you need.', 'summarize-with-ai' ); ?></p>
			<p class="description">
				<?php esc_html_e( 'Automatic placement runs on single posts only, never on archives, category pages or the home page.', 'summarize-with-ai' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Automatic placement', 'summarize-with-ai' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Automatic placement', 'summarize-with-ai' ); ?></legend>
								<?php
								$swi_placements = array(
									'none'   => __( 'Do not place automatically (use the shortcode or block)', 'summarize-with-ai' ),
									'before' => __( 'Before the content', 'summarize-with-ai' ),
									'after'  => __( 'After the content', 'summarize-with-ai' ),
									'both'   => __( 'Before and after the content', 'summarize-with-ai' ),
								);

								foreach ( $swi_placements as $swi_value => $swi_text ) :
									?>
									<label>
										<input type="radio"
											name="summarizewithai_options[auto_placement]"
											value="<?php echo esc_attr( $swi_value ); ?>"
											<?php checked( $swi_options['auto_placement'], $swi_value ); ?> />
										<?php echo esc_html( $swi_text ); ?>
									</label>
									<br />
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'Automatic placement is skipped on any post that already contains the shortcode or the block.', 'summarize-with-ai' ); ?></p>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Post types', 'summarize-with-ai' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Post types', 'summarize-with-ai' ); ?></legend>
								<?php foreach ( $swi_post_types as $swi_type => $swi_type_label ) : ?>
									<label>
										<input type="checkbox"
											name="summarizewithai_options[auto_post_types][]"
											value="<?php echo esc_attr( $swi_type ); ?>"
											<?php checked( in_array( $swi_type, $swi_auto_types, true ) ); ?> />
										<?php echo esc_html( $swi_type_label ); ?>
									</label>
									<br />
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'Which single post types receive the automatic placement.', 'summarize-with-ai' ); ?></p>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="swi-excluded"><?php esc_html_e( 'Exclude post IDs', 'summarize-with-ai' ); ?></label>
						</th>
						<td>
							<input type="text"
								name="summarizewithai_options[excluded_post_ids]"
								id="swi-excluded"
								value="<?php echo esc_attr( $swi_options['excluded_post_ids'] ); ?>"
								class="regular-text code"
								placeholder="12, 34, 56" />
							<p class="description"><?php esc_html_e( 'Comma separated post IDs that never show the buttons, even where the shortcode is present.', 'summarize-with-ai' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Appearance --------------------------------------------------- -->
		<div class="swi-tab-panel" id="swi-panel-appearance">
			<h2 class="title"><?php esc_html_e( 'Appearance', 'summarize-with-ai' ); ?></h2>
			<p class="description"><?php esc_html_e( 'How the buttons look on the front end. The preview below updates as you change these settings, before you save.', 'summarize-with-ai' ); ?></p>

			<div class="swi-preview-frame">
				<div class="swi-preview-bar">
					<span><?php esc_html_e( 'Live preview', 'summarize-with-ai' ); ?></span>
					<label class="swi-preview-toggle">
						<input type="checkbox" id="swi-preview-dark" />
						<?php esc_html_e( 'Dark background', 'summarize-with-ai' ); ?>
					</label>
				</div>
				<div class="swi-preview-stage" id="swi-preview-stage">
					<?php
					// Real front-end markup, rendered with every service on so the
					// script can show and hide them as the checkboxes change.
					echo summarizewithai_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the renderer.
						array(
							'services'      => implode( ',', array_keys( $swi_services ) ),
							'copy'          => 'yes',
							'google_source' => 'no',
						)
					);
					?>
				</div>
			</div>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="swi-label"><?php esc_html_e( 'Summarize label', 'summarize-with-ai' ); ?></label>
						</th>
						<td>
							<input type="text"
								name="summarizewithai_options[summarize_label]"
								id="swi-label"
								value="<?php echo esc_attr( $swi_options['summarize_label'] ); ?>"
								class="regular-text" />
							<p class="description"><?php esc_html_e( 'Text shown before the buttons. Leave empty to hide it.', 'summarize-with-ai' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="swi-button-style"><?php esc_html_e( 'Button style', 'summarize-with-ai' ); ?></label>
						</th>
						<td>
							<select name="summarizewithai_options[button_style]" id="swi-button-style">
								<?php
								$swi_styles = array(
									'filled'  => __( 'Filled (brand colours)', 'summarize-with-ai' ),
									'outline' => __( 'Outline', 'summarize-with-ai' ),
									'minimal' => __( 'Minimal (text and icon only)', 'summarize-with-ai' ),
								);

								foreach ( $swi_styles as $swi_value => $swi_text ) :
									?>
									<option value="<?php echo esc_attr( $swi_value ); ?>" <?php selected( $swi_options['button_style'], $swi_value ); ?>>
										<?php echo esc_html( $swi_text ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Filled uses each service brand colour and stands out most. Outline keeps a border only, which suits restrained designs. Minimal drops the button shape entirely and leaves the icon and name.', 'summarize-with-ai' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="swi-layout"><?php esc_html_e( 'Layout', 'summarize-with-ai' ); ?></label>
						</th>
						<td>
							<select name="summarizewithai_options[layout]" id="swi-layout">
								<option value="inline" <?php selected( $swi_options['layout'], 'inline' ); ?>><?php esc_html_e( 'Inline (wraps on small screens)', 'summarize-with-ai' ); ?></option>
								<option value="stacked" <?php selected( $swi_options['layout'], 'stacked' ); ?>><?php esc_html_e( 'Stacked (full width buttons)', 'summarize-with-ai' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Inline suits a row inside an article. Stacked suits a narrow sidebar, or a long list of services.', 'summarize-with-ai' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Button text', 'summarize-with-ai' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="summarizewithai_options[show_button_text]"
									value="1"
									<?php checked( $swi_options['show_button_text'], 1 ); ?> />
								<?php esc_html_e( 'Show the service name next to the icon', 'summarize-with-ai' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'When off, buttons show icons only. The service name stays available to screen readers.', 'summarize-with-ai' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Behaviour ---------------------------------------------------- -->
		<div class="swi-tab-panel" id="swi-panel-behaviour">
			<h2 class="title"><?php esc_html_e( 'Behaviour', 'summarize-with-ai' ); ?></h2>
			<p class="description"><?php esc_html_e( 'What happens when a visitor uses the buttons.', 'summarize-with-ai' ); ?></p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Copy prompt button', 'summarize-with-ai' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="summarizewithai_options[enable_copy]"
									value="1"
									<?php checked( $swi_options['enable_copy'], 1 ); ?> />
								<?php esc_html_e( 'Add a button that copies the prompt to the clipboard', 'summarize-with-ai' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Lets visitors paste the prompt into any AI tool, including those without a shareable link.', 'summarize-with-ai' ); ?></p>
							<p>
								<label for="swi-copy-label"><?php esc_html_e( 'Copy button label', 'summarize-with-ai' ); ?></label><br />
								<input type="text"
									name="summarizewithai_options[copy_label]"
									id="swi-copy-label"
									value="<?php echo esc_attr( $swi_options['copy_label'] ); ?>"
									class="regular-text" />
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Open in a new tab', 'summarize-with-ai' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="summarizewithai_options[open_new_tab]"
									value="1"
									<?php checked( $swi_options['open_new_tab'], 1 ); ?> />
								<?php esc_html_e( 'Open AI services in a new browser tab', 'summarize-with-ai' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Click tracking', 'summarize-with-ai' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="summarizewithai_options[enable_analytics]"
									value="1"
									<?php checked( $swi_options['enable_analytics'], 1 ); ?> />
								<?php esc_html_e( 'Send a click event to Google Analytics or Google Tag Manager', 'summarize-with-ai' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Uses whichever of gtag() or dataLayer your site already loads. Nothing is sent to the plugin author, and no cookies are set. Only enable this if your privacy policy covers analytics.', 'summarize-with-ai' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Google ------------------------------------------------------- -->
		<div class="swi-tab-panel" id="swi-panel-google">
			<h2 class="title"><?php esc_html_e( 'Add as a preferred source on Google', 'summarize-with-ai' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Invites readers to mark your site as a preferred source in Google Search. This button is about the site rather than the post, so it carries no prompt.', 'summarize-with-ai' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'It changes what each reader who opts in sees in Top Stories, and in AI Overviews and AI Mode where those exist. It does not change your rankings for everyone, and it does not guarantee placement.', 'summarize-with-ai' ); ?>
			</p>
			<p class="description">
				<?php
				printf(
					wp_kses(
						/* translators: 1: source preferences tool URL, 2: link text, 3: Google documentation URL, 4: link text. */
						__( 'Before publishing the button, check that your site already appears in <a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>. Only domains and subdomains are eligible, never subdirectories. See <a href="%3$s" target="_blank" rel="noopener noreferrer">%4$s</a>.', 'summarize-with-ai' ),
						array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
					),
					esc_url( summarizewithai_get_google_source_link() ),
					esc_html__( 'the source preferences tool', 'summarize-with-ai' ),
					esc_url( 'https://developers.google.com/search/docs/appearance/preferred-sources' ),
					esc_html__( 'Google&#8217;s documentation', 'summarize-with-ai' )
				);
				?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Placement', 'summarize-with-ai' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Placement', 'summarize-with-ai' ); ?></legend>
								<?php foreach ( summarizewithai_get_google_source_placements() as $swi_value => $swi_text ) : ?>
									<label>
										<input type="radio"
											name="summarizewithai_options[google_source_placement]"
											value="<?php echo esc_attr( $swi_value ); ?>"
											<?php checked( $swi_options['google_source_placement'], $swi_value ); ?> />
										<?php echo esc_html( $swi_text ); ?>
									</label>
									<br />
								<?php endforeach; ?>
								<p class="description">
									<?php esc_html_e( 'The first option keeps it fully manual. The next three tie it to the AI buttons, so it appears wherever they do: either as one more button in the row, or in a row of its own underneath. The last three place it on its own, independently of the AI buttons and of their post types.', 'summarize-with-ai' ); ?>
								</p>
								<p class="description">
									<?php esc_html_e( 'Automatic placement is skipped on any post that already contains the Google shortcode or block, and when the AI buttons appear both before and after the content the invitation is added only once, to the last copy.', 'summarize-with-ai' ); ?>
								</p>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Post types', 'summarize-with-ai' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Post types', 'summarize-with-ai' ); ?></legend>
								<?php
								$swi_gs_types = (array) $swi_options['google_source_post_types'];

								foreach ( $swi_post_types as $swi_type => $swi_type_label ) :
									?>
									<label>
										<input type="checkbox"
											name="summarizewithai_options[google_source_post_types][]"
											value="<?php echo esc_attr( $swi_type ); ?>"
											<?php checked( in_array( $swi_type, $swi_gs_types, true ) ); ?> />
										<?php echo esc_html( $swi_type_label ); ?>
									</label>
									<br />
								<?php endforeach; ?>
								<p class="description">
									<?php esc_html_e( 'Used by the three standalone placements above. The two inline placements and the "own row" option travel with the AI buttons, so they follow the AI post types instead.', 'summarize-with-ai' ); ?>
								</p>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Button', 'summarize-with-ai' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Button', 'summarize-with-ai' ); ?></legend>

								<label>
									<input type="radio"
										name="summarizewithai_options[google_source_button]"
										value="plugin"
										<?php checked( $swi_options['google_source_button'], 'plugin' ); ?> />
									<?php esc_html_e( 'This plugin&#8217;s button', 'summarize-with-ai' ); ?>
								</label>
								<p class="description" style="margin-left:24px;">
									<?php esc_html_e( 'A plain link you control: your own wording, your own styling, and no request to Google from your visitors&#8217; browsers.', 'summarize-with-ai' ); ?>
								</p>

								<label>
									<input type="radio"
										name="summarizewithai_options[google_source_button]"
										value="official"
										<?php checked( $swi_options['google_source_button'], 'official' ); ?> />
									<?php esc_html_e( 'Google&#8217;s official button', 'summarize-with-ai' ); ?>
								</label>
								<p class="description" style="margin-left:24px;">
									<?php esc_html_e( 'Google&#8217;s own branded button, translated by Google and kept up to date by them. It loads a script from news.google.com on pages where the button appears, so your visitors make a request to Google. The wording, colours and label settings below do not apply to it.', 'summarize-with-ai' ); ?>
								</p>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Google button options', 'summarize-with-ai' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Google button options', 'summarize-with-ai' ); ?></legend>

								<p>
									<label for="swi-google-theme"><?php esc_html_e( 'Theme', 'summarize-with-ai' ); ?></label><br />
									<select name="summarizewithai_options[google_source_theme]" id="swi-google-theme">
										<option value="light" <?php selected( $swi_options['google_source_theme'], 'light' ); ?>><?php esc_html_e( 'Light', 'summarize-with-ai' ); ?></option>
										<option value="dark" <?php selected( $swi_options['google_source_theme'], 'dark' ); ?>><?php esc_html_e( 'Dark', 'summarize-with-ai' ); ?></option>
									</select>
								</p>

								<p>
									<label for="swi-google-lang"><?php esc_html_e( 'Language', 'summarize-with-ai' ); ?></label><br />
									<input type="text"
										name="summarizewithai_options[google_source_lang]"
										id="swi-google-lang"
										value="<?php echo esc_attr( $swi_options['google_source_lang'] ); ?>"
										class="small-text code"
										placeholder="<?php echo esc_attr( substr( (string) determine_locale(), 0, 2 ) ); ?>" />
								</p>

								<p class="description">
									<?php esc_html_e( 'Both apply to Google&#8217;s official button only. Leave the language empty to let each visitor&#8217;s browser decide, or enter a code such as en, ja or id.', 'summarize-with-ai' ); ?>
								</p>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="swi-google-align"><?php esc_html_e( 'Alignment', 'summarize-with-ai' ); ?></label>
						</th>
						<td>
							<select name="summarizewithai_options[google_source_align]" id="swi-google-align">
								<?php
								$swi_aligns = array(
									'left'   => __( 'Left', 'summarize-with-ai' ),
									'center' => __( 'Center', 'summarize-with-ai' ),
									'right'  => __( 'Right', 'summarize-with-ai' ),
								);

								foreach ( $swi_aligns as $swi_value => $swi_text ) :
									?>
									<option value="<?php echo esc_attr( $swi_value ); ?>" <?php selected( $swi_options['google_source_align'], $swi_value ); ?>>
										<?php echo esc_html( $swi_text ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Applies to the standalone placements, the shortcode and the block. The inline placements follow the alignment of the AI button row.', 'summarize-with-ai' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="swi-google-label"><?php esc_html_e( 'Button text', 'summarize-with-ai' ); ?></label>
						</th>
						<td>
							<input type="text"
								name="summarizewithai_options[google_source_label]"
								id="swi-google-label"
								value="<?php echo esc_attr( $swi_options['google_source_label'] ); ?>"
								class="large-text" />
							<p class="description">
								<?php esc_html_e( 'Used by the standalone placements, the shortcode and the block. The same placeholders as the prompt work here, and a short name often reads better than the full site title.', 'summarize-with-ai' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="swi-google-inline-label"><?php esc_html_e( 'Inline button text', 'summarize-with-ai' ); ?></label>
						</th>
						<td>
							<input type="text"
								name="summarizewithai_options[google_source_inline_label]"
								id="swi-google-inline-label"
								value="<?php echo esc_attr( $swi_options['google_source_inline_label'] ); ?>"
								class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'Used by the two "among the AI buttons" placements. Keep it short so it sits comfortably next to ChatGPT and Claude.', 'summarize-with-ai' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="swi-google-domain"><?php esc_html_e( 'Domain', 'summarize-with-ai' ); ?></label>
						</th>
						<td>
							<input type="text"
								name="summarizewithai_options[google_source_domain]"
								id="swi-google-domain"
								value="<?php echo esc_attr( $swi_options['google_source_domain'] ); ?>"
								class="regular-text code"
								placeholder="<?php echo esc_attr( summarizewithai_extract_domain( home_url() ) ); ?>" />
							<p class="description">
								<?php esc_html_e( 'The domain Google is asked to remember. Leave empty to use the domain of this site, which is what you want unless you are working on a staging copy and need the live domain instead.', 'summarize-with-ai' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Preview', 'summarize-with-ai' ); ?></th>
						<td>
							<?php
							$swi_google_link = summarizewithai_get_google_source_link();

							if ( '' === $swi_google_link ) {
								printf(
									'<p class="description">%s</p>',
									esc_html__( 'No domain could be resolved, so the button will not render.', 'summarize-with-ai' )
								);
							} else {
								printf(
									'<p><a href="%1$s" target="_blank" rel="noopener noreferrer"><code>%2$s</code></a></p>',
									esc_url( $swi_google_link ),
									esc_html( $swi_google_link )
								);

								printf(
									'<p class="description">%s</p>',
									esc_html__( 'Open this link to check it resolves to the right site before publishing the button.', 'summarize-with-ai' )
								);
							}
							?>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="swi-google-url"><?php esc_html_e( 'Google URL', 'summarize-with-ai' ); ?></label>
						</th>
						<td>
							<input type="url"
								name="summarizewithai_options[google_source_url]"
								id="swi-google-url"
								value="<?php echo esc_attr( $swi_options['google_source_url'] ); ?>"
								class="regular-text code"
								inputmode="url"
								placeholder="https://www.google.com/preferences/source?q=" />
							<p class="description">
								<?php esc_html_e( 'The domain is URL encoded and appended to this address. Only change it if Google moves the page. Leave empty to restore the default.', 'summarize-with-ai' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Usage -------------------------------------------------------- -->
		<div class="swi-tab-panel" id="swi-panel-usage" data-swi-readonly="1">
			<h2 class="title"><?php esc_html_e( 'Usage', 'summarize-with-ai' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Reference for placing the buttons by hand. Nothing on this tab is saved.', 'summarize-with-ai' ); ?></p>

			<?php
			/**
			 * Print a copyable code snippet.
			 *
			 * @param string $code  Snippet text.
			 * @param string $note  Optional caption above the snippet.
			 * @return void
			 */
			$swi_snippet = static function ( $code, $note = '' ) {
				if ( '' !== $note ) {
					printf( '<p class="swi-snippet-note">%s</p>', esc_html( $note ) );
				}
				?>
				<div class="swi-snippet">
					<code class="swi-snippet-code"><?php echo esc_html( $code ); ?></code>
					<button type="button"
						class="button button-small swi-snippet-copy"
						data-swi-copy="<?php echo esc_attr( $code ); ?>"
						data-swi-copied="<?php esc_attr_e( 'Copied', 'summarize-with-ai' ); ?>"
						data-swi-select="<?php esc_attr_e( 'Press Ctrl+C', 'summarize-with-ai' ); ?>">
						<?php esc_html_e( 'Copy', 'summarize-with-ai' ); ?>
					</button>
				</div>
				<?php
			};

			/**
			 * Print a table of shortcode attributes.
			 *
			 * @param array<int, array{0:string,1:string}> $rows Attribute name and description pairs.
			 * @return void
			 */
			$swi_attr_table = static function ( array $rows ) {
				?>
				<table class="swi-attr-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Attribute', 'summarize-with-ai' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Description', 'summarize-with-ai' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $swi_row ) : ?>
							<tr>
								<td><code class="swi-attr"><?php echo esc_html( $swi_row[0] ); ?></code></td>
								<td><?php echo wp_kses( $swi_row[1], array( 'code' => array(), 'em' => array() ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php
			};

			/**
			 * Wrap a list of values as inline code chips.
			 *
			 * @param string[] $values Values.
			 * @return string HTML.
			 */
			$swi_chips = static function ( array $values ) {
				return implode( ', ', array_map(
					static function ( $value ) {
						return '<code>' . esc_html( $value ) . '</code>';
					},
					$values
				) );
			};
			?>

			<div class="swi-usage-grid">

				<section class="swi-card">
					<header class="swi-card-header">
						<h3><?php esc_html_e( 'Summarize with AI buttons', 'summarize-with-ai' ); ?></h3>
						<p><?php esc_html_e( 'The row of AI services. Add the block named "Summarize with AI" in the editor, or use the shortcode.', 'summarize-with-ai' ); ?></p>
					</header>

					<div class="swi-card-body">
						<?php
						$swi_snippet( '[summarizewithai]' );
						$swi_snippet( "<?php echo do_shortcode( '[summarizewithai]' ); ?>", __( 'In a theme template file:', 'summarize-with-ai' ) );

						$swi_attr_table(
							array(
								array(
									'services',
									sprintf(
										/* translators: %s: comma separated list of available service IDs. */
										esc_html__( 'Service IDs in the order you want them: %s', 'summarize-with-ai' ),
										$swi_chips( array_keys( $swi_services ) )
									),
								),
								array( 'label', esc_html__( 'Text before the buttons. Use an empty value to hide it.', 'summarize-with-ai' ) ),
								array( 'style', $swi_chips( array( 'filled', 'outline', 'minimal' ) ) ),
								array( 'layout', $swi_chips( array( 'inline', 'stacked' ) ) ),
								array( 'show_text', $swi_chips( array( 'yes', 'no' ) ) . ' &mdash; ' . esc_html__( 'show or hide the service names.', 'summarize-with-ai' ) ),
								array( 'copy', $swi_chips( array( 'yes', 'no' ) ) . ' &mdash; ' . esc_html__( 'show or hide the copy-prompt button.', 'summarize-with-ai' ) ),
								array( 'google_source', $swi_chips( array( 'yes', 'no' ) ) . ' &mdash; ' . esc_html__( 'append the Google preferred-source row.', 'summarize-with-ai' ) ),
								array( 'prompt', esc_html__( 'A one-off prompt template for this placement only. Supports the same placeholders.', 'summarize-with-ai' ) ),
								array( 'class', esc_html__( 'Extra CSS classes for the wrapper element.', 'summarize-with-ai' ) ),
							)
						);

						$swi_snippet(
							'[summarizewithai services="claude,chatgpt" style="outline" label="' . __( 'Ask AI about this:', 'summarize-with-ai' ) . '"]',
							__( 'Example:', 'summarize-with-ai' )
						);
						?>
					</div>
				</section>

				<section class="swi-card">
					<header class="swi-card-header">
						<h3><?php esc_html_e( 'Add as a preferred source on Google', 'summarize-with-ai' ); ?></h3>
						<p><?php esc_html_e( 'The Google button on its own. Add the block named "Add as Preferred Source on Google", or use the shortcode. The Google tab also places it automatically.', 'summarize-with-ai' ); ?></p>
					</header>

					<div class="swi-card-body">
						<?php
						$swi_snippet( '[summarizewithai_google_source]' );
						$swi_snippet( "<?php echo do_shortcode( '[summarizewithai_google_source]' ); ?>", __( 'In a theme template file:', 'summarize-with-ai' ) );

						$swi_attr_table(
							array(
								array( 'label', esc_html__( 'Button text. Supports the same placeholders as the prompt.', 'summarize-with-ai' ) ),
								array( 'align', $swi_chips( array( 'left', 'center', 'right' ) ) ),
								array( 'class', esc_html__( 'Extra CSS classes for the wrapper element.', 'summarize-with-ai' ) ),
							)
						);

						$swi_snippet(
							'[summarizewithai_google_source align="center" label="' . __( 'Add us as a preferred source on Google', 'summarize-with-ai' ) . '"]',
							__( 'Example:', 'summarize-with-ai' )
						);
						?>
					</div>
				</section>

				<section class="swi-card">
					<header class="swi-card-header">
						<h3><?php esc_html_e( 'Placeholders', 'summarize-with-ai' ); ?></h3>
						<p><?php esc_html_e( 'Available in the AI prompt, in any label, and in the Google button text.', 'summarize-with-ai' ); ?></p>
					</header>

					<div class="swi-card-body">
						<ul class="swi-chip-list">
							<?php foreach ( array_keys( $swi_placeholder_help ) as $swi_token ) : ?>
								<li><code><?php echo esc_html( $swi_token ); ?></code></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</section>

			</div>
		</div>

		<?php submit_button(); ?>
	</form>
</div>
