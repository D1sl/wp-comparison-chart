<?php
defined( 'ABSPATH' ) || exit;

/**
 * [comparison_chart] shortcode.
 *
 * Renders the chart for the current post (or a specified post ID), applying the
 * global style settings from the settings page as inline CSS variables.
 *
 * Attributes:
 *   id="123"  — optional post ID to render; defaults to the current post.
 */
class SDB_SC_Shortcode {

	public function __construct() {
		add_shortcode( 'comparison_chart', [ $this, 'render' ] );
	}

	public function render( $atts ): string {
		$atts = shortcode_atts( [ 'id' => 0 ], $atts, 'comparison_chart' );

		$post_id = (int) $atts['id'];
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( ! $post_id ) return '';

		$config = sdb_sc_build_config( $post_id );
		if ( ! $config ) {
			return ''; // nothing to compare
		}

		// Ensure front-end assets are loaded (registered in class-assets.php)
		wp_enqueue_style( 'sdb-sc-widget' );
		wp_enqueue_script( 'sdb-sc-widget' );

		// Apply global style settings
		$style = SDB_SC_Settings::get_style();

		if ( ! empty( $style['column_width'] ) ) $config['columnWidth'] = (int) $style['column_width'];
		if ( ! empty( $style['label_width'] ) )  $config['labelWidth']  = (int) $style['label_width'];

		$uid = 'sdb-sc-' . $post_id . '-' . wp_rand( 1000, 9999 );
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
