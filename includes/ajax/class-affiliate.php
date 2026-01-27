<?php

namespace EkuseyEcom\Ajax;

use EkuseyEcom\AffiliateBloom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AJAX handler for affiliate commission claims.
 */
class Affiliate {

    /**
     * Register AJAX actions.
     */
    public function register(): void {
        add_action( 'wp_ajax_ekusey_affiliate_claim_commission', [ $this, 'handle_claim_commission' ] );
    }

    /**
     * Handle commission claim AJAX request.
     */
    public function handle_claim_commission(): void {
        // Verify nonce.
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'affiliate_claim_commission' ) ) {
            wp_send_json_error( [ 'message' => 'Security check failed. Please refresh the page and try again.' ] );
        }

        // Check permissions.
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( [ 'message' => 'You do not have permission to perform this action.' ] );
        }

        $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
        if ( ! $order_id ) {
            wp_send_json_error( [ 'message' => 'Invalid order ID.' ] );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( [ 'message' => 'Order not found.' ] );
        }

        $affiliate_code    = $order->get_meta( '_affiliate_ref_code' );
        $commission_amount = $order->get_meta( '_affiliate_commission_amount' );
        $current_status    = $order->get_meta( '_affiliate_commission_status' );

        if ( empty( $affiliate_code ) ) {
            wp_send_json_error( [ 'message' => 'No affiliate code found for this order.' ] );
        }

        if ( $current_status === 'claimed' || $current_status === 'paid' ) {
            wp_send_json_error( [ 'message' => 'Commission has already been ' . $current_status . '.' ] );
        }

        // Call the conversion API.
        $api_data = [
            'affiliate_code'    => $affiliate_code,
            'commission_amount' => floatval( $commission_amount ),
            'order_id'          => $order_id,
        ];

        $api_url = 'https://wordpress-1557942-6041866.cloudwaysapps.com/wp-json/affiliate-bloom/v1/conversion';

        $api_response = wp_remote_post( $api_url, [
            'headers'   => [ 'Content-Type' => 'application/json' ],
            'body'      => wp_json_encode( $api_data ),
            'timeout'   => 30,
            'sslverify' => false,
        ] );

        if ( is_wp_error( $api_response ) ) {
            wp_send_json_error( [ 'message' => 'API call failed: ' . $api_response->get_error_message() ] );
        }

        $response_code = wp_remote_retrieve_response_code( $api_response );
        $response_body = wp_remote_retrieve_body( $api_response );

        if ( $response_code === 200 || $response_code === 201 ) {
            $order->update_meta_data( '_affiliate_commission_status', 'claimed' );
            $order->update_meta_data( '_affiliate_commission_claimed_date', current_time( 'mysql' ) );
            $order->save();

            $order->add_order_note(
                sprintf(
                    /* translators: 1: commission amount 2: affiliate code */
                    __( 'Affiliate commission of %1$s claimed for affiliate code: %2$s', 'ekusey-ecom' ),
                    wc_price( $commission_amount ),
                    $affiliate_code
                )
            );

            wp_send_json_success( [
                'message'      => 'Commission claimed successfully! Amount: ' . wc_price( $commission_amount ),
                'api_response' => json_decode( $response_body ),
            ] );
        } else {
            wp_send_json_error( [
                'message' => 'API returned error (Code: ' . $response_code . '): ' . substr( $response_body, 0, 200 ),
                'code'    => $response_code,
                'details' => $response_body,
            ] );
        }
    }
}
