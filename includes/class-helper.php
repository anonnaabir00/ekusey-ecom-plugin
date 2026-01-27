<?php

namespace EkuseyEcom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared helper utilities.
 */
class Helper {

    /**
     * Auto-detect brand taxonomy used for WooCommerce products.
     */
    public static function detect_brand_taxonomy(): ?string {
        $taxes = get_object_taxonomies( 'product', 'objects' );
        if ( empty( $taxes ) ) {
            return null;
        }

        $preferred = [ 'brand', 'product_brand', 'pwb-brand' ];

        foreach ( $preferred as $slug ) {
            if ( isset( $taxes[ $slug ] ) ) {
                return $slug;
            }
        }

        foreach ( $taxes as $slug => $obj ) {
            $haystack = strtolower(
                $slug . ' ' .
                ( $obj->label ?? '' ) . ' ' .
                ( $obj->labels->name ?? '' ) . ' ' .
                ( $obj->labels->singular_name ?? '' )
            );
            if ( strpos( $haystack, 'brand' ) !== false ) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * Check if a brand term is disabled via SCF/ACF field.
     */
    public static function is_term_disabled_brand( int $term_id ): bool {
        if ( function_exists( 'get_field' ) ) {
            $v = get_field( 'disable_brand', 'term_' . $term_id );
            return ! empty( $v ) && $v !== '0';
        }

        $v = get_term_meta( $term_id, 'disable_brand', true );
        return ! empty( $v ) && $v !== '0';
    }
}
