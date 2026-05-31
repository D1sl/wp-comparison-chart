<?php
defined( 'ABSPATH' ) || exit;

/**
 * [comparison_chart] shortcode.
 *
 * Renders the chart for the current post (or a specified post ID), applying the
 * global style settings from the settings page as inline CSS variables.
 *
 * Attributes:
 *   id="123"                              — force post-hierarchy mode for this post ID.
 *   term="term-slug" taxonomy="tax-slug"  — force taxonomy mode for a specific term.
 *
 * Auto-detection (no attributes):
 *   1. If the current post has a post parent or children → post-hierarchy mode.
 *   2. If the current post belongs to a term (in an enabled taxonomy) that has
 *      a schema → taxonomy mode (first matching term used; add term= to be explicit
 *      when multiple terms match).
 */
class SDB_SC_Shortcode {

	public function __construct() {
		add_shortcode( 'comparison_chart', [ $this, 'render' ] );
	}

	public function render( $atts ): string {
		$atts = shortcode_atts(
			[
				'id'       => 0,
				'term'     => '',
				'taxonomy' => '',
			],
			$atts,
			'comparison_chart'
		);

		// ── Explicit taxonomy mode ────────────────────────────────────────────
		if ( $atts['term'] !== '' ) {
			$taxonomy = sanitize_key( $atts['taxonomy'] );
			$term_slug = sanitize_text_field( $atts['term'] );

			// If no taxonomy given, search across all enabled taxonomies.
			if ( ! $taxonomy ) {
				$enabled_taxes = SDB_SC_Settings::get_saved_taxonomies();
				foreach ( $enabled_taxes as $tax ) {
					$candidate = get_term_by( 'slug', $term_slug, $tax );
					if ( $candidate ) {
						$taxonomy = $tax;
						$term     = $candidate;
						break;
					}
				}
			} else {
				$term = get_term_by( 'slug', $term_slug, $taxonomy );
			}

			if ( empty( $term ) || is_wp_error( $term ) ) return '';

			$current_id = (int) ( $atts['id'] ?: get_the_ID() );
			$config     = sdb_sc_build_config_for_term( $term->term_id, $taxonomy, $current_id );
			if ( ! $config ) return '';

			return $this->build_html( $config );
		}

		// ── Explicit post-hierarchy mode (id= given) ──────────────────────────
		$post_id = (int) $atts['id'];
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( ! $post_id ) return '';

		// ── Auto-detection ────────────────────────────────────────────────────
		// Try post-hierarchy first; fall through to taxonomy mode.
		$config = sdb_sc_build_config( $post_id );

		if ( ! $config ) {
			// Taxonomy mode: use first term that has a schema.
			$terms_ws = sdb_sc_get_terms_with_schema_for_post( $post_id );
			if ( ! empty( $terms_ws ) ) {
				$term   = $terms_ws[0];
				$config = sdb_sc_build_config_for_term( $term->term_id, $term->taxonomy, $post_id );
			}
		}

		if ( ! $config ) {
			return ''; // nothing to compare
		}

		return $this->build_html( $config );
	}

	// ── Shared HTML output ────────────────────────────────────────────────────

	private function build_html( array $config ): string {
		// Ensure front-end assets are loaded (registered in class-assets.php)
		wp_enqueue_style( 'sdb-sc-widget' );
		wp_enqueue_script( 'sdb-sc-widget' );

		// Apply global style settings
		$style = SDB_SC_Settings::get_style();

		if ( ! empty( $style['column_width'] ) ) $config['columnWidth'] = (int) $style['column_width'];
		if ( ! empty( $style['label_width'] ) )  $config['labelWidth']  = (int) $style['label_width'];

		$uid = 'sdb-sc-' . wp_rand( 1000, 9999 );
		$config['mountSelector'] = '#' . $uid;

		$classes  = 'sdb-sc-root';
		$css_vars = $this->build_css_vars( $style );

		if ( ! empty( $style['fullbleed'] ) ) {
			$classes .= ' sdb-sc-root--fullbleed';
			if ( ! empty( $style['gutter'] ) ) {
				$css_vars = '--sc-gutter:' . $style['gutter'] . ( $css_vars ? ';' . $css_vars : '' );
			}
		}

		$html  = sprintf(
			'<div id="%s" class="%s"%s></div>',
			esc_attr( $uid ),
			esc_attr( $classes ),
			$css_vars ? ' style="' . esc_attr( $css_vars ) . '"' : ''
		);
		$html .= sprintf(
			'<script type="application/json" id="%s-cfg">%s</script>',
			esc_attr( $uid ),
			wp_json_encode( $config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);

		return $html;
	}

	private function build_css_vars( array $style ): string {
		$map = [
			'color_primary'   => '--sc-primary',
			'color_secondary' => '--sc-secondary',
			'color_ink'       => '--sc-ink',
			'font_body'       => '--sc-font-body',
			'font_heading'    => '--sc-font-heading',
			'pill_radius'     => '--sc-radius-pill',
		];
		$vars = [];
		foreach ( $map as $key => $prop ) {
			$val = $style[ $key ] ?? '';
			if ( is_string( $val ) && $val !== '' ) {
				$vars[] = $prop . ':' . $val;
			}
		}
		return implode( ';', $vars );
	}
}

new SDB_SC_Shortcode();
