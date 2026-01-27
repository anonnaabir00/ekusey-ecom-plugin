<?php

namespace EkuseyEcom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AJAX action dispatcher.
 *
 * Each feature registers its own AJAX handlers via dedicated classes in includes/ajax/.
 */
class Ajax {

    public static function init(): void {
        $self = new self();
        $self->register_handlers();
    }

    /**
     * Dispatch AJAX handler registration to feature classes.
     */
    private function register_handlers(): void {
        ( new Ajax\Affiliate() )->register();
    }
}
