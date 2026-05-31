<?php
defined( 'ABSPATH' ) || exit;

/**
 * Values Metabox
 * Renders on child pages only. Reads the parent's row schema and dynamically
 * outputs the correct input field per row type. Fields are shown/hidden based
 * on the current parent's schema — no stale fields for removed rows.
 */
class SDB_SC_Values_Metabox {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'register' ], 10, 2 );
        add_action( 'save_post',      [ $this, 'save' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    // ── Register ──────────────────────────────────────────────────────────────

    public function register( string $post_type, $post = null ): void {
        $post_types = SDB_SC_Settings::get_saved_post_types();
        if ( ! in_array( $post_type, $post_types, true ) ) return;

        // Resolve the post object — fall back to the global if not passed.
        if ( ! $post instanceof WP_Post ) {
            $post = get_post();
        }
        if ( ! $post instanceof WP_Post ) return;

        // Header + Values boxes appear ONLY on child posts (those with a parent).
        // Parent posts manage schema; orphan posts get nothing.
        if ( sdb_sc_post_role( $post->ID ) !== 'child' ) return;

        add_meta_box(
            'sdb_sc_service_header',
            'Comparison Chart — Item Info',
            [ $this, 'render_header' ],
            $post_type,
            'side',
            'high'
        );
        add_meta_box(
            'sdb_sc_values',
            '📊 Comparison Chart — Values',
            [ $this, 'render_values' ],
            $post_type,
            'normal',
            'high'
        );
    }

    // ── Enqueue ───────────────────────────────────────────────────────────────

    public function enqueue( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
        $screen = get_current_screen();
        if ( ! $screen ) return;
        $post_types = SDB_SC_Settings::get_saved_post_types();
        if ( ! in_array( $screen->post_type, $post_types, true ) ) return;

        // Pass schema to JS so the values metabox can react to schema changes
        global $post;
        if ( ! $post ) return;
        $schema_src = sdb_sc_values_schema_source( $post->ID );
        if ( ! $schema_src ) return; // parent post — no values box

        $schema = sdb_sc_get_schema( $schema_src );

        wp_enqueue_script(
            'sdb-sc-values-admin',
            SDB_SC_URL . 'assets/admin-values.js',
            [ 'jquery' ],
            SDB_SC_VERSION,
            true
        );
        wp_localize_script( 'sdb-sc-values-admin', 'SDB_SC_Schema', [
            'schema'    => $schema,
            'parent_id' => $schema_src,
        ] );
    }

    // ── Render: header sidebar ────────────────────────────────────────────────

    public function render_header( WP_Post $post ): void {
        $schema_src = sdb_sc_values_schema_source( $post->ID );
        if ( ! $schema_src ) {
            echo '<p style="color:#888;font-style:italic">Only shown on child posts.</p>';
            return;
        }

        wp_nonce_field( 'sdb_sc_header_save', 'sdb_sc_header_nonce' );
        $meta  = get_post_meta( $post->ID, '_sdb_sc_header', true );
        $meta  = is_array( $meta ) ? $meta : [];
        $name  = esc_attr( $meta['name']  ?? '' );
        $price = esc_attr( $meta['price'] ?? '' );
        $badge = esc_attr( $meta['badge'] ?? '' );
        ?>
        <table class="form-table" style="margin:0">
            <tr>
                <th style="padding:4px 0"><label for="sdb_svc_name">Display Name</label></th>
                <td style="padding:4px 0">
                    <input type="text" id="sdb_svc_name" name="sdb_svc_name" value="<?php echo $name; ?>" class="widefat" placeholder="Falls back to post title" />
                </td>
            </tr>
            <tr>
                <th style="padding:4px 0"><label for="sdb_svc_price">Price / Range</label></th>
                <td style="padding:4px 0">
                    <input type="text" id="sdb_svc_price" name="sdb_svc_price" value="<?php echo $price; ?>" class="widefat" placeholder="e.g. $49 or $100–$200" />
                </td>
            </tr>
            <tr>
                <th style="padding:4px 0"><label for="sdb_svc_badge">Badge</label></th>
                <td style="padding:4px 0">
                    <input type="text" id="sdb_svc_badge" name="sdb_svc_badge" value="<?php echo $badge; ?>" class="widefat" placeholder="e.g. Popular (leave blank for none)" />
                </td>
            </tr>
        </table>
        <?php
    }

    // ── Render: values (driven by parent schema) ──────────────────────────────

    public function render_values( WP_Post $post ): void {
        $schema_src = sdb_sc_values_schema_source( $post->ID );
        if ( ! $schema_src ) {
            echo '<p style="color:#888;font-style:italic">Values are entered on child posts. Use the Row Schema box on the parent to define columns.</p>';
            return;
        }

        $schema = sdb_sc_get_schema( $schema_src );
        if ( empty( $schema ) ) {
            echo '<p style="color:#888;font-style:italic">No rows defined on the parent post yet. Add rows there first, then return here to fill in values.</p>';
            return;
        }

        wp_nonce_field( 'sdb_sc_values_save', 'sdb_sc_values_nonce' );

        $stored = get_post_meta( $post->ID, '_sdb_sc_values', true );
        $stored = is_array( $stored ) ? $stored : [];
        ?>
        <div id="sdb-sc-values-wrap">
            <p class="description" style="margin-bottom:16px">
                Fill in values for each comparison row defined on the parent post.
                These drive the comparison widget columns.
            </p>
            <table class="form-table sdb-sc-values-table">
                <?php foreach ( $schema as $row ) : ?>
                    <?php $this->render_value_row( $row, $stored[ $row['key'] ] ?? null ); ?>
                <?php endforeach; ?>
            </table>
        </div>
        <?php
    }

    // ── Render: single value row ──────────────────────────────────────────────

    private function render_value_row( array $row, $value ): void {
        $key   = esc_attr( $row['key'] );
        $label = esc_html( $row['label'] );
        $type  = $row['type'];
        $field_name = 'sdb_sc_val[' . $key . ']';
        ?>
        <tr class="sdb-sc-val-row" data-type="<?php echo esc_attr( $type ); ?>" data-key="<?php echo $key; ?>">
            <th scope="row" style="width:180px">
                <label>
                    <?php echo $label; ?>
                    <span class="sdb-sc-type-badge"><?php echo esc_html( $type ); ?></span>
                </label>
            </th>
            <td>
                <?php $this->render_field( $type, $field_name, $value, $row ); ?>
            </td>
        </tr>
        <?php
    }

    // ── Render: field by type ─────────────────────────────────────────────────

    private function render_field( string $type, string $name, $value, array $row ): void {
        switch ( $type ) {

            case 'text':
                $variant = $row['variant'] ?? '';
                if ( $variant === 'display' ) {
                    $placeholder = 'Short display phrase, e.g. "The premium option"';
                } elseif ( $variant === 'quote' ) {
                    $placeholder = 'Brief italic quote, e.g. "Best for first-timers"';
                } else {
                    $placeholder = 'e.g. 60 minutes';
                }
                echo '<input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) ( $value ?? '' ) ) . '" class="widefat" placeholder="' . esc_attr( $placeholder ) . '" />';
                if ( $variant ) {
                    echo '<p class="description">Rendered as <em>' . esc_html( $variant ) . '</em> style.</p>';
                }
                break;

            case 'pills':
                $lines = '';
                if ( is_array( $value ) ) {
                    $lines = implode( "\n", array_map( 'esc_html', $value ) );
                } elseif ( is_string( $value ) ) {
                    $lines = esc_html( $value );
                }
                echo '<textarea name="' . esc_attr( $name ) . '" rows="4" class="widefat" placeholder="One item per line&#10;e.g. Option A&#10;Option B&#10;Option C">' . $lines . '</textarea>';
                echo '<p class="description">One item per line. Each line becomes a pill tag.</p>';
                break;

            case 'rating':
                $max = (int) ( $row['max'] ?? 5 );
                $val = (float) ( $value ?? 0 );
                $icon_label = ( $row['icon'] ?? '' ) === 'star' ? '✦' : '●';
                echo '<div class="sdb-sc-rating-field">';
                for ( $i = 1; $i <= $max; $i++ ) :
                    $checked = $val >= $i ? 'checked' : '';
                    ?>
                    <label class="sdb-sc-star-label <?php echo $checked ? 'active' : ''; ?>" title="<?php echo $i; ?>">
                        <input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo $i; ?>" <?php echo $checked; ?> style="position:absolute;opacity:0;width:0" />
                        <span><?php echo esc_html( $icon_label ); ?></span>
                    </label>
                    <?php
                endfor;
                echo '<label class="sdb-sc-star-label sdb-sc-star-clear" title="Clear">';
                echo '<input type="radio" name="' . esc_attr( $name ) . '" value="0" ' . ( $val == 0 ? 'checked' : '' ) . ' style="position:absolute;opacity:0;width:0" />';
                echo '<span style="font-size:11px;opacity:.5">✕</span>';
                echo '</label>';
                echo '</div>';
                echo '<p class="description">Select 1–' . $max . '. Click ✕ to clear.</p>';
                break;

            case 'meter':
                $max = (int) ( $row['max'] ?? 5 );
                $val = (float) ( $value ?? 0 );
                echo '<div style="display:flex;align-items:center;gap:12px">';
                echo '<input type="range" name="' . esc_attr( $name ) . '" min="0" max="' . $max . '" step="1" value="' . $val . '" class="sdb-sc-meter-slider" style="width:200px" />';
                echo '<output class="sdb-sc-meter-output">' . $val . ' / ' . $max . '</output>';
                echo '</div>';
                break;

            case 'bool':
                $checked = ! empty( $value ) ? 'checked' : '';
                echo '<label style="display:flex;align-items:center;gap:8px;cursor:pointer">';
                echo '<input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . $checked . ' style="width:18px;height:18px" />';
                echo '<span>Yes</span>';
                echo '</label>';
                break;
        }
    }

    // ── Save ──────────────────────────────────────────────────────────────────

    public function save( int $post_id, WP_Post $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        // Only act on enabled post types
        $post_types = SDB_SC_Settings::get_saved_post_types();
        if ( ! in_array( $post->post_type, $post_types, true ) ) return;

        $post_type_obj = get_post_type_object( $post->post_type );
        $cap           = $post_type_obj ? $post_type_obj->cap->edit_posts : 'edit_posts';
        if ( ! current_user_can( $cap, $post_id ) ) return;

        $schema_src = sdb_sc_values_schema_source( $post_id );
        if ( ! $schema_src ) return; // parent post — values handled by its children

        // Header
        if (
            isset( $_POST['sdb_sc_header_nonce'] ) &&
            wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sdb_sc_header_nonce'] ) ), 'sdb_sc_header_save' )
        ) {
            update_post_meta( $post_id, '_sdb_sc_header', [
                'name'  => sanitize_text_field( wp_unslash( $_POST['sdb_svc_name']  ?? '' ) ),
                'price' => sanitize_text_field( wp_unslash( $_POST['sdb_svc_price'] ?? '' ) ),
                'badge' => sanitize_text_field( wp_unslash( $_POST['sdb_svc_badge'] ?? '' ) ),
            ] );
        }

        // Values
        if (
            isset( $_POST['sdb_sc_values_nonce'] ) &&
            wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sdb_sc_values_nonce'] ) ), 'sdb_sc_values_save' )
        ) {
            $schema = sdb_sc_get_schema( $schema_src );
            $raw    = $_POST['sdb_sc_val'] ?? []; // phpcs:ignore
            $clean  = [];

            foreach ( $schema as $row ) {
                $k   = $row['key'];
                $val = $raw[ $k ] ?? null;

                switch ( $row['type'] ) {
                    case 'bool':
                        $clean[ $k ] = ! empty( $val );
                        break;
                    case 'rating':
                    case 'meter':
                        $max         = (int) ( $row['max'] ?? 5 );
                        $clean[ $k ] = min( $max, max( 0, (float) $val ) );
                        break;
                    case 'pills':
                        $lines       = preg_split( '/[\r\n]+/', sanitize_textarea_field( wp_unslash( (string) $val ) ) );
                        $clean[ $k ] = array_values( array_filter( array_map( 'trim', $lines ) ) );
                        break;
                    default:
                        $clean[ $k ] = sanitize_text_field( wp_unslash( (string) ( $val ?? '' ) ) );
                        break;
                }
            }
            update_post_meta( $post_id, '_sdb_sc_values', $clean );
        }
    }
}

new SDB_SC_Values_Metabox();
