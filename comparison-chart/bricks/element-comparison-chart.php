<?php
defined( 'ABSPATH' ) || exit;

/**
 * Bricks custom element: Comparison Chart
 */
class Bricks_Element_Sdb_Service_Comparison extends \Bricks\Element {

    public $category     = 'Comparison Chart';
    public $name         = 'sdb-service-comparison';
    public $icon         = 'ti-layout-list-thumb-alt';
    public $css_selector = '.sdb-sc-root';
    public $scripts      = [];

    public function get_label(): string {
        return esc_html__( 'Comparison Chart', 'sdb-sc' );
    }

    public function set_controls(): void {

        // ── Layout ────────────────────────────────────────────────────────────
        $this->controls['_section_layout'] = [
            'tab'   => 'content',
            'label' => esc_html__( 'Layout', 'sdb-sc' ),
            'type'  => 'separator',
        ];

        $this->controls['column_width'] = [
            'tab'         => 'content',
            'label'       => esc_html__( 'Column Width (px)', 'sdb-sc' ),
            'type'        => 'number',
            'min'         => 140,
            'max'         => 320,
            'step'        => 4,
            'default'     => 188,
            'description' => esc_html__( 'Width of each comparison column.', 'sdb-sc' ),
        ];

        $this->controls['label_width'] = [
            'tab'     => 'content',
            'label'   => esc_html__( 'Label Column Width (px)', 'sdb-sc' ),
            'type'    => 'number',
            'min'     => 100,
            'max'     => 260,
            'step'    => 4,
            'default' => 156,
        ];

        // ── Typography ────────────────────────────────────────────────────────
        $this->controls['_section_type'] = [
            'tab'   => 'content',
            'label' => esc_html__( 'Typography', 'sdb-sc' ),
            'type'  => 'separator',
        ];

        $this->controls['font_body'] = [
            'tab'         => 'content',
            'label'       => esc_html__( 'Body Font Family', 'sdb-sc' ),
            'type'        => 'text',
            'default'     => 'var(--ff-body, var(--font-primary, sans-serif))',
            'description' => esc_html__( 'CSS font-family value. Use ACSS tokens e.g. var(--ff-body)', 'sdb-sc' ),
        ];

        $this->controls['font_heading'] = [
            'tab'     => 'content',
            'label'   => esc_html__( 'Display/Heading Font Family', 'sdb-sc' ),
            'type'    => 'text',
            'default' => 'var(--ff-heading, var(--font-heading, serif))',
        ];

        // ── Colours ───────────────────────────────────────────────────────────
        $this->controls['_section_colour'] = [
            'tab'   => 'content',
            'label' => esc_html__( 'Colours', 'sdb-sc' ),
            'type'  => 'separator',
        ];

        $this->controls['color_primary'] = [
            'tab'         => 'content',
            'label'       => esc_html__( 'Primary colour', 'sdb-sc' ),
            'type'        => 'color',
            'default'     => [ 'raw' => 'var(--color-primary, #2563eb)' ],
            'description' => esc_html__( 'Drives all structural colours: column headers, row tints, borders. Everything derives from this.', 'sdb-sc' ),
        ];

        $this->controls['color_secondary'] = [
            'tab'         => 'content',
            'label'       => esc_html__( 'Secondary colour (optional)', 'sdb-sc' ),
            'type'        => 'color',
            'description' => esc_html__( 'Used for pills, rating icons, meter fill, bool checkmarks, and toggle buttons. Falls back to the primary colour if left blank.', 'sdb-sc' ),
        ];

        $this->controls['color_ink'] = [
            'tab'         => 'content',
            'label'       => esc_html__( 'Body text colour', 'sdb-sc' ),
            'type'        => 'color',
            'default'     => [ 'raw' => 'var(--color-primary-dark, #1f2933)' ],
            'description' => esc_html__( 'Row label and cell text colour.', 'sdb-sc' ),
        ];

        // ── Border radius ─────────────────────────────────────────────────────
        $this->controls['_section_radius'] = [
            'tab'   => 'content',
            'label' => esc_html__( 'Border Radius', 'sdb-sc' ),
            'type'  => 'separator',
        ];

        $this->controls['pill_radius'] = [
            'tab'         => 'content',
            'label'       => esc_html__( 'Pill / Button Radius', 'sdb-sc' ),
            'type'        => 'text',
            'default'     => 'var(--radius-full, 20px)',
            'description' => esc_html__( 'Border-radius for pill tags and toggle buttons. Use an ACSS token or px value.', 'sdb-sc' ),
        ];

        // ── Full-bleed scroll ─────────────────────────────────────────────────
        $this->controls['_section_fullbleed'] = [
            'tab'   => 'content',
            'label' => esc_html__( 'Full-Bleed Scroll', 'sdb-sc' ),
            'type'  => 'separator',
        ];

        $this->controls['fullbleed'] = [
            'tab'         => 'content',
            'label'       => esc_html__( 'Enable full-bleed', 'sdb-sc' ),
            'type'        => 'checkbox',
            'default'     => false,
            'description' => esc_html__( 'Breaks the scroll container out of the Bricks section/container so columns can overflow edge-to-edge. Re-pads with the gutter value below so content stays aligned to your grid.', 'sdb-sc' ),
        ];

        $this->controls['gutter'] = [
            'tab'         => 'content',
            'label'       => esc_html__( 'Gutter (px or CSS value)', 'sdb-sc' ),
            'type'        => 'text',
            'default'     => 'max(24px, calc((100vw - 1400px) / 2))',
            'description' => esc_html__( 'Space between the viewport edge and the first column. Match your site max-width container. Only used when full-bleed is on.', 'sdb-sc' ),
            'required'    => [ 'fullbleed', '=', true ],
        ];
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render(): void {
        $current_id = get_the_ID();
        if ( ! $current_id ) {
            $this->render_placeholder_notice( 'No page context available.' );
            return;
        }

        $config = sdb_sc_build_config( $current_id );
        if ( ! $config ) {
            $this->render_placeholder_notice(
                'No comparison data found. Define rows on the parent post and add child posts for each item to compare.'
            );
            return;
        }

        $settings = $this->settings;

        if ( ! empty( $settings['column_width'] ) ) {
            $config['columnWidth'] = (int) $settings['column_width'];
        }
        if ( ! empty( $settings['label_width'] ) ) {
            $config['labelWidth'] = (int) $settings['label_width'];
        }

        $css_vars  = $this->build_css_vars( $settings );
        $fullbleed = ! empty( $settings['fullbleed'] );
        $uid       = 'sdb-sc-' . esc_attr( $this->id );
        $config['mountSelector'] = '#' . $uid;

        $classes = 'sdb-sc-root';
        if ( $fullbleed ) {
            $classes .= ' sdb-sc-root--fullbleed';
        }

        if ( $fullbleed && ! empty( $settings['gutter'] ) ) {
            $gutter_val = sanitize_text_field( $settings['gutter'] );
            $css_vars   = '--sc-gutter:' . $gutter_val . ( $css_vars ? ';' . $css_vars : '' );
        }

        printf(
            '<div id="%s" class="%s"%s></div>',
            esc_attr( $uid ),
            esc_attr( $classes ),
            $css_vars ? ' style="' . esc_attr( $css_vars ) . '"' : ''
        );
        printf(
            '<script type="application/json" id="%s-cfg">%s</script>',
            esc_attr( $uid ),
            wp_json_encode( $config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
        );
    }

    // ── Build inline CSS variables ────────────────────────────────────────────

    private function build_css_vars( array $settings ): string {
        // Simple colour/text controls that map 1:1 to a CSS var
        $map = [
            'color_primary'   => '--sc-primary',
            'color_secondary' => '--sc-secondary',
            'color_ink'       => '--sc-ink',
            'font_body'       => '--sc-font-body',
            'font_heading'    => '--sc-font-heading',
            'pill_radius'     => '--sc-radius-pill',
        ];

        $vars = [];
        foreach ( $map as $control => $prop ) {
            $val = $settings[ $control ] ?? null;
            if ( ! $val ) continue;
            if ( is_array( $val ) && isset( $val['raw'] ) ) {
                $val = $val['raw'];
            }
            if ( is_string( $val ) && $val !== '' ) {
                $vars[] = $prop . ':' . $val;
            }
        }

        return implode( ';', $vars );
    }

    // ── Placeholder ───────────────────────────────────────────────────────────

    private function render_placeholder_notice( string $message ): void {
        printf(
            '<div style="padding:24px;background:rgba(0,0,0,.04);border:2px dashed rgba(0,0,0,.12);border-radius:8px;font-size:13px;color:#666;text-align:center">
                <strong>Comparison Chart</strong><br>%s
            </div>',
            esc_html( $message )
        );
    }
}
