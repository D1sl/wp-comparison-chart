<?php
defined( 'ABSPATH' ) || exit;

/**
 * Settings Page
 *
 * Settings > Comparison Chart. Manages:
 *   - Which post types the schema/values metaboxes appear on.
 *   - Which taxonomies get term-level schema metaboxes (taxonomy mode).
 *   - Bricks support toggle.
 *   - When Bricks support is OFF: all chart styling controls live here, applied
 *     globally to every shortcode-rendered chart.
 *
 * Option key: sdb_sc_settings (array)
 *   post_types:    string[]
 *   taxonomies:    string[]  — taxonomies that get term-level schema metaboxes
 *   bricks:        bool      — register the Bricks custom element
 *   style:         array     — global style controls used in shortcode mode
 */
class SDB_SC_Settings {

	const OPTION_KEY = 'sdb_sc_settings';
	const PAGE_SLUG  = 'comparison-chart';

	public function __construct() {
		add_action( 'admin_menu',    [ $this, 'register_page' ] );
		add_action( 'admin_init',    [ $this, 'register_settings' ] );
		add_action( 'admin_notices', [ $this, 'saved_notice' ] );
	}

	// ── Menu ──────────────────────────────────────────────────────────────────

	public function register_page(): void {
		add_options_page(
			__( 'Comparison Chart', 'comparison-chart' ),
			__( 'Comparison Chart', 'comparison-chart' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	// ── Settings API ──────────────────────────────────────────────────────────

	public function register_settings(): void {
		register_setting(
			self::OPTION_KEY,
			self::OPTION_KEY,
			[ $this, 'sanitize' ]
		);
	}

	// ── Sanitise ──────────────────────────────────────────────────────────────

	public function sanitize( $input ): array {
		$clean      = [];
		$registered = array_keys( self::get_eligible_post_types() );

		// Post types
		if ( ! empty( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			foreach ( $input['post_types'] as $pt ) {
				if ( in_array( $pt, $registered, true ) ) {
					$clean['post_types'][] = $pt;
				}
			}
		}
		if ( empty( $clean['post_types'] ) ) {
			$clean['post_types'] = [ 'page' ];
		}

		// Taxonomies (taxonomy mode)
		$registered_taxes = array_keys( self::get_eligible_taxonomies() );
		$clean['taxonomies'] = [];
		if ( ! empty( $input['taxonomies'] ) && is_array( $input['taxonomies'] ) ) {
			foreach ( $input['taxonomies'] as $tax ) {
				if ( in_array( $tax, $registered_taxes, true ) ) {
					$clean['taxonomies'][] = $tax;
				}
			}
		}

		// Bricks toggle
		$clean['bricks'] = ! empty( $input['bricks'] );

		// Style controls (used in shortcode mode, harmless otherwise)
		$style_in  = isset( $input['style'] ) && is_array( $input['style'] ) ? $input['style'] : [];
		$defaults  = self::style_defaults();
		$clean['style'] = [];
		foreach ( $defaults as $key => $default ) {
			$val = $style_in[ $key ] ?? $default;
			// Numbers stay numeric; everything else is a sanitized string.
			if ( in_array( $key, [ 'column_width', 'label_width' ], true ) ) {
				$clean['style'][ $key ] = (int) $val;
			} elseif ( $key === 'fullbleed' ) {
				$clean['style'][ $key ] = ! empty( $style_in[ $key ] );
			} else {
				$clean['style'][ $key ] = sanitize_text_field( $val );
			}
		}

		return $clean;
	}

	// ── Render: page ──────────────────────────────────────────────────────────

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$opts             = get_option( self::OPTION_KEY, [] );
		$saved_pts        = self::get_saved_post_types();
		$eligible         = self::get_eligible_post_types();
		$saved_taxes      = self::get_saved_taxonomies();
		$eligible_taxes   = self::get_eligible_taxonomies();
		$bricks_on        = self::bricks_enabled();
		$bricks_active    = class_exists( '\Bricks\Elements' ) || defined( 'BRICKS_VERSION' );
		$style            = self::get_style();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Comparison Chart Settings', 'comparison-chart' ); ?></h1>
			<p class="description" style="margin-bottom:20px;max-width:680px">
				<?php esc_html_e( 'The plugin supports two modes that can coexist on the same site. Post Hierarchy mode: a parent post defines the rows; child posts each become a column. Taxonomy mode: a taxonomy term defines the rows; top-level posts assigned to that term each become a column.', 'comparison-chart' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_KEY ); ?>

				<h2 class="title"><?php esc_html_e( 'Post Types', 'comparison-chart' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Choose which post types show the Comparison Chart editor fields. The Row Schema appears on parent posts; the Values fields appear on child posts.', 'comparison-chart' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enabled on', 'comparison-chart' ); ?></th>
						<td>
							<fieldset>
								<?php foreach ( $eligible as $slug => $label ) :
									$checked  = in_array( $slug, $saved_pts, true ) ? 'checked' : '';
									$disabled = $slug === 'page' ? 'disabled' : '';
									?>
									<label style="display:block;margin-bottom:8px">
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_types][]" value="<?php echo esc_attr( $slug ); ?>" <?php echo esc_attr( $checked ); ?> <?php echo esc_attr( $disabled ); ?> />
										<strong><?php echo esc_html( $label ); ?></strong>
										<code style="font-size:11px;opacity:.7"><?php echo esc_html( $slug ); ?></code>
										<?php if ( $slug === 'page' ) : ?>
											<em style="font-size:11px;color:#888"><?php esc_html_e( '(always enabled)', 'comparison-chart' ); ?></em>
											<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_types][]" value="page" />
										<?php endif; ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Taxonomy Mode', 'comparison-chart' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Choose which taxonomies get a Row Schema metabox on their term edit screens. Posts assigned to those terms (with no post parent) become chart columns automatically.', 'comparison-chart' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enabled taxonomies', 'comparison-chart' ); ?></th>
						<td>
							<?php if ( empty( $eligible_taxes ) ) : ?>
								<p class="description"><?php esc_html_e( 'No public taxonomies found.', 'comparison-chart' ); ?></p>
							<?php else : ?>
								<fieldset>
									<?php foreach ( $eligible_taxes as $slug => $label ) :
										$checked = in_array( $slug, $saved_taxes, true ) ? 'checked' : '';
										?>
										<label style="display:block;margin-bottom:8px">
											<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[taxonomies][]" value="<?php echo esc_attr( $slug ); ?>" <?php echo esc_attr( $checked ); ?> />
											<strong><?php echo esc_html( $label ); ?></strong>
											<code style="font-size:11px;opacity:.7"><?php echo esc_html( $slug ); ?></code>
										</label>
									<?php endforeach; ?>
								</fieldset>
								<p class="description" style="margin-top:8px"><?php esc_html_e( 'Use the shortcode [comparison_chart term="term-slug" taxonomy="taxonomy-slug"] to render a taxonomy-mode chart, or just [comparison_chart] on a post that belongs to one of these terms.', 'comparison-chart' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Placement', 'comparison-chart' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Bricks Builder support', 'comparison-chart' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[bricks]" value="1" <?php checked( $bricks_on ); ?> />
								<?php esc_html_e( 'Register the Comparison Chart element in Bricks', 'comparison-chart' ); ?>
							</label>
							<p class="description">
								<?php if ( $bricks_active ) : ?>
									<?php esc_html_e( 'Bricks is active. When enabled, add the chart via the “Comparison Chart” element (under the Comparison Chart category) and configure its styling in the Bricks panel.', 'comparison-chart' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'Bricks is not active on this site. Leave this off and use the shortcode below.', 'comparison-chart' ); ?>
								<?php endif; ?>
								<br>
								<?php esc_html_e( 'When OFF, use the shortcode and the global styling options below.', 'comparison-chart' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Shortcode', 'comparison-chart' ); ?></th>
						<td>
							<code style="user-select:all;padding:4px 8px;background:#f0f0f1;border-radius:3px">[comparison_chart]</code>
							<p class="description"><?php esc_html_e( 'Place this on a parent or child post to render its chart. The chart automatically detects whether the post is a parent (all columns) or a child (its own column pinned). Used when Bricks support is off; also works as a fallback even when Bricks is on.', 'comparison-chart' ); ?></p>
						</td>
					</tr>
				</table>

				<div id="sdb-sc-style-section" style="<?php echo $bricks_on ? 'opacity:.5' : ''; ?>">
					<h2 class="title"><?php esc_html_e( 'Chart Styling', 'comparison-chart' ); ?></h2>
					<p class="description" style="max-width:680px">
						<?php esc_html_e( 'These options style charts rendered via the shortcode. (When Bricks support is on, styling is controlled per-element in the Bricks panel instead, and these are ignored.)', 'comparison-chart' ); ?>
					</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="sc_color_primary"><?php esc_html_e( 'Primary colour', 'comparison-chart' ); ?></label></th>
							<td>
								<input type="text" id="sc_color_primary" class="sdb-sc-color-field" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style][color_primary]" value="<?php echo esc_attr( $style['color_primary'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Drives all structural colours: column headers, row tints, borders.', 'comparison-chart' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="sc_color_secondary"><?php esc_html_e( 'Secondary colour (optional)', 'comparison-chart' ); ?></label></th>
							<td>
								<input type="text" id="sc_color_secondary" class="sdb-sc-color-field" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style][color_secondary]" value="<?php echo esc_attr( $style['color_secondary'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Pills, rating icons, meter fill, checkmarks, toggle buttons. Falls back to the primary colour if blank.', 'comparison-chart' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="sc_color_ink"><?php esc_html_e( 'Body text colour', 'comparison-chart' ); ?></label></th>
							<td>
								<input type="text" id="sc_color_ink" class="sdb-sc-color-field" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style][color_ink]" value="<?php echo esc_attr( $style['color_ink'] ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="sc_font_body"><?php esc_html_e( 'Body font family', 'comparison-chart' ); ?></label></th>
							<td>
								<input type="text" id="sc_font_body" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style][font_body]" value="<?php echo esc_attr( $style['font_body'] ); ?>" />
								<p class="description"><?php esc_html_e( 'CSS font-family value, e.g. inherit or "Inter", sans-serif', 'comparison-chart' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="sc_font_heading"><?php esc_html_e( 'Heading font family', 'comparison-chart' ); ?></label></th>
							<td>
								<input type="text" id="sc_font_heading" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style][font_heading]" value="<?php echo esc_attr( $style['font_heading'] ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="sc_pill_radius"><?php esc_html_e( 'Pill / button radius', 'comparison-chart' ); ?></label></th>
							<td>
								<input type="text" id="sc_pill_radius" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style][pill_radius]" value="<?php echo esc_attr( $style['pill_radius'] ); ?>" />
								<p class="description"><?php esc_html_e( 'e.g. 20px or 999px', 'comparison-chart' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="sc_column_width"><?php esc_html_e( 'Minimum column width (px)', 'comparison-chart' ); ?></label></th>
							<td>
								<input type="number" id="sc_column_width" min="140" max="320" step="4" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style][column_width]" value="<?php echo esc_attr( (int) $style['column_width'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Columns grow to fill space but never shrink below this width.', 'comparison-chart' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="sc_label_width"><?php esc_html_e( 'Label column width (px)', 'comparison-chart' ); ?></label></th>
							<td>
								<input type="number" id="sc_label_width" min="100" max="260" step="4" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style][label_width]" value="<?php echo esc_attr( (int) $style['label_width'] ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Full-bleed scroll', 'comparison-chart' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style][fullbleed]" value="1" <?php checked( ! empty( $style['fullbleed'] ) ); ?> />
									<?php esc_html_e( 'Let columns scroll edge-to-edge beyond the container', 'comparison-chart' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="sc_gutter"><?php esc_html_e( 'Full-bleed gutter', 'comparison-chart' ); ?></label></th>
							<td>
								<input type="text" id="sc_gutter" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style][gutter]" value="<?php echo esc_attr( $style['gutter'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Space from the viewport edge to the first column, e.g. max(24px, calc((100vw - 1200px) / 2)). Only used when full-bleed is on.', 'comparison-chart' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button( __( 'Save Settings', 'comparison-chart' ) ); ?>
			</form>
		</div>

		<script>
		(function () {
			// Enable the WP color picker on our colour fields
			if ( window.jQuery && jQuery.fn.wpColorPicker ) {
				jQuery('.sdb-sc-color-field').wpColorPicker();
			}
			// Dim the styling section when Bricks support is on
			var bricks = document.querySelector('input[name="<?php echo esc_js( self::OPTION_KEY ); ?>[bricks]"]');
			var styleSection = document.getElementById('sdb-sc-style-section');
			if ( bricks && styleSection ) {
				bricks.addEventListener('change', function () {
					styleSection.style.opacity = bricks.checked ? '.5' : '';
				});
			}
		})();
		</script>
		<?php
	}

	public function saved_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'settings_page_' . self::PAGE_SLUG ) return;
		if ( ! isset( $_GET['settings-updated'] ) ) return;
		echo '<div class="notice notice-success is-dismissible"><p>' .
			esc_html__( 'Comparison Chart settings saved.', 'comparison-chart' ) .
			'</p></div>';
	}

	// Enqueue the WP color picker on our settings page
	public static function enqueue_admin( string $hook ): void {
		if ( $hook !== 'settings_page_' . self::PAGE_SLUG ) return;
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
	}

	// ── Static accessors ──────────────────────────────────────────────────────

	public static function get_saved_post_types(): array {
		$opts = get_option( self::OPTION_KEY, [] );
		$pts  = isset( $opts['post_types'] ) && is_array( $opts['post_types'] )
			? $opts['post_types']
			: [ 'page' ];
		if ( ! in_array( 'page', $pts, true ) ) {
			$pts[] = 'page';
		}
		return $pts;
	}

	public static function bricks_enabled(): bool {
		$opts = get_option( self::OPTION_KEY, [] );
		// Default ON if Bricks is present and no explicit choice saved yet.
		if ( ! isset( $opts['bricks'] ) ) {
			return class_exists( '\Bricks\Elements' ) || defined( 'BRICKS_VERSION' );
		}
		return ! empty( $opts['bricks'] );
	}

	public static function style_defaults(): array {
		return [
			'color_primary'   => '#2563eb', // generic blue
			'color_secondary' => '',
			'color_ink'       => '#1f2933',
			'font_body'       => 'inherit',
			'font_heading'    => 'inherit',
			'pill_radius'     => '20px',
			'column_width'    => 188,
			'label_width'     => 156,
			'fullbleed'       => false,
			'gutter'          => 'max(24px, calc((100vw - 1200px) / 2))',
		];
	}

	public static function get_style(): array {
		$opts  = get_option( self::OPTION_KEY, [] );
		$style = isset( $opts['style'] ) && is_array( $opts['style'] ) ? $opts['style'] : [];
		return wp_parse_args( $style, self::style_defaults() );
	}

	public static function get_eligible_post_types(): array {
		$types  = get_post_types( [ 'public' => true ], 'objects' );
		$result = [];
		$exclude = [ 'attachment', 'bricks_element' ];
		foreach ( $types as $slug => $obj ) {
			if ( in_array( $slug, $exclude, true ) ) continue;
			$result[ $slug ] = $obj->labels->singular_name ?: $slug;
		}
		uksort( $result, function ( $a, $b ) {
			if ( $a === 'page' ) return -1;
			if ( $b === 'page' ) return 1;
			return strcmp( $a, $b );
		} );
		return $result;
	}

	public static function get_saved_taxonomies(): array {
		$opts = get_option( self::OPTION_KEY, [] );
		return isset( $opts['taxonomies'] ) && is_array( $opts['taxonomies'] )
			? $opts['taxonomies']
			: [];
	}

	public static function get_eligible_taxonomies(): array {
		$taxes   = get_taxonomies( [ 'public' => true ], 'objects' );
		$result  = [];
		$exclude = [ 'post_format', 'nav_menu', 'link_category', 'wp_theme', 'wp_template_part_area' ];
		foreach ( $taxes as $slug => $obj ) {
			if ( in_array( $slug, $exclude, true ) ) continue;
			$result[ $slug ] = $obj->labels->singular_name ?: $slug;
		}
		ksort( $result );
		return $result;
	}
}

new SDB_SC_Settings();
add_action( 'admin_enqueue_scripts', [ 'SDB_SC_Settings', 'enqueue_admin' ] );
