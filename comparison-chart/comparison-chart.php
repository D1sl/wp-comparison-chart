<?php
/**
 * Plugin Name:       Comparison Chart
 * Plugin URI:        https://github.com/D1sl/wp-comparison-chart
 * Description:       Interactive, drag-to-reorder comparison chart for any content type. A parent post defines the rows; child posts supply the values. Works with or without Bricks Builder.
 * Version:           2.3.7
 * Author:            Benjamin Keaton
 * License:           GPL-2.0-or-later
 * Text Domain:       comparison-chart
 * Update URI:        https://updates.specialtydentalbrands.com/comparison-chart
 */

defined( 'ABSPATH' ) || exit;

define( 'SDB_SC_VERSION', '2.3.7' );
define( 'SDB_SC_FILE',    __FILE__ );
define( 'SDB_SC_DIR',     plugin_dir_path( __FILE__ ) );
define( 'SDB_SC_URL',     plugin_dir_url( __FILE__ ) );

// ── Core modules ─────────────────────────────────────────────────────────────
require_once SDB_SC_DIR . 'includes/class-settings.php';
require_once SDB_SC_DIR . 'includes/helpers.php';
require_once SDB_SC_DIR . 'includes/class-schema-metabox.php';
require_once SDB_SC_DIR . 'includes/class-term-schema-metabox.php';
require_once SDB_SC_DIR . 'includes/class-values-metabox.php';
require_once SDB_SC_DIR . 'includes/class-assets.php';
require_once SDB_SC_DIR . 'includes/class-shortcode.php';

// ── Bricks element (only when Bricks is active AND support is enabled) ────────
// Priority 11 ensures Bricks has run its own init (priority 10) and defined
// \Bricks\Element before our element file is required.
add_action( 'init', function () {
    if ( ! class_exists( '\Bricks\Elements' ) ) return;
    if ( ! SDB_SC_Settings::bricks_enabled() ) return;
    \Bricks\Elements::register_element( SDB_SC_DIR . 'bricks/element-comparison-chart.php' );
}, 11 );
