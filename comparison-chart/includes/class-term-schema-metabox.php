<?php
defined( 'ABSPATH' ) || exit;

/**
 * Term Schema Metabox (Taxonomy Mode)
 *
 * Adds a Row Schema editor to the term edit screens for any taxonomy enabled
 * in Settings → Comparison Chart. The schema is stored as term meta under
 * _sdb_sc_schema on the term, and a widget-settings record under _sdb_sc_widget.
 *
 * Posts assigned to a term that has a schema (and that have no post parent)
 * automatically become columns in a taxonomy-mode chart.
 */
class SDB_SC_Term_Schema_Metabox {

	public function __construct() {
		add_action( 'init', [ $this, 'register_hooks' ], 20 );
	}

	// ── Register hooks for each enabled taxonomy ──────────────────────────────

	public function register_hooks(): void {
		$taxonomies = SDB_SC_Settings::get_saved_taxonomies();
		foreach ( $taxonomies as $taxonomy ) {
			add_action( "{$taxonomy}_edit_form_fields", [ $this, 'render' ], 10, 2 );
			add_action( "edited_{$taxonomy}",           [ $this, 'save'   ], 10, 2 );
		}
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	// ── Enqueue ───────────────────────────────────────────────────────────────

	public function enqueue( string $hook ): void {
		if ( $hook !== 'term.php' && $hook !== 'edit-tags.php' ) return;

		$screen     = get_current_screen();
		$taxonomies = SDB_SC_Settings::get_saved_taxonomies();
		if ( ! $screen || ! in_array( $screen->taxonomy, $taxonomies, true ) ) return;

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

	// ── Render ────────────────────────────────────────────────────────────────

	public function render( WP_Term $term, string $taxonomy ): void {
		$rows        = get_term_meta( $term->term_id, '_sdb_sc_schema', true );
		$rows        = is_array( $rows ) ? $rows : [];
		$widget_meta = get_term_meta( $term->term_id, '_sdb_sc_widget', true );
		$widget_meta = is_array( $widget_meta ) ? $widget_meta : [];
		$current_label = esc_attr( $widget_meta['current_label'] ?? '' );
		?>
		<tr class="form-field">
			<th colspan="2">
				<h3 style="margin:24px 0 4px;padding-top:16px;border-top:1px solid #e0e0e0">
					<?php esc_html_e( 'Comparison Chart', 'comparison-chart' ); ?>
				</h3>
				<p class="description" style="font-weight:normal">
					<?php esc_html_e( 'Define the rows for charts generated from this term. Top-level posts assigned here become chart columns.', 'comparison-chart' ); ?>
				</p>
			</th>
		</tr>

		<tr class="form-field">
			<th scope="row">
				<label for="sdb_wgt_current_label_term"><?php esc_html_e( 'Current indicator label', 'comparison-chart' ); ?></label>
			</th>
			<td>
				<?php wp_nonce_field( 'sdb_sc_term_schema_save_' . $term->term_id, 'sdb_sc_term_schema_nonce' ); ?>
				<input type="text" id="sdb_wgt_current_label_term" name="sdb_wgt_current_label" value="<?php echo $current_label; ?>" class="regular-text" placeholder="Current" />
				<p class="description"><?php esc_html_e( 'Shown in the pinned column header. Defaults to "Current" if blank.', 'comparison-chart' ); ?></p>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Rows', 'comparison-chart' ); ?></th>
			<td>
				<p class="description" style="margin-bottom:12px">
					<?php esc_html_e( 'Define the rows that appear in the comparison table. Each row becomes a field that posts (the columns) fill in.', 'comparison-chart' ); ?>
				</p>

				<div id="sdb-sc-rows">
					<?php foreach ( $rows as $i => $row ) : ?>
						<?php $this->render_row( $i, $row ); ?>
					<?php endforeach; ?>
				</div>

				<button type="button" id="sdb-sc-add-row" class="button button-secondary" style="margin-top:10px">
					<?php esc_html_e( '+ Add Row', 'comparison-chart' ); ?>
				</button>

				<script type="text/html" id="sdb-sc-row-template">
					<?php $this->render_row( '__INDEX__', [] ); ?>
				</script>

				<input type="hidden" id="sdb_sc_schema_json" name="sdb_sc_schema_json" value="<?php echo esc_attr( wp_json_encode( $rows ) ); ?>">
			</td>
		</tr>
		<?php
	}

	// ── Render: single row (reuses the same markup as the post metabox) ───────

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
					<?php esc_html_e( 'Label', 'comparison-chart' ); ?>
					<input type="text" class="sdb-sc-label" value="<?php echo $label; ?>" placeholder="e.g. Duration" />
				</label>
				<label>
					<?php esc_html_e( 'Type', 'comparison-chart' ); ?>
					<select class="sdb-sc-type">
						<?php foreach ( [ 'text', 'pills', 'rating', 'meter', 'bool' ] as $t ) : ?>
							<option value="<?php echo $t; ?>" <?php selected( $type, $t ); ?>><?php echo ucfirst( $t ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>

				<span class="sdb-sc-opt-text" style="<?php echo $type !== 'text' ? 'display:none' : ''; ?>">
					<label>
						<?php esc_html_e( 'Variant', 'comparison-chart' ); ?>
						<select class="sdb-sc-variant">
							<option value="" <?php selected( $variant, '' ); ?>><?php esc_html_e( 'Default', 'comparison-chart' ); ?></option>
							<option value="display" <?php selected( $variant, 'display' ); ?>><?php esc_html_e( 'Display (serif)', 'comparison-chart' ); ?></option>
							<option value="quote" <?php selected( $variant, 'quote' ); ?>><?php esc_html_e( 'Quote (italic)', 'comparison-chart' ); ?></option>
						</select>
					</label>
				</span>

				<span class="sdb-sc-opt-rating" style="<?php echo $type !== 'rating' ? 'display:none' : ''; ?>">
					<label>
						<?php esc_html_e( 'Max', 'comparison-chart' ); ?>
						<input type="number" class="sdb-sc-max small-text" value="<?php echo (int) $max; ?>" min="1" max="10" style="width:55px" />
					</label>
					<label>
						<?php esc_html_e( 'Icon', 'comparison-chart' ); ?>
						<select class="sdb-sc-icon">
							<option value="" <?php selected( $icon, '' ); ?>><?php esc_html_e( 'Dots', 'comparison-chart' ); ?></option>
							<option value="star" <?php selected( $icon, 'star' ); ?>><?php esc_html_e( 'Stars (✦)', 'comparison-chart' ); ?></option>
						</select>
					</label>
				</span>

				<span class="sdb-sc-opt-meter" style="<?php echo $type !== 'meter' ? 'display:none' : ''; ?>">
					<label>
						<?php esc_html_e( 'Max', 'comparison-chart' ); ?>
						<input type="number" class="sdb-sc-max-meter small-text" value="<?php echo (int) $max; ?>" min="1" max="10" style="width:55px" />
					</label>
				</span>

				<span class="sdb-sc-opt-bool" style="<?php echo $type !== 'bool' ? 'display:none' : ''; ?>">
					<label>
						<?php esc_html_e( 'Yes label', 'comparison-chart' ); ?>
						<input type="text" class="sdb-sc-yes-label small-text" value="<?php echo $yes_label; ?>" placeholder="Yes" style="width:80px" />
					</label>
					<label>
						<?php esc_html_e( 'No label', 'comparison-chart' ); ?>
						<input type="text" class="sdb-sc-no-label small-text" value="<?php echo $no_label; ?>" placeholder="No" style="width:80px" />
					</label>
				</span>
			</div>

			<button type="button" class="sdb-sc-remove button-link-delete" title="Remove row">✕</button>
		</div>
		<?php
	}

	// ── Save ──────────────────────────────────────────────────────────────────

	public function save( int $term_id, int $tt_id ): void {
		if (
			! isset( $_POST['sdb_sc_term_schema_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['sdb_sc_term_schema_nonce'] ) ),
				'sdb_sc_term_schema_save_' . $term_id
			)
		) {
			return;
		}

		if ( ! current_user_can( 'manage_categories' ) ) return;

		// Schema
		if ( isset( $_POST['sdb_sc_schema_json'] ) ) {
			$json    = wp_unslash( $_POST['sdb_sc_schema_json'] );
			$decoded = json_decode( $json, true );
			if ( is_array( $decoded ) ) {
				update_term_meta( $term_id, '_sdb_sc_schema', $decoded );
			}
		}

		// Widget settings
		update_term_meta( $term_id, '_sdb_sc_widget', [
			'current_label' => sanitize_text_field( wp_unslash( $_POST['sdb_wgt_current_label'] ?? '' ) ),
		] );
	}
}

new SDB_SC_Term_Schema_Metabox();
