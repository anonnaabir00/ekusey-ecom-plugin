<?php

namespace EkuseyEcom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST API route dispatcher.
 *
 * Each feature registers its own routes via dedicated classes in includes/api/.
 */
class Api {

    public static function init(): void {
        $self = new self();
        add_action( 'rest_api_init', [ $self, 'register_routes' ] );
    }

    /**
     * Dispatch route registration to feature classes.
     */
    public function register_routes(): void {
        ( new Api\Products() )->register();
        ( new Api\HomepageBanner() )->register();
    }
}
