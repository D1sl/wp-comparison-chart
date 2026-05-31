<?php
defined( 'ABSPATH' ) || exit;

/**
 * Determine whether a post has any published children.
 *
 * @param  int $post_id
 * @return bool
 */
function sdb_sc_has_children( int $post_id ): bool {
    $children = get_posts( [
        'post_parent'    => $post_id,
        'post_type'      => get_post_type( $post_id ) ?: 'any',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ] );
    return ! empty( $children );
}

/**
 * Classify a post's role in the comparison-chart hierarchy.
 *
 * Returns one of:
 *   'child'  — has a parent; values live here, schema comes from the parent.
 *   'parent' — has children; schema lives here, values come from children.
 *   'none'   — no parent AND no children; nothing to compare, no metaboxes shown.
 *
 * @param  int $post_id
 * @return string
 */
function sdb_sc_post_role( int $post_id ): string {
    if ( wp_get_post_parent_id( $post_id ) ) {
        return 'child';
    }
    if ( sdb_sc_has_children( $post_id ) ) {
        return 'parent';
    }
    return 'none';
}

/**
 * Determine which post ID holds the schema that a given post's VALUES map to.
 *
 * - child:  the parent post
 * - parent: null (parents don't have a values box — their children do)
 * - none:   null (nothing to compare)
 *
 * @param  int $post_id
 * @return int|null
 */
function sdb_sc_values_schema_source( int $post_id ): ?int {
    if ( sdb_sc_post_role( $post_id ) === 'child' ) {
        return wp_get_post_parent_id( $post_id );
    }
    return null;
}

/**
 * Retrieve the row schema stored on a parent page.
 *
 * @param  int   $page_id  Post ID of the parent (schema) page.
 * @return array           Array of row definition arrays, empty if none.
 */
function sdb_sc_get_schema( int $page_id ): array {
    $raw = get_post_meta( $page_id, '_sdb_sc_schema', true );
    if ( empty( $raw ) || ! is_array( $raw ) ) return [];

    $allowed_types    = [ 'text', 'pills', 'rating', 'meter', 'bool' ];
    $allowed_variants = [ '', 'display', 'quote' ];
    $allowed_icons    = [ '', 'star' ];

    $clean = [];
    foreach ( $raw as $row ) {
        if ( empty( $row['key'] ) || empty( $row['label'] ) ) continue;
        $type = in_array( $row['type'] ?? '', $allowed_types, true ) ? $row['type'] : 'text';
        $r    = [
            'key'   => sanitize_key( $row['key'] ),
            'label' => sanitize_text_field( $row['label'] ),
            'type'  => $type,
        ];
        if ( $type === 'text' ) {
            $variant = $row['variant'] ?? '';
            if ( in_array( $variant, $allowed_variants, true ) && $variant !== '' ) {
                $r['variant'] = $variant;
            }
        }
        if ( in_array( $type, [ 'rating', 'meter' ], true ) ) {
            $r['max'] = isset( $row['max'] ) ? max( 1, (int) $row['max'] ) : 5;
        }
        if ( $type === 'rating' ) {
            $icon = $row['icon'] ?? '';
            if ( in_array( $icon, $allowed_icons, true ) && $icon !== '' ) {
                $r['icon'] = $icon;
            }
        }
        $clean[] = $r;
    }
    return $clean;
}

/**
 * Retrieve service value data stored on a child page.
 *
 * @param  int   $page_id  Post ID of the child page.
 * @param  array $schema   Row schema from sdb_sc_get_schema().
 * @return array           Flat associative array of key => value.
 */
function sdb_sc_get_values( int $page_id, array $schema ): array {
    $raw    = get_post_meta( $page_id, '_sdb_sc_values', true );
    $raw    = is_array( $raw ) ? $raw : [];
    $values = [];

    foreach ( $schema as $row ) {
        $k   = $row['key'];
        $val = $raw[ $k ] ?? null;

        switch ( $row['type'] ) {
            case 'bool':
                $values[ $k ] = ! empty( $val );
                break;

            case 'rating':
            case 'meter':
                $values[ $k ] = isset( $val ) ? (float) $val : 0;
                break;

            case 'pills':
                // Stored as newline-separated string; return array of trimmed strings.
                if ( is_array( $val ) ) {
                    $values[ $k ] = array_values( array_filter( array_map( 'sanitize_text_field', $val ) ) );
                } else {
                    $lines = preg_split( '/[\r\n]+/', (string) $val );
                    $values[ $k ] = array_values( array_filter( array_map( 'trim', $lines ) ) );
                }
                break;

            default: // text
                $values[ $k ] = sanitize_text_field( (string) ( $val ?? '' ) );
                break;
        }
    }
    return $values;
}

/**
 * Retrieve service header meta (name, price, badge) stored on a child page.
 *
 * @param  int $page_id
 * @return array
 */
function sdb_sc_get_service_header( int $page_id ): array {
    $meta = get_post_meta( $page_id, '_sdb_sc_header', true );
    $meta = is_array( $meta ) ? $meta : [];
    return [
        'name'  => sanitize_text_field( ! empty( $meta['name'] ) ? $meta['name'] : get_the_title( $page_id ) ),
        'price' => sanitize_text_field( $meta['price'] ?? '' ),
        'badge' => sanitize_text_field( $meta['badge'] ?? '' ),
    ];
}

/**
 * Build the full JS config array for a given page (parent or child).
 *
 * @param  int $current_id  The page currently being rendered.
 * @return array|null       Config array ready for wp_json_encode, or null on failure.
 */
function sdb_sc_build_config( int $current_id ): ?array {
    $role = sdb_sc_post_role( $current_id );
    if ( $role === 'none' ) return null; // nothing to compare

    $parent_id = wp_get_post_parent_id( $current_id );
    $schema_id = ( $role === 'child' ) ? $parent_id : $current_id;

    $schema = sdb_sc_get_schema( $schema_id );
    if ( empty( $schema ) ) return null;

    // Columns are the published children of the schema post.
    $children = get_posts( [
        'post_parent'    => $schema_id,
        'post_type'      => get_post_type( $schema_id ) ?: 'any',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ] );
    if ( empty( $children ) ) return null;

    $services       = [];
    $current_svc_id = null;

    foreach ( $children as $child ) {
        $pid    = (int) $child->ID;
        $svc_id = 'svc-' . $pid;
        $header = sdb_sc_get_service_header( $pid );
        $vals   = sdb_sc_get_values( $pid, $schema );

        $svc = [
            'id'    => $svc_id,
            'name'  => $header['name'],
            'price' => $header['price'],
            'badge' => $header['badge'] !== '' ? $header['badge'] : null,
        ];
        foreach ( $vals as $k => $v ) {
            $svc[ $k ] = $v;
        }
        $services[] = $svc;

        if ( $pid === $current_id ) {
            $current_svc_id = $svc_id;
        }
    }

    // Widget-level overrides stored on the schema post
    $widget_meta = get_post_meta( $schema_id, '_sdb_sc_widget', true );
    $widget_meta = is_array( $widget_meta ) ? $widget_meta : [];

    return [
        'currentServiceId' => $current_svc_id, // null on parent/category pages
        'storageKey'       => 'sdb-svc-' . $schema_id,
        'columnWidth'      => 188,
        'labelWidth'       => 156,
        'currentLabel'     => ! empty( $widget_meta['current_label'] ) ? sanitize_text_field( $widget_meta['current_label'] ) : 'Current',
        'rows'             => $schema,
        'services'         => $services,
    ];
}
