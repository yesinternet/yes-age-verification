<?php
/**
 * Admin settings page template — uses WordPress native UI components.
 *
 * @package YES_Age_Verification
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$saved = get_option( 'yes_age_verification_options' );

if ( false === $saved ) {
	$options = YES_Age_Verification::defaults_for_site();
} else {
	$options = wp_parse_args(
		(array) $saved,
		array(
			'enabled'        => 1,
			'cookie_days'    => 30,
			'overlay_color'  => 'rgba(0,0,0,0.78)',
			'logo_id'        => 0,
			'logo_width'     => '',
			'title'          => '',
			'body_text'      => '',
			'question_text'  => '',
			'yes_text'       => '',
			'no_text'        => '',
			'footer_text'    => '',
			'redirect_url'         => '',
			'mode'                 => 'exclusion',
			'exclude_urls'         => '',
			'target_pages'         => array(),
			'target_categories'    => array(),
			'target_post_types'    => array(),
			'target_taxonomies'    => array(),
			'target_url_regex'     => '',
			'target_wc_categories' => array(),
		)
	);
}

$select_class = class_exists( 'WooCommerce' ) ? 'wc-enhanced-select' : 'large-text';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Age Verification', 'yes-age-verification' ); ?></h1>

	<?php settings_errors( 'yes_age_verification_options' ); ?>

	<form method="post" action="options.php" novalidate>
		<?php settings_fields( 'yes_age_verification_group' ); ?>

		<!-- ── General ──────────────────────────────────────── -->
		<h2><?php esc_html_e( 'General', 'yes-age-verification' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Enable popup', 'yes-age-verification' ); ?>
				</th>
				<td>
					<label for="yes-age-verification__enabled">
						<input
							type="checkbox"
							id="yes-age-verification__enabled"
							name="yes_age_verification_options[enabled]"
							value="1"
							<?php checked( 1, $options['enabled'] ); ?>>
						<?php esc_html_e( 'Show the age verification popup to visitors', 'yes-age-verification' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="yes-age-verification__overlay-color">
						<?php esc_html_e( 'Overlay colour', 'yes-age-verification' ); ?>
					</label>
				</th>
				<td>
					<input
						type="text"
						id="yes-age-verification__overlay-color"
						name="yes_age_verification_options[overlay_color]"
						value="<?php echo esc_attr( $options['overlay_color'] ); ?>"
						class="regular-text"
						placeholder="rgba(0,0,0,0.78)">
					<p class="description">
						<?php esc_html_e( 'Any valid CSS colour — hex, rgb, rgba. Example: rgba(0,0,0,0.78)', 'yes-age-verification' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="yes-age-verification__cookie-days">
						<?php esc_html_e( 'Cookie duration (days)', 'yes-age-verification' ); ?>
					</label>
				</th>
				<td>
					<input
						type="number"
						id="yes-age-verification__cookie-days"
						name="yes_age_verification_options[cookie_days]"
						value="<?php echo esc_attr( $options['cookie_days'] ); ?>"
						min="1"
						max="365"
						class="small-text">
					<p class="description">
						<?php esc_html_e( 'How many days to remember a visitor who clicked Yes.', 'yes-age-verification' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="yes-age-verification__redirect-url">
						<?php esc_html_e( 'Redirect URL (when visitor clicks No)', 'yes-age-verification' ); ?>
					</label>
				</th>
				<td>
					<input
						type="url"
						id="yes-age-verification__redirect-url"
						name="yes_age_verification_options[redirect_url]"
						value="<?php echo esc_attr( $options['redirect_url'] ); ?>"
						class="regular-text"
						placeholder="https://www.google.com">
				</td>
			</tr>
		</table>

		<!-- ── Visibility rules ──────────────────────────────── -->
		<h2><?php esc_html_e( 'Visibility Rules', 'yes-age-verification' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Mode', 'yes-age-verification' ); ?>
				</th>
				<td>
					<fieldset>
						<label>
							<input
								type="radio"
								name="yes_age_verification_options[mode]"
								value="exclusion"
								<?php checked( 'exclusion', $options['mode'] ); ?>>
							<strong><?php esc_html_e( 'Exclusion', 'yes-age-verification' ); ?></strong>
							&mdash; <?php esc_html_e( 'Show on all pages except the ones listed below.', 'yes-age-verification' ); ?>
						</label>
						<br>
						<label>
							<input
								type="radio"
								name="yes_age_verification_options[mode]"
								value="inclusion"
								<?php checked( 'inclusion', $options['mode'] ); ?>>
							<strong><?php esc_html_e( 'Inclusion', 'yes-age-verification' ); ?></strong>
							&mdash; <?php esc_html_e( 'Show only on the pages listed below.', 'yes-age-verification' ); ?>
						</label>
					</fieldset>
					<p class="description">
						<?php esc_html_e( 'The URL exceptions field below always hides the popup regardless of mode. All other fields are interpreted according to the mode selected here.', 'yes-age-verification' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="yes-age-verification__exclude-urls">
						<?php esc_html_e( 'URL exceptions (always hidden)', 'yes-age-verification' ); ?>
					</label>
				</th>
				<td>
					<textarea
						id="yes-age-verification__exclude-urls"
						name="yes_age_verification_options[exclude_urls]"
						rows="4"
						class="large-text"><?php echo esc_textarea( $options['exclude_urls'] ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Full URLs where the popup is always hidden, one per line — e.g. your Privacy Policy or Terms page. Applied regardless of mode.', 'yes-age-verification' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="yes-age-verification__target-pages">
						<?php esc_html_e( 'Pages', 'yes-age-verification' ); ?>
					</label>
				</th>
				<td>
					<?php
					$all_pages      = get_pages( array( 'sort_column' => 'post_title', 'sort_order' => 'ASC' ) );
					$selected_pages = array_map( 'absint', (array) $options['target_pages'] );
					?>
					<select
						id="yes-age-verification__target-pages"
						name="yes_age_verification_options[target_pages][]"
						class="<?php echo esc_attr( $select_class ); ?>"
						multiple="multiple"
						style="min-width:350px">
						<?php foreach ( $all_pages as $page ) : ?>
							<option
								value="<?php echo esc_attr( $page->ID ); ?>"
								<?php selected( in_array( $page->ID, $selected_pages, true ) ); ?>>
								<?php echo esc_html( $page->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="yes-age-verification__target-categories">
						<?php esc_html_e( 'Post categories', 'yes-age-verification' ); ?>
					</label>
				</th>
				<td>
					<?php
					$all_categories      = get_categories( array( 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );
					$selected_categories = array_map( 'absint', (array) $options['target_categories'] );
					?>
					<select
						id="yes-age-verification__target-categories"
						name="yes_age_verification_options[target_categories][]"
						class="<?php echo esc_attr( $select_class ); ?>"
						multiple="multiple"
						style="min-width:350px">
						<?php foreach ( $all_categories as $category ) : ?>
							<option
								value="<?php echo esc_attr( $category->term_id ); ?>"
								<?php selected( in_array( $category->term_id, $selected_categories, true ) ); ?>>
								<?php echo esc_html( $category->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Includes category archives and individual posts. Subcategories are matched automatically.', 'yes-age-verification' ); ?>
					</p>
				</td>
			</tr>
			<?php
			$custom_post_types    = get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' );
			if ( $custom_post_types ) :
				$selected_post_types = (array) $options['target_post_types'];
			?>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Custom post types', 'yes-age-verification' ); ?>
				</th>
				<td>
					<fieldset>
						<?php foreach ( $custom_post_types as $post_type ) : ?>
							<label style="display:inline-block;margin-right:16px;margin-bottom:6px">
								<input
									type="checkbox"
									name="yes_age_verification_options[target_post_types][]"
									value="<?php echo esc_attr( $post_type->name ); ?>"
									<?php checked( in_array( $post_type->name, $selected_post_types, true ) ); ?>>
								<?php echo esc_html( $post_type->labels->name ); ?>
								<span style="color:#999;font-size:.85em">(<?php echo esc_html( $post_type->name ); ?>)</span>
							</label>
						<?php endforeach; ?>
					</fieldset>
					<p class="description">
						<?php esc_html_e( 'Includes single posts and archive pages for the selected post types.', 'yes-age-verification' ); ?>
					</p>
				</td>
			</tr>
			<?php endif; ?>
			<?php
			$woocommerce_taxonomies = class_exists( 'WooCommerce' ) ? array( 'product_cat', 'product_tag' ) : array();
			$custom_taxonomies      = get_taxonomies( array( 'public' => true, '_builtin' => false ), 'objects' );
			$custom_taxonomies      = array_diff_key( $custom_taxonomies, array_flip( $woocommerce_taxonomies ) );
			if ( $custom_taxonomies ) :
				$selected_taxonomy_pairs = (array) $options['target_taxonomies'];
			?>
			<?php foreach ( $custom_taxonomies as $taxonomy ) :
				$taxonomy_terms = get_terms( array( 'taxonomy' => $taxonomy->name, 'hide_empty' => false ) );
				if ( empty( $taxonomy_terms ) || is_wp_error( $taxonomy_terms ) ) continue;
				$selected_taxonomy_terms = array();
				foreach ( $selected_taxonomy_pairs as $pair ) {
					list( $pair_taxonomy, $pair_term_id ) = explode( ':', $pair, 2 );
					if ( $pair_taxonomy === $taxonomy->name ) {
						$selected_taxonomy_terms[] = (int) $pair_term_id;
					}
				}
			?>
			<tr>
				<th scope="row">
					<label for="yes-age-verification__taxonomy-<?php echo esc_attr( $taxonomy->name ); ?>">
						<?php echo esc_html( $taxonomy->labels->name ); ?>
					</label>
				</th>
				<td>
					<select
						id="yes-age-verification__taxonomy-<?php echo esc_attr( $taxonomy->name ); ?>"
						name="yes_age_verification_options[target_taxonomies][]"
						class="<?php echo esc_attr( $select_class ); ?>"
						multiple="multiple"
						style="min-width:350px">
						<?php foreach ( $taxonomy_terms as $term ) :
							$pair_value = $taxonomy->name . ':' . $term->term_id;
						?>
							<option
								value="<?php echo esc_attr( $pair_value ); ?>"
								<?php selected( in_array( $term->term_id, $selected_taxonomy_terms, true ) ); ?>>
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php
						printf(
							/* translators: %s: taxonomy name */
							esc_html__( 'Includes %s archives and individual posts. Child terms are matched automatically.', 'yes-age-verification' ),
							'<em>' . esc_html( strtolower( $taxonomy->labels->name ) ) . '</em>'
						);
						?>
					</p>
				</td>
			</tr>
			<?php endforeach; ?>
			<?php endif; ?>
			<tr>
				<th scope="row">
					<label for="yes-age-verification__target-url-regex">
						<?php esc_html_e( 'URL path pattern (regex)', 'yes-age-verification' ); ?>
					</label>
				</th>
				<td>
					<textarea
						id="yes-age-verification__target-url-regex"
						name="yes_age_verification_options[target_url_regex]"
						rows="4"
						class="large-text"
						placeholder="^/sample-category/(.*)"><?php echo esc_textarea( $options['target_url_regex'] ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'One pattern per line, matched against the URL path and query string. No delimiters needed. Invalid patterns are removed on save.', 'yes-age-verification' ); ?>
					</p>
				</td>
			</tr>
			<?php if ( class_exists( 'WooCommerce' ) ) : ?>
			<tr>
				<th scope="row">
					<label for="yes-age-verification__woocommerce-categories">
						<?php esc_html_e( 'Product categories', 'yes-age-verification' ); ?>
					</label>
				</th>
				<td>
					<?php
					$woocommerce_terms                   = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
					$selected_woocommerce_categories     = array_map( 'absint', (array) $options['target_wc_categories'] );
					?>
					<select
						id="yes-age-verification__woocommerce-categories"
						name="yes_age_verification_options[target_wc_categories][]"
						class="wc-enhanced-select"
						multiple="multiple"
						style="min-width:350px">
						<?php foreach ( $woocommerce_terms as $term ) : ?>
							<option
								value="<?php echo esc_attr( $term->term_id ); ?>"
								<?php selected( in_array( $term->term_id, $selected_woocommerce_categories, true ) ); ?>>
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Includes category archives and individual products. Subcategories are matched automatically.', 'yes-age-verification' ); ?>
					</p>
				</td>
			</tr>
			<?php endif; ?>
		</table>

		<!-- ── Popup content ─────────────────────────────────── -->
		<h2><?php esc_html_e( 'Popup Content', 'yes-age-verification' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Logo', 'yes-age-verification' ); ?>
				</th>
				<td>
					<input
						type="hidden"
						id="yes-age-verification__logo-id"
						name="yes_age_verification_options[logo_id]"
						value="<?php echo absint( $options['logo_id'] ); ?>">
					<button type="button" class="button" id="yes-age-verification__logo-button">
						<?php esc_html_e( 'Choose image', 'yes-age-verification' ); ?>
					</button>
					<?php if ( ! empty( $options['logo_id'] ) ) : ?>
						<button type="button" class="button yes-age-verification__logo-remove">
							<?php esc_html_e( 'Remove', 'yes-age-verification' ); ?>
						</button>
						<br><br>
						<div id="yes-age-verification__logo-preview" style="margin-top:8px">
							<?php echo wp_get_attachment_image( absint( $options['logo_id'] ), 'thumbnail', false, array( 'style' => 'max-height:70px;width:auto;border:1px solid #dcdcde;border-radius:3px;padding:4px;background:#fafafa' ) ); ?>
						</div>
					<?php else : ?>
						<div id="yes-age-verification__logo-preview" style="display:none;margin-top:8px"></div>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="yes-age-verification__logo-width">
						<?php esc_html_e( 'Logo width (px)', 'yes-age-verification' ); ?>
					</label>
				</th>
				<td>
					<input
						type="number"
						id="yes-age-verification__logo-width"
						name="yes_age_verification_options[logo_width]"
						value="<?php echo esc_attr( $options['logo_width'] ); ?>"
						min="1"
						max="1000"
						class="small-text"
						placeholder="—">
					<p class="description">
						<?php esc_html_e( 'Maximum display width in pixels. Leave blank to use the natural image size.', 'yes-age-verification' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="yes-age-verification__field-title">
						<?php esc_html_e( 'Title', 'yes-age-verification' ); ?>
					</label>
				</th>
				<td>
					<input
						type="text"
						id="yes-age-verification__field-title"
						name="yes_age_verification_options[title]"
						value="<?php echo esc_attr( $options['title'] ); ?>"
						class="large-text">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Body text', 'yes-age-verification' ); ?>
				</th>
				<td>
					<?php
					wp_editor(
						$options['body_text'],
						'yes_age_verification_body_text',
						array(
							'textarea_name' => 'yes_age_verification_options[body_text]',
							'media_buttons' => false,
							'teeny'         => true,
							'editor_height' => 150,
							'tinymce'       => array(
								'toolbar1' => 'bold,italic,underline,link,unlink,removeformat',
								'toolbar2' => '',
							),
						)
					);
					?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="yes-age-verification__field-question">
						<?php esc_html_e( 'Question text', 'yes-age-verification' ); ?>
					</label>
				</th>
				<td>
					<input
						type="text"
						id="yes-age-verification__field-question"
						name="yes_age_verification_options[question_text]"
						value="<?php echo esc_attr( $options['question_text'] ); ?>"
						class="large-text">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Button labels', 'yes-age-verification' ); ?>
				</th>
				<td>
					<label for="yes-age-verification__field-button-yes"><?php esc_html_e( '"Yes" button', 'yes-age-verification' ); ?></label><br>
					<input
						type="text"
						id="yes-age-verification__field-button-yes"
						name="yes_age_verification_options[yes_text]"
						value="<?php echo esc_attr( $options['yes_text'] ); ?>"
						class="regular-text">
					<br><br>
					<label for="yes-age-verification__field-button-no"><?php esc_html_e( '"No" button', 'yes-age-verification' ); ?></label><br>
					<input
						type="text"
						id="yes-age-verification__field-button-no"
						name="yes_age_verification_options[no_text]"
						value="<?php echo esc_attr( $options['no_text'] ); ?>"
						class="regular-text">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Footer text', 'yes-age-verification' ); ?>
				</th>
				<td>
					<?php
					wp_editor(
						$options['footer_text'],
						'yes_age_verification_footer_text',
						array(
							'textarea_name' => 'yes_age_verification_options[footer_text]',
							'media_buttons' => false,
							'teeny'         => true,
							'editor_height' => 100,
							'tinymce'       => array(
								'toolbar1' => 'bold,italic,underline,link,unlink,removeformat',
								'toolbar2' => '',
							),
						)
					);
					?>
					<p class="description">
						<?php esc_html_e( 'Leave blank to hide.', 'yes-age-verification' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( esc_html__( 'Save settings', 'yes-age-verification' ) ); ?>
	</form>
</div>
