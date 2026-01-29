<?php
/**
 * Plugin Name:       Ekusey Ecom Plugin
 * Plugin URI:        https://github.com/anonnaabir00/ekusey-ecom-plugin
 * Description:       Custom e-commerce functionality for Ekusey — product API, homepage banner, affiliate system, and more.
 * Version:           1.0.2
 * Author:            Anonna Abir
 * Author URI:        https://github.com/anonnaabir00
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ekusey-ecom
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class — Singleton pattern.
 */
final class EkuseyEcomPlugin {

    /**
     * Plugin version.
     */
    const VERSION = '1.0.2';

    /**
     * Singleton instance.
     */
    private static $instance = null;

    /**
     * Get the singleton instance.
     */
    public static function init(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor — runs once.
     */
    private function __construct() {
        $this->define_constants();
        $this->load_dependencies();

        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );

        add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
    }

    /**
     * Define plugin constants.
     */
    private function define_constants(): void {
        define( 'EKUSEY_ECOM_VERSION', self::VERSION );
        define( 'EKUSEY_ECOM_FILE', __FILE__ );
        define( 'EKUSEY_ECOM_PATH', plugin_dir_path( __FILE__ ) );
        define( 'EKUSEY_ECOM_URL', plugin_dir_url( __FILE__ ) );
        define( 'EKUSEY_ECOM_INCLUDES', EKUSEY_ECOM_PATH . 'includes/' );
    }

    /**
     * Load the autoloader.
     */
    private function load_dependencies(): void {
        require_once EKUSEY_ECOM_INCLUDES . 'class-autoload.php';
    }

    /**
     * Fire up all the modules after plugins are loaded.
     */
    public function init_plugin(): void {
        $this->dispatch_hooks();
    }

    /**
     * Register all module hooks.
     */
    private function dispatch_hooks(): void {
        EkuseyEcom\Autoload::init();
        EkuseyEcom\Admin::init();
        EkuseyEcom\Enqueue::init();
        EkuseyEcom\Api::init();
        EkuseyEcom\Ajax::init();
        EkuseyEcom\AffiliateBloom::init();
        EkuseyEcom\ProductProfit::init();
    }

    /**
     * Plugin activation.
     */
    public function activate(): void {
        // Set a transient so we can redirect after activation.
        set_transient( 'ekusey_ecom_activation_redirect', true, 30 );
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate(): void {
        // Cleanup if needed.
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserialization.
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton.' );
    }
}

/**
 * Boot the plugin.
 */
function ekusey_ecom_start() {
    return EkuseyEcomPlugin::init();
}

ekusey_ecom_start();
