<?php

namespace EkuseyEcom\Api;

use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST endpoints: homepage banner and options debug.
 */
class HomepageBanner {

    /**
     * Register REST routes.
     */
    public function register(): void {
        // Public endpoint for homepage banner data.
        register_rest_route( 'ekusey/v1', '/homepage-banner', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_banner' ],
            'permission_callback' => '__return_true',
        ] );

        // Admin-only debug endpoint.
        register_rest_route( 'ekusey/v1', '/options', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_options_debug' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );
    }

    /**
     * Fetch repeater rows from Options using ACF/SCF API if available,
     * otherwise fall back to wp_options.
     */
    public function get_banner( WP_REST_Request $request ) {
        $rows = null;

        // 1) If SCF/ACF functions exist.
        if ( function_exists( 'get_field' ) ) {
            $rows = get_field( 'homepage_banner', 'option' );
        }

        // 2) Fallback: try common option keys.
        if ( empty( $rows ) ) {
            $try_keys = [
                'homepage_banner',
                'ekusey_options',
                'ekusey_options_fields',
                'scf_ekusey_options',
                'scf_options_ekusey_options',
            ];

            foreach ( $try_keys as $k ) {
                $v = get_option( $k );
                if ( ! empty( $v ) ) {
                    $rows = $v;
                    break;
                }
            }
        }

        if ( ! is_array( $rows ) ) {
            $rows = [];
        }

        $result = [];

        foreach ( $rows as $i => $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $banner = $row['banner'] ?? null;

            $attachment_id = null;
            $url           = null;
            $alt           = null;

            if ( is_numeric( $banner ) ) {
                $attachment_id = (int) $banner;
            } elseif ( is_array( $banner ) ) {
                $attachment_id = isset( $banner['ID'] ) ? (int) $banner['ID'] : null;
                $url           = $banner['url'] ?? null;
                $alt           = $banner['alt'] ?? null;
            } elseif ( is_string( $banner ) ) {
                $url = $banner;
            }

            if ( $attachment_id && ! $url ) {
                $url = wp_get_attachment_url( $attachment_id );
            }
            if ( $attachment_id && ! $alt ) {
                $alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
            }

            $result[] = [
                'index'     => $i,
                'image_id'  => $attachment_id,
                'image_url' => $url,
                'image_alt' => $alt,
                'raw'       => $row,
            ];
        }

        return rest_ensure_response( [
            'ok'    => true,
            'count' => count( $result ),
            'items' => $result,
        ] );
    }

    /**
     * Admin-only: check what options exist (debug).
     */
    public function get_options_debug( WP_REST_Request $request ) {
        $keys_to_check = [
            'homepage_banner',
            'ekusey_options',
            'ekusey_options_fields',
            'scf_ekusey_options',
            'scf_options_ekusey_options',
        ];

        $out = [];
        foreach ( $keys_to_check as $k ) {
            $v = get_option( $k );
            if ( is_array( $v ) ) {
                $out[ $k ] = [ 'type' => 'array', 'count' => count( $v ) ];
            } else {
                $out[ $k ] = [
                    'type'          => gettype( $v ),
                    'value_preview' => is_string( $v ) ? substr( $v, 0, 120 ) : $v,
                ];
            }
        }

        return rest_ensure_response( [
            'ok'      => true,
            'checked' => $out,
            'note'    => 'If none contains your data, SCF is likely storing options under a different key, or via get_field(..., option).',
        ] );
    }
}
