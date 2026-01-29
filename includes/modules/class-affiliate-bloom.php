<?php

namespace EkuseyEcom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Affiliate Bloom — cookie tracking, order meta, admin UI, bulk actions.
 */
class AffiliateBloom {

    /**
     * Commission rate (30%).
     */
    const COMMISSION_RATE = 0.30;

    public static function init(): void {
        $self = new self();

        // Store affiliate cookie from ?ref= param.
        add_action( 'template_redirect', [ $self, 'store_affiliate_cookie' ], 1 );

        // Save affiliate ref to WooCommerce orders (multiple hooks for compat).
        add_action( 'woocommerce_checkout_create_order', [ $self, 'save_ref_to_order' ], 10, 2 );
        add_action( 'woocommerce_store_api_checkout_order_processed', [ $self, 'save_ref_to_order_api' ] );
        add_action( 'woocommerce_checkout_order_processed', [ $self, 'save_ref_to_order_legacy' ], 10, 1 );

        // Admin order panel.
        add_action( 'woocommerce_admin_order_data_after_order_details', [ $self, 'display_order_ref' ] );

        // Admin scripts (claim button).
        add_action( 'admin_footer', [ $self, 'admin_scripts' ] );

        // Bulk actions.
        add_filter( 'bulk_actions-edit-shop_order', [ $self, 'add_bulk_actions' ] );
        add_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $self, 'add_bulk_actions' ] );
        add_filter( 'handle_bulk_actions-edit-shop_order', [ $self, 'handle_bulk_actions' ], 10, 3 );
        add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', [ $self, 'handle_bulk_actions' ], 10, 3 );
        add_action( 'admin_notices', [ $self, 'bulk_action_admin_notice' ] );
    }

    // ------------------------------------------------------------------
    // Cookie
    // ------------------------------------------------------------------

    /**
     * Store affiliate code in cookie when visitor arrives via ?ref=CODE.
     */
    public function store_affiliate_cookie(): void {
        if ( ! isset( $_GET['ref'] ) || empty( $_GET['ref'] ) ) {
            return;
        }

        $affiliate_code = sanitize_text_field( $_GET['ref'] );

        $cookie_name   = 'affiliate_bloom_ref';
        $cookie_expiry = time() + ( 30 * DAY_IN_SECONDS );
        $cookie_path   = '/';
        $cookie_domain = '';

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ( strpos( $host, 'localhost' ) === false && strpos( $host, ':' ) === false ) {
            $cookie_domain = $host;
        }

        setcookie(
            $cookie_name,
            $affiliate_code,
            $cookie_expiry,
            $cookie_path,
            $cookie_domain,
            is_ssl(),
            true
        );

        $_COOKIE[ $cookie_name ] = $affiliate_code;

        do_action( 'affiliate_bloom_cookie_set', $affiliate_code );
    }

    /**
     * Get current affiliate ref from cookie.
     */
    public static function get_current_ref(): string {
        return isset( $_COOKIE['affiliate_bloom_ref'] )
            ? sanitize_text_field( $_COOKIE['affiliate_bloom_ref'] )
            : '';
    }

    // ------------------------------------------------------------------
    // Save to order
    // ------------------------------------------------------------------

    /**
     * Classic checkout hook.
     *
     * NOTE: This hook fires BEFORE line items are added to the order,
     * so we only save the ref code here. Net profit and commission
     * are calculated later in the order_processed hook when items exist.
     */
    public function save_ref_to_order( $order, $data ): void {
        $ref = self::get_current_ref();
        if ( empty( $ref ) ) {
            return;
        }

        $order->update_meta_data( '_affiliate_ref_code', $ref );
        $order->update_meta_data( '_affiliate_commission_status', 'pending' );
    }

    /**
     * WooCommerce Blocks checkout hook.
     */
    public function save_ref_to_order_api( $order ): void {
        $ref = self::get_current_ref();
        if ( empty( $ref ) ) {
            return;
        }

        $order->update_meta_data( '_affiliate_ref_code', $ref );

        $net_profit = self::calculate_order_net_profit( $order );
        $commission = $net_profit * self::COMMISSION_RATE;

        $order->update_meta_data( '_affiliate_commission_amount', $commission );
        $order->update_meta_data( '_affiliate_commission_rate', self::COMMISSION_RATE );
        $order->update_meta_data( '_affiliate_commission_status', 'pending' );
        $order->update_meta_data( '_affiliate_order_net_profit', $net_profit );

        $order->save();
    }

    /**
     * Legacy order processed hook.
     *
     * This fires AFTER line items are created, so net profit can be
     * calculated correctly. Uses order object methods for HPOS compat.
     */
    public function save_ref_to_order_legacy( $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // If ref was already saved by the create_order hook, use it.
        // Otherwise check the cookie.
        $ref = $order->get_meta( '_affiliate_ref_code' );
        if ( empty( $ref ) ) {
            $ref = self::get_current_ref();
        }

        if ( empty( $ref ) ) {
            return;
        }

        $net_profit = self::calculate_order_net_profit( $order );
        $commission = $net_profit * self::COMMISSION_RATE;

        $order->update_meta_data( '_affiliate_ref_code', $ref );
        $order->update_meta_data( '_affiliate_commission_amount', $commission );
        $order->update_meta_data( '_affiliate_commission_rate', self::COMMISSION_RATE );
        $order->update_meta_data( '_affiliate_commission_status', 'pending' );
        $order->update_meta_data( '_affiliate_order_net_profit', $net_profit );
        $order->save();
    }

    /**
     * Calculate net profit for an order (excludes shipping).
     *
     * Net profit = Sum of (item_subtotal - (buy_price * quantity)) for each line item.
     *
     * @param \WC_Order $order The order object.
     * @return float The total net profit.
     */
    public static function calculate_order_net_profit( $order ): float {
        $total_profit = 0.0;

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_variation_id() ?: $item->get_product_id();
            $quantity   = $item->get_quantity();
            $line_total = (float) $item->get_total(); // Price paid for this line (after discounts, excludes tax).

            // Get buy price from product meta.
            $buy_price = get_post_meta( $product_id, '_ekusey_buy_price', true );

            if ( $buy_price === '' || ! is_numeric( $buy_price ) ) {
                // If no buy price set, assume zero cost (full profit).
                $buy_price = 0;
            }

            $cost_for_line = (float) $buy_price * $quantity;
            $line_profit   = $line_total - $cost_for_line;

            $total_profit += $line_profit;
        }

        // Ensure profit is not negative.
        return max( 0.0, $total_profit );
    }

    // ------------------------------------------------------------------
    // Admin order display
    // ------------------------------------------------------------------

    /**
     * Show affiliate info and claim button in order admin.
     */
    public function display_order_ref( $order ): void {
        $ref_code = $order->get_meta( '_affiliate_ref_code' );
        if ( ! $ref_code ) {
            return;
        }

        $commission_amount = $order->get_meta( '_affiliate_commission_amount' );
        $commission_rate   = $order->get_meta( '_affiliate_commission_rate' );
        $commission_status = $order->get_meta( '_affiliate_commission_status' );

        if ( empty( $commission_status ) ) {
            $commission_status = 'pending';
            $order->update_meta_data( '_affiliate_commission_status', 'pending' );
            $order->save();
        }

        $net_profit_meta = $order->get_meta( '_affiliate_order_net_profit' );

        if ( empty( $commission_amount ) || empty( $net_profit_meta ) ) {
            $net_profit        = self::calculate_order_net_profit( $order );
            $commission_amount = $net_profit * self::COMMISSION_RATE;
            $commission_rate   = self::COMMISSION_RATE;

            $order->update_meta_data( '_affiliate_commission_amount', $commission_amount );
            $order->update_meta_data( '_affiliate_commission_rate', $commission_rate );
            $order->update_meta_data( '_affiliate_order_net_profit', $net_profit );
            $order->save();
        }

        echo '<div class="order_data_column" style="clear:both; padding-top:20px; border-top: 1px solid #ddd; margin-top: 20px;">';
        echo '<h3 style="font-size: 14px; margin-bottom: 12px;">' . esc_html__( 'Affiliate Information', 'ekusey-ecom' ) . '</h3>';
        echo '<p style="margin: 8px 0;"><strong>' . esc_html__( 'Referral Code:', 'ekusey-ecom' ) . '</strong> <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">' . esc_html( $ref_code ) . '</code></p>';

        if ( $commission_amount ) {
            $net_profit      = $order->get_meta( '_affiliate_order_net_profit' );
            $rate_percentage = ( $commission_rate * 100 ) . '%';

            if ( $net_profit ) {
                echo '<p style="margin: 8px 0;"><strong>' . esc_html__( 'Order Net Profit:', 'ekusey-ecom' ) . '</strong> ' . wc_price( $net_profit ) . '</p>';
            }
            echo '<p style="margin: 8px 0;"><strong>' . esc_html__( 'Commission Rate:', 'ekusey-ecom' ) . '</strong> ' . esc_html( $rate_percentage ) . ' ' . esc_html__( '(of net profit)', 'ekusey-ecom' ) . '</p>';
            echo '<p style="margin: 8px 0;"><strong>' . esc_html__( 'Commission Amount:', 'ekusey-ecom' ) . '</strong> <span style="font-size: 16px; color: #2c3e50; font-weight: 600;">' . wc_price( $commission_amount ) . '</span></p>';

            $status_colors = [
                'pending' => '#ff9800',
                'claimed' => '#2196F3',
                'paid'    => '#4CAF50',
            ];
            $status_color = $status_colors[ $commission_status ] ?? '#999';

            echo '<p style="margin: 8px 0;"><strong>' . esc_html__( 'Status:', 'ekusey-ecom' ) . '</strong> ';
            echo '<span style="background: ' . $status_color . '; color: white; padding: 4px 10px; border-radius: 3px; font-size: 11px; text-transform: uppercase; font-weight: 600;">' . esc_html( $commission_status ) . '</span>';
            echo '</p>';

            if ( $commission_status === 'pending' ) {
                echo '<div style="margin-top: 15px; padding-top: 12px; border-top: 1px solid #e5e5e5;">';
                echo '<button type="button" class="button button-primary button-large affiliate-claim-commission" data-order-id="' . esc_attr( $order->get_id() ) . '" data-affiliate-code="' . esc_attr( $ref_code ) . '" data-commission="' . esc_attr( $commission_amount ) . '" style="width: 100%; text-align: center; height: 36px;">';
                echo '<span class="dashicons dashicons-yes" style="margin-top: 7px;"></span> ';
                echo esc_html__( 'Claim Commission', 'ekusey-ecom' );
                echo '</button>';
                echo '<span class="spinner" style="float: none; margin: 8px auto; display: none;"></span>';
                echo '<div class="affiliate-claim-message" style="margin-top: 10px;"></div>';
                echo '</div>';
            } elseif ( $commission_status === 'claimed' ) {
                echo '<div style="margin-top: 15px; padding: 10px; background: #e3f2fd; border-left: 3px solid #2196F3; border-radius: 3px;">';
                echo '<span class="dashicons dashicons-yes-alt" style="color: #2196F3;"></span> ';
                echo '<span style="color: #1976d2; font-weight: 500;">' . esc_html__( 'Commission has been claimed', 'ekusey-ecom' ) . '</span>';
                echo '</div>';
            } elseif ( $commission_status === 'paid' ) {
                echo '<div style="margin-top: 15px; padding: 10px; background: #e8f5e9; border-left: 3px solid #4CAF50; border-radius: 3px;">';
                echo '<span class="dashicons dashicons-yes" style="color: #4CAF50;"></span> ';
                echo '<span style="color: #388e3c; font-weight: 500;">' . esc_html__( 'Commission has been paid', 'ekusey-ecom' ) . '</span>';
                echo '</div>';
            }
        }

        echo '</div>';
    }

    // ------------------------------------------------------------------
    // Admin scripts (claim button JS)
    // ------------------------------------------------------------------

    /**
     * Inline script for the claim commission button on order pages.
     */
    public function admin_scripts(): void {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        $is_order_page = (
            $screen->id === 'shop_order' ||
            $screen->id === 'woocommerce_page_wc-orders' ||
            $screen->post_type === 'shop_order' ||
            ( isset( $_GET['page'] ) && $_GET['page'] === 'wc-orders' )
        );

        if ( ! $is_order_page ) {
            return;
        }

        $nonce = wp_create_nonce( 'affiliate_claim_commission' );
        ?>
        <script type="text/javascript">
        (function($) {
            $(document).ready(function() {
                $(document).on('click', '.affiliate-claim-commission', function(e) {
                    e.preventDefault();

                    var $button    = $(this);
                    var $spinner   = $button.siblings('.spinner');
                    var $container = $button.closest('div');
                    var $message   = $container.find('.affiliate-claim-message');

                    var orderId          = $button.data('order-id');
                    var commissionAmount = $button.data('commission');

                    if (!orderId) {
                        alert('Error: Order ID not found');
                        return;
                    }

                    if (!confirm('Claim commission of ' + commissionAmount + '?')) {
                        return;
                    }

                    $button.prop('disabled', true).css('opacity', '0.6');
                    $spinner.css({'display': 'inline-block', 'visibility': 'visible'}).addClass('is-active');
                    $message.html('<div style="padding: 8px; background: #fff8dc; border: 1px solid #ffd700; border-radius: 4px; margin-top: 10px;">Processing claim...</div>');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'ekusey_affiliate_claim_commission',
                            order_id: orderId,
                            nonce: '<?php echo esc_js( $nonce ); ?>'
                        },
                        success: function(response) {
                            $spinner.removeClass('is-active').hide();

                            if (response.success) {
                                var msg = response.data && response.data.message ? response.data.message : 'Commission claimed successfully!';
                                $message.html('<div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 4px; margin-top: 10px;"><strong>Success!</strong> ' + msg + '</div>');
                                setTimeout(function() { location.reload(); }, 2000);
                            } else {
                                var errMsg = response.data && response.data.message ? response.data.message : 'Unknown error';
                                $message.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin-top: 10px;"><strong>Error!</strong> ' + errMsg + '</div>');
                                $button.prop('disabled', false).css('opacity', '1');
                            }
                        },
                        error: function(xhr, status, error) {
                            $spinner.removeClass('is-active').hide();
                            $message.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin-top: 10px;"><strong>Error!</strong> ' + status + '<br><small>' + error + '</small></div>');
                            $button.prop('disabled', false).css('opacity', '1');
                        }
                    });
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    // ------------------------------------------------------------------
    // Bulk actions
    // ------------------------------------------------------------------

    /**
     * Add "Mark Commission as Paid" bulk action.
     */
    public function add_bulk_actions( array $actions ): array {
        $actions['mark_commission_paid'] = __( 'Mark Commission as Paid', 'ekusey-ecom' );
        return $actions;
    }

    /**
     * Handle the bulk action.
     */
    public function handle_bulk_actions( string $redirect_to, string $action, array $post_ids ): string {
        if ( $action !== 'mark_commission_paid' ) {
            return $redirect_to;
        }

        $changed = 0;

        foreach ( $post_ids as $post_id ) {
            $order = wc_get_order( $post_id );
            if ( $order ) {
                $affiliate_code = $order->get_meta( '_affiliate_ref_code' );
                if ( $affiliate_code ) {
                    $order->update_meta_data( '_affiliate_commission_status', 'paid' );
                    $order->update_meta_data( '_affiliate_commission_paid_date', current_time( 'mysql' ) );
                    $order->save();
                    $changed++;
                }
            }
        }

        return add_query_arg( 'bulk_marked_paid', $changed, $redirect_to );
    }

    /**
     * Admin notice after bulk action.
     */
    public function bulk_action_admin_notice(): void {
        if ( empty( $_REQUEST['bulk_marked_paid'] ) ) {
            return;
        }

        $count = intval( $_REQUEST['bulk_marked_paid'] );
        printf(
            '<div class="notice notice-success is-dismissible"><p>' .
            esc_html(
                _n(
                    '%s commission marked as paid.',
                    '%s commissions marked as paid.',
                    $count,
                    'ekusey-ecom'
                )
            ) .
            '</p></div>',
            $count
        );
    }
}
