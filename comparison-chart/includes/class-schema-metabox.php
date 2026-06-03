<?php
defined( 'ABSPATH' ) || exit;

/**
 * Schema Metabox
 * Renders on parent pages (pages with no parent, or pages whose slug starts with
 * "new-services"). Lets editors define comparison rows: key, label, type, and
 * type-specific options. Rows are stored as JSON in _sdb_sc_schema post meta.
 */
class SDB_SC_Schema_Metabox {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'register' ], 10, 2 );
        add_action( 'save_post', [ $this, 'save' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    // ── Register ──────────────────────────────────────────────────────────────

    public function register( string $post_type, $post = null ): void {
        $post_types = SDB_SC_Settings::get_saved_post_types();
        if ( ! in_array( $post_type, $post_types, true ) ) return;

        // Resolve the post object — the hook usually passes it, but fall back
        // to the global if a context omits it.
        if ( ! $post instanceof WP_Post ) {
            $post = get_post();
        }
        if ( ! $post instanceof WP_Post ) return;

        // Single combined metabox appears ONLY on parent posts (those with
        // published children). Child posts and orphans never see it.
        if ( sdb_sc_post_role( $post->ID ) !== 'parent' ) return;

        add_meta_box(
            'sdb_sc_schema',
            'Comparison Chart Settings',
            [ $this, 'render' ],
            $post_type,
            'normal',
            'high'
        );
    }

    // ── Enqueue admin JS/CSS ──────────────────────────────────────────────────

    public function enqueue( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
        $screen = get_current_screen();
        if ( ! $screen ) return;
        $post_types = SDB_SC_Settings::get_saved_post_types();
        if ( ! in_array( $screen->post_type, $post_types, true ) ) return;

        wp_enqueue_script(
            'sdb-sc-schema-admin',
            SDB_SC_URL . 'assets/admin-schema.js',
            [ 'jquery', 'jquery-ui-sortable' ],
            SDB_SC_VERSION,
            true
        );
        wp_enqueue_style(
            'sdb-sc-admin',
            SDB_SC_URL . 'assets/admin.css',
            [],
            SDB_SC_VERSION
        );
    }

    // ── Render: row schema ────────────────────────────────────────────────────

    public function render( WP_Post $post ): void {
        wp_nonce_field( 'sdb_sc_schema_save', 'sdb_sc_schema_nonce' );
        wp_nonce_field( 'sdb_sc_widget_save', 'sdb_sc_widget_nonce' );

        $meta          = get_post_meta( $post->ID, '_sdb_sc_widget', true );
        $meta          = is_array( $meta ) ? $meta : [];
        $current_label = esc_attr( $meta['current_label'] ?? '' );

        $rows = get_post_meta( $post->ID, '_sdb_sc_schema', true );
        $rows = is_array( $rows ) ? $rows : [];
        ?>
        <div id="sdb-sc-schema-wrap">

            <table class="form-table" style="margin:0 0 20px">
                <tr>
                    <th style="width:180px;padding:4px 0"><label for="sdb_wgt_current_label">Current indicator label</label></th>
                    <td style="padding:4px 0">
                        <input type="text" id="sdb_wgt_current_label" name="sdb_wgt_current_label" value="<?php echo $current_label; ?>" class="regular-text" placeholder="Current" />
                        <p class="description">Shown in the pinned column header and on its toggle button. Defaults to &ldquo;Current&rdquo; if left blank.</p>
                    </td>
                </tr>
            </table>

            <hr style="margin:0 0 16px;border:none;border-top:1px solid #e0e0e0" />

            <p class="description" style="margin-bottom:12px">
                <strong>Rows.</strong> Define the rows that appear in the comparison table. Each row becomes a field that child posts (the columns) fill in.
            </p>

            <div id="sdb-sc-rows">
                <?php foreach ( $rows as $i => $row ) : ?>
                    <?php $this->render_row( $i, $row ); ?>
                <?php endforeach; ?>
            </div>

            <button type="button" id="sdb-sc-add-row" class="button button-secondary" style="margin-top:10px">
                + Add Row
            </button>

            <!-- Hidden template for JS cloning -->
            <script type="text/html" id="sdb-sc-row-template">
                <?php $this->render_row( '__INDEX__', [] ); ?>
            </script>

            <input type="hidden" id="sdb_sc_schema_json" name="sdb_sc_schema_json" value="<?php echo esc_attr( wp_json_encode( $rows ) ); ?>">
        </div>
        <?php
    }

    // ── Render: single row ────────────────────────────────────────────────────

    private function render_row( $index, array $row ): void {
        $key       = esc_attr( $row['key']       ?? '' );
        $label     = esc_attr( $row['label']     ?? '' );
        $type      = $row['type']    ?? 'text';
        $variant   = $row['variant'] ?? '';
        $max       = $row['max']     ?? 5;
        $icon      = $row['icon']    ?? '';
        $yes_label = esc_attr( $row['yes_label'] ?? '' );
        $no_label  = esc_attr( $row['no_label']  ?? '' );
        ?>
        <div class="sdb-sc-row" data-index="<?php echo esc_attr( $index ); ?>">
            <span class="sdb-sc-handle dashicons dashicons-move" title="Drag to reorder"></span>

            <div class="sdb-sc-row-fields">
                <input type="hidden" class="sdb-sc-key" value="<?php echo $key; ?>" />
                <label>
                    Label
                    <input type="text" class="sdb-sc-label" value="<?php echo $label; ?>" placeholder="e.g. Duration" />
                </label>
                <label>
                    Type
                    <select class="sdb-sc-type">
                        <?php foreach ( [ 'text', 'pills', 'rating', 'meter', 'bool' ] as $t ) : ?>
                            <option value="<?php echo $t; ?>" <?php selected( $type, $t ); ?>><?php echo ucfirst( $t ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <span class="sdb-sc-opt-text" style="<?php echo $type !== 'text' ? 'display:none' : ''; ?>">
                    <label>
                        Variant
                        <select class="sdb-sc-variant">
                            <option value="" <?php selected( $variant, '' ); ?>>Default</option>
                            <option value="display" <?php selected( $variant, 'display' ); ?>>Display (serif)</option>
                            <option value="quote" <?php selected( $variant, 'quote' ); ?>>Quote (italic)</option>
                        </select>
                    </label>
                </span>

                <span class="sdb-sc-opt-rating" style="<?php echo $type !== 'rating' ? 'display:none' : ''; ?>">
                    <label>
                        Max
                        <input type="number" class="sdb-sc-max small-text" value="<?php echo (int) $max; ?>" min="1" max="10" style="width:55px" />
                    </label>
                    <label>
                        Icon
                        <select class="sdb-sc-icon">
                            <option value="" <?php selected( $icon, '' ); ?>>Dots</option>
                            <option value="star" <?php selected( $icon, 'star' ); ?>>Stars (✦)</option>
                        </select>
                    </label>
                </span>

                <span class="sdb-sc-opt-meter" style="<?php echo $type !== 'meter' ? 'display:none' : ''; ?>">
                    <label>
                        Max
                        <input type="number" class="sdb-sc-max-meter small-text" value="<?php echo (int) $max; ?>" min="1" max="10" style="width:55px" />
                    </label>
                </span>

                <span class="sdb-sc-opt-bool" style="<?php echo $type !== 'bool' ? 'display:none' : ''; ?>">
                    <label>
                        Yes label
                        <input type="text" class="sdb-sc-yes-label small-text" value="<?php echo $yes_label; ?>" placeholder="Yes" style="width:80px" />
                    </label>
                    <label>
                        No label
                        <input type="text" class="sdb-sc-no-label small-text" value="<?php echo $no_label; ?>" placeholder="No" style="width:80px" />
                    </label>
                </span>
            </div>

            <button type="button" class="sdb-sc-remove button-link-delete" title="Remove row">✕</button>
        </div>
        <?php
    }

    // ── Save ──────────────────────────────────────────────────────────────────

    public function save( int $post_id, WP_Post $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        // Only act on enabled post types
        $post_types = SDB_SC_Settings::get_saved_post_types();
        if ( ! in_array( $post->post_type, $post_types, true ) ) return;

        // Use the correct capability for this post type
        $post_type_obj = get_post_type_object( $post->post_type );
        $cap           = $post_type_obj ? $post_type_obj->cap->edit_posts : 'edit_posts';
        if ( ! current_user_can( $cap, $post_id ) ) return;

        if ( wp_get_post_parent_id( $post_id ) ) return; // child posts handled by values metabox

        // Schema
        if (
            isset( $_POST['sdb_sc_schema_nonce'] ) &&
            wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sdb_sc_schema_nonce'] ) ), 'sdb_sc_schema_save' ) &&
            isset( $_POST['sdb_sc_schema_json'] )
        ) {
            $json = wp_unslash( $_POST['sdb_sc_schema_json'] );
            $decoded = json_decode( $json, true );
            if ( is_array( $decoded ) ) {
                // Re-validate via helper
                update_post_meta( $post_id, '_sdb_sc_schema', $decoded );
            }
        }

        // Widget settings
        if (
            isset( $_POST['sdb_sc_widget_nonce'] ) &&
            wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sdb_sc_widget_nonce'] ) ), 'sdb_sc_widget_save' )
        ) {
            update_post_meta( $post_id, '_sdb_sc_widget', [
                'current_label' => sanitize_text_field( wp_unslash( $_POST['sdb_wgt_current_label'] ?? '' ) ),
            ] );
        }
    }
}

new SDB_SC_Schema_Metabox();
