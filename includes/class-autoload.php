<?php

namespace EkuseyEcom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Autoloader — scans known directories for class-*.php files.
 */
class Autoload {

    /**
     * Directories to scan for class files.
     *
     * @var string[]
     */
    private $directories = [];

    /**
     * Register the autoloader.
     */
    public static function init(): void {
        $self = new self();

        $self->directories = [
            EKUSEY_ECOM_INCLUDES,
            EKUSEY_ECOM_INCLUDES . 'api/',
            EKUSEY_ECOM_INCLUDES . 'ajax/',
            EKUSEY_ECOM_INCLUDES . 'modules/',
        ];

        spl_autoload_register( [ $self, 'autoload' ] );
    }

    /**
     * Autoload callback.
     */
    public function autoload( string $class ): void {
        // Only handle our namespace.
        if ( strpos( $class, 'EkuseyEcom\\' ) !== 0 ) {
            return;
        }

        // Strip namespace prefix → e.g. "Ajax\Affiliate" or "Admin"
        $relative = str_replace( 'EkuseyEcom\\', '', $class );

        // Split into namespace parts and class name.
        // "Ajax\Affiliate" → parts: ["Ajax"], class: "Affiliate"
        // "Admin"          → parts: [],       class: "Admin"
        $segments   = explode( '\\', $relative );
        $class_name = array_pop( $segments );

        // Build subdirectory path from namespace parts: Ajax → ajax/
        $sub_dir = '';
        if ( ! empty( $segments ) ) {
            $sub_dir = strtolower( implode( '/', $segments ) ) . '/';
        }

        // Convert class name: "HomepageBanner" → "class-homepage-banner.php"
        $file_name = 'class-' . strtolower(
            preg_replace( '/([a-z])([A-Z])/', '$1-$2', $class_name )
        ) . '.php';

        // Try the exact namespace-mapped path first.
        $file = EKUSEY_ECOM_INCLUDES . $sub_dir . $file_name;
        if ( file_exists( $file ) ) {
            require_once $file;
            return;
        }

        // Fallback: scan all registered directories.
        foreach ( $this->directories as $dir ) {
            $file = $dir . $file_name;
            if ( file_exists( $file ) ) {
                require_once $file;
                return;
            }
        }
    }
}
