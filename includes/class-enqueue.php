<?php

namespace EkuseyEcom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue admin scripts and styles.
 */
class Enqueue {

    public static function init(): void {
        $self = new self();
        add_action( 'admin_enqueue_scripts', [ $self, 'enqueue_admin_assets' ] );
    }

    /**
     * Enqueue React app on the plugin's admin pages.
     */
    public function enqueue_admin_assets( string $hook ): void {
        // Only load on our own pages.
        if ( strpos( $hook, 'ekusey-ecom' ) === false ) {
            return;
        }

        $asset_dir = EKUSEY_ECOM_URL . 'includes/assets/admin/';

        // React app.
        wp_enqueue_script(
            'ekusey-ecom-admin',
            $asset_dir . 'admin.js',
            [ 'wp-element' ],
            EKUSEY_ECOM_VERSION,
            true
        );

        // Make the script an ES module so Vite output works.
        add_filter( 'script_loader_tag', function ( $tag, $handle ) {
            if ( 'ekusey-ecom-admin' === $handle ) {
                $tag = str_replace( '<script ', '<script type="module" ', $tag );
            }
            return $tag;
        }, 10, 2 );

        // Styles.
        wp_enqueue_style(
            'ekusey-ecom-admin',
            $asset_dir . 'admin.css',
            [],
            EKUSEY_ECOM_VERSION
        );

        // Localize — passes server data to the React app.
        wp_localize_script( 'ekusey-ecom-admin', 'ekuseyEcom', [
            'ajax_url'  => admin_url( 'admin-ajax.php' ),
            'rest_url'  => rest_url( 'ekusey/v1/' ),
            'nonce'     => wp_create_nonce( 'ekusey_ecom_nonce' ),
            'rest_nonce'=> wp_create_nonce( 'wp_rest' ),
            'admin_url' => admin_url(),
            'plugin_url'=> EKUSEY_ECOM_URL,
            'version'   => EKUSEY_ECOM_VERSION,
        ] );

        // Remove admin notices on our pages for a clean UI.
        remove_all_actions( 'admin_notices' );
    }
}
