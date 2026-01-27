<?php

namespace EkuseyEcom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin menu and page registration.
 */
class Admin {

    public static function init(): void {
        $self = new self();
        add_action( 'admin_menu', [ $self, 'register_menu' ] );
        add_action( 'admin_init', [ $self, 'activation_redirect' ] );
    }

    /**
     * Register the top-level admin menu.
     */
    public function register_menu(): void {
        add_menu_page(
            __( 'Ekusey Ecom', 'ekusey-ecom' ),
            __( 'Ekusey Ecom', 'ekusey-ecom' ),
            'manage_options',
            'ekusey-ecom',
            [ $this, 'render_app' ],
            'dashicons-store',
            30
        );

        // Sub-menus — all point to the same React SPA with different hash paths.
        add_submenu_page(
            'ekusey-ecom',
            __( 'Dashboard', 'ekusey-ecom' ),
            __( 'Dashboard', 'ekusey-ecom' ),
            'manage_options',
            'ekusey-ecom',
            [ $this, 'render_app' ]
        );

        add_submenu_page(
            'ekusey-ecom',
            __( 'Products', 'ekusey-ecom' ),
            __( 'Products', 'ekusey-ecom' ),
            'manage_options',
            'ekusey-ecom&path=products',
            [ $this, 'render_app' ]
        );

        add_submenu_page(
            'ekusey-ecom',
            __( 'Affiliates', 'ekusey-ecom' ),
            __( 'Affiliates', 'ekusey-ecom' ),
            'manage_options',
            'ekusey-ecom&path=affiliates',
            [ $this, 'render_app' ]
        );

        add_submenu_page(
            'ekusey-ecom',
            __( 'Settings', 'ekusey-ecom' ),
            __( 'Settings', 'ekusey-ecom' ),
            'manage_options',
            'ekusey-ecom&path=settings',
            [ $this, 'render_app' ]
        );
    }

    /**
     * Render the React root element.
     */
    public function render_app(): void {
        echo '<div id="ekusey-ecom-app"></div>';
    }

    /**
     * Redirect to plugin page on first activation.
     */
    public function activation_redirect(): void {
        if ( get_transient( 'ekusey_ecom_activation_redirect' ) ) {
            delete_transient( 'ekusey_ecom_activation_redirect' );

            if ( ! isset( $_GET['activate-multi'] ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=ekusey-ecom' ) );
                exit;
            }
        }
    }
}
