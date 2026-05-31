<?php
defined( 'ABSPATH' ) || exit;

/**
 * Front-end asset registration + enqueue.
 *
 * Assets are always REGISTERED so the shortcode and Bricks element can enqueue
 * them on demand.
 *
 * When Bricks support is ON, we also auto-enqueue unconditionally on the front
 * end — Bricks' own enqueue_scripts() hook for custom elements only fires when
 * its asset-loading mode is "Load all", so relying on it is unreliable.
 *
 * When Bricks support is OFF, the shortcode enqueues on demand, so charts only
 * load assets on pages that actually contain one.
 */
class SDB_SC_Assets {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'register' ], 1 );
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue' ] );
    }

    public function register(): void {
        wp_register_style(
            'sdb-sc-widget',
            SDB_SC_URL . 'assets/widget.css',
            [],
            SDB_SC_VERSION
        );
        wp_register_script(
            'sdb-sc-widget',
            SDB_SC_URL . 'assets/widget.js',
            [],
            SDB_SC_VERSION,
            true
        );
    }

    public function maybe_enqueue(): void {
        if ( SDB_SC_Settings::bricks_enabled() ) {
            wp_enqueue_style( 'sdb-sc-widget' );
            wp_enqueue_script( 'sdb-sc-widget' );
        }
    }
}

new SDB_SC_Assets();
