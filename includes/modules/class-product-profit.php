<?php

namespace EkuseyEcom;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Product Profit — buy/sale price fields + net profit display.
 */
class ProductProfit {

    const META_BUY_PRICE  = '_ekusey_buy_price';
    const META_SALE_PRICE = '_ekusey_sale_price';
    const META_DISCOUNT   = '_ekusey_discount_percent';

    public static function init(): void {
        if ( ! function_exists( 'wc_get_product' ) ) {
            return;
        }

        $self = new self();

        add_action( 'woocommerce_product_options_pricing', [ $self, 'add_simple_fields' ] );
        add_action( 'woocommerce_admin_process_product_object', [ $self, 'save_simple_fields' ] );

        add_action( 'woocommerce_variation_options_pricing', [ $self, 'add_variation_fields' ], 10, 3 );
        add_action( 'woocommerce_save_product_variation', [ $self, 'save_variation_fields' ], 10, 2 );

        add_action( 'admin_head', [ $self, 'admin_styles' ] );
        add_action( 'admin_footer', [ $self, 'admin_scripts' ] );
    }

    /**
     * Add buy/sale fields for simple products.
     */
    public function add_simple_fields(): void {
        global $post;

        $buy_price  = get_post_meta( $post->ID, self::META_BUY_PRICE, true );
        $sale_price = get_post_meta( $post->ID, self::META_SALE_PRICE, true );
        $discount   = get_post_meta( $post->ID, self::META_DISCOUNT, true );

        woocommerce_wp_text_input( [
            'id'                => 'ekusey_buy_price',
            'label'             => esc_html__( 'Buy price', 'ekusey-ecom' ),
            'type'              => 'number',
            'class'             => 'short ekusey-buy-price',
            'value'             => $buy_price,
            'custom_attributes' => [
                'step' => '0.01',
                'min'  => '0',
            ],
        ] );

        woocommerce_wp_text_input( [
            'id'                => 'ekusey_sale_price',
            'label'             => esc_html__( 'Sale price', 'ekusey-ecom' ),
            'type'              => 'number',
            'class'             => 'short ekusey-sale-price',
            'value'             => $sale_price,
            'custom_attributes' => [
                'step' => '0.01',
                'min'  => '0',
            ],
        ] );

        woocommerce_wp_text_input( [
            'id'                => 'ekusey_discount_percent',
            'label'             => esc_html__( 'Discount (%)', 'ekusey-ecom' ),
            'type'              => 'number',
            'class'             => 'short ekusey-discount-percent',
            'value'             => $discount,
            'custom_attributes' => [
                'step' => '0.01',
                'min'  => '0',
                'max'  => '100',
            ],
        ] );

        $profit_display = $this->format_profit_display( $sale_price, $buy_price, $discount );

        echo '<p class="form-field ekusey-net-profit-field">';
        echo '<label>' . esc_html__( 'Net profit', 'ekusey-ecom' ) . '</label>';
        echo '<span class="ekusey-net-profit">' . esc_html( $profit_display ) . '</span>';
        echo '</p>';
    }

    /**
     * Save buy/sale fields for simple products.
     */
    public function save_simple_fields( $product ): void {
        if ( ! $product ) {
            return;
        }

        $buy_price  = isset( $_POST['ekusey_buy_price'] ) ? wc_format_decimal( wp_unslash( $_POST['ekusey_buy_price'] ) ) : '';
        $sale_price = isset( $_POST['ekusey_sale_price'] ) ? wc_format_decimal( wp_unslash( $_POST['ekusey_sale_price'] ) ) : '';
        $discount   = isset( $_POST['ekusey_discount_percent'] ) ? wc_format_decimal( wp_unslash( $_POST['ekusey_discount_percent'] ) ) : '';

        if ( $this->is_invalid_price_pair( $buy_price, $sale_price ) ) {
            $this->add_admin_error( esc_html__( 'Sale price must be greater than or equal to buy price.', 'ekusey-ecom' ) );
            return;
        }

        if ( $buy_price !== '' ) {
            $product->update_meta_data( self::META_BUY_PRICE, $buy_price );
        } else {
            $product->delete_meta_data( self::META_BUY_PRICE );
        }

        if ( $sale_price !== '' ) {
            $product->update_meta_data( self::META_SALE_PRICE, $sale_price );
        } else {
            $product->delete_meta_data( self::META_SALE_PRICE );
        }

        if ( $discount !== '' ) {
            $product->update_meta_data( self::META_DISCOUNT, $discount );
        } else {
            $product->delete_meta_data( self::META_DISCOUNT );
        }

        if ( $sale_price !== '' ) {
            $product->set_regular_price( $sale_price );
        } else {
            $product->set_regular_price( '' );
        }

        $discounted = $this->get_discounted_price( $sale_price, $discount );
        if ( $discounted !== null ) {
            $product->set_sale_price( $discounted );
            $product->set_price( $discounted );
        } else {
            $product->set_sale_price( '' );
            $product->set_price( $sale_price !== '' ? $sale_price : '' );
        }
    }

    /**
     * Add buy/sale fields for variations.
     */
    public function add_variation_fields( $loop, $variation_data, $variation ): void {
        $buy_price  = get_post_meta( $variation->ID, self::META_BUY_PRICE, true );
        $sale_price = get_post_meta( $variation->ID, self::META_SALE_PRICE, true );
        $discount   = get_post_meta( $variation->ID, self::META_DISCOUNT, true );

        woocommerce_wp_text_input( [
            'id'                => 'ekusey_buy_price[' . $loop . ']',
            'name'              => 'ekusey_buy_price[' . $loop . ']',
            'label'             => esc_html__( 'Buy price', 'ekusey-ecom' ),
            'type'              => 'number',
            'class'             => 'short ekusey-buy-price',
            'value'             => $buy_price,
            'wrapper_class'     => 'form-row form-row-first',
            'custom_attributes' => [
                'step' => '0.01',
                'min'  => '0',
            ],
        ] );

        woocommerce_wp_text_input( [
            'id'                => 'ekusey_sale_price[' . $loop . ']',
            'name'              => 'ekusey_sale_price[' . $loop . ']',
            'label'             => esc_html__( 'Sale price', 'ekusey-ecom' ),
            'type'              => 'number',
            'class'             => 'short ekusey-sale-price',
            'value'             => $sale_price,
            'wrapper_class'     => 'form-row form-row-last',
            'custom_attributes' => [
                'step' => '0.01',
                'min'  => '0',
            ],
        ] );

        woocommerce_wp_text_input( [
            'id'                => 'ekusey_discount_percent[' . $loop . ']',
            'name'              => 'ekusey_discount_percent[' . $loop . ']',
            'label'             => esc_html__( 'Discount (%)', 'ekusey-ecom' ),
            'type'              => 'number',
            'class'             => 'short ekusey-discount-percent',
            'value'             => $discount,
            'wrapper_class'     => 'form-row form-row-first',
            'custom_attributes' => [
                'step' => '0.01',
                'min'  => '0',
                'max'  => '100',
            ],
        ] );

        $profit_display = $this->format_profit_display( $sale_price, $buy_price, $discount );

        echo '<p class="form-row form-row-wide ekusey-net-profit-field">';
        echo '<label>' . esc_html__( 'Net profit', 'ekusey-ecom' ) . '</label>';
        echo '<span class="ekusey-net-profit">' . esc_html( $profit_display ) . '</span>';
        echo '</p>';
    }

    /**
     * Save variation fields.
     */
    public function save_variation_fields( $variation_id, $loop ): void {
        $buy_prices  = $_POST['ekusey_buy_price'] ?? [];
        $sale_prices = $_POST['ekusey_sale_price'] ?? [];
        $discounts   = $_POST['ekusey_discount_percent'] ?? [];

        $buy_price  = isset( $buy_prices[ $loop ] ) ? wc_format_decimal( wp_unslash( $buy_prices[ $loop ] ) ) : '';
        $sale_price = isset( $sale_prices[ $loop ] ) ? wc_format_decimal( wp_unslash( $sale_prices[ $loop ] ) ) : '';
        $discount   = isset( $discounts[ $loop ] ) ? wc_format_decimal( wp_unslash( $discounts[ $loop ] ) ) : '';

        if ( $this->is_invalid_price_pair( $buy_price, $sale_price ) ) {
            $this->add_admin_error( sprintf(
                esc_html__( 'Variation %d: sale price must be greater than or equal to buy price.', 'ekusey-ecom' ),
                (int) ( $loop + 1 )
            ) );
            return;
        }

        if ( $buy_price !== '' ) {
            update_post_meta( $variation_id, self::META_BUY_PRICE, $buy_price );
        } else {
            delete_post_meta( $variation_id, self::META_BUY_PRICE );
        }

        if ( $sale_price !== '' ) {
            update_post_meta( $variation_id, self::META_SALE_PRICE, $sale_price );
        } else {
            delete_post_meta( $variation_id, self::META_SALE_PRICE );
        }

        if ( $discount !== '' ) {
            update_post_meta( $variation_id, self::META_DISCOUNT, $discount );
        } else {
            delete_post_meta( $variation_id, self::META_DISCOUNT );
        }

        if ( $sale_price !== '' ) {
            update_post_meta( $variation_id, '_regular_price', $sale_price );
        } else {
            delete_post_meta( $variation_id, '_regular_price' );
        }

        $discounted = $this->get_discounted_price( $sale_price, $discount );
        if ( $discounted !== null ) {
            update_post_meta( $variation_id, '_sale_price', $discounted );
            update_post_meta( $variation_id, '_price', $discounted );
        } else {
            delete_post_meta( $variation_id, '_sale_price' );
            if ( $sale_price !== '' ) {
                update_post_meta( $variation_id, '_price', $sale_price );
            } else {
                delete_post_meta( $variation_id, '_price' );
            }
        }
    }

    /**
     * Hide WooCommerce default sale price fields.
     */
    public function admin_styles(): void {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'product' ) {
            return;
        }
        ?>
        <style>
            #_sale_price,
            .sale_price_field,
            .variable_sale_price {
                display: block;
            }

            #_sale_price,
            .variable_sale_price {
                background-color: #f6f7f7;
                pointer-events: none;
            }
        </style>
        <?php
    }

    /**
     * Admin JS for live net profit updates.
     */
    public function admin_scripts(): void {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'product' ) {
            return;
        }

        ?>
        <script>
            jQuery(function ($) {
                const formatProfit = (value) => {
                    if (!isFinite(value)) {
                        return '--';
                    }
                    return value.toFixed(2);
                };

                const getScope = ($el) => {
                    const $variation = $el.closest('.woocommerce_variation');
                    if ($variation.length) {
                        return $variation;
                    }
                    return $el.closest('.options_group');
                };

                const getLoopIndex = ($el) => {
                    const name = $el.attr('name') || '';
                    const match = name.match(/\[(\d+)\]/);
                    return match ? match[1] : null;
                };

                const updateProfit = ($scope) => {
                    const buyVal = parseFloat($scope.find('.ekusey-buy-price').first().val());
                    const saleVal = parseFloat($scope.find('.ekusey-sale-price').first().val());
                    const discountVal = parseFloat($scope.find('.ekusey-discount-percent').first().val());
                    let effectiveSale = saleVal;
                    if (isFinite(saleVal) && isFinite(discountVal)) {
                        effectiveSale = saleVal * (1 - (discountVal / 100));
                    }
                    const profit = (isFinite(buyVal) && isFinite(effectiveSale)) ? (effectiveSale - buyVal) : NaN;
                    $scope.find('.ekusey-net-profit').text(formatProfit(profit));
                };

                const syncWooPrices = ($scope, $sourceField) => {
                    const saleVal = $scope.find('.ekusey-sale-price').first().val();
                    const discountVal = parseFloat($scope.find('.ekusey-discount-percent').first().val());
                    let discounted = '';
                    if (saleVal !== '' && isFinite(parseFloat(saleVal)) && isFinite(discountVal)) {
                        discounted = (parseFloat(saleVal) * (1 - (discountVal / 100))).toFixed(2);
                    }

                    if ($scope.hasClass('woocommerce_variation')) {
                        const loopIndex = getLoopIndex($sourceField);
                        const $regular = loopIndex !== null
                            ? $scope.find('input[name="variable_regular_price[' + loopIndex + ']"]')
                            : $scope.find('.variable_regular_price').first();
                        const $sale = loopIndex !== null
                            ? $scope.find('input[name="variable_sale_price[' + loopIndex + ']"]')
                            : $scope.find('.variable_sale_price').first();
                        $regular.val(saleVal).trigger('change');
                        $sale.val(discounted).trigger('change');
                    } else {
                        $('#_regular_price').val(saleVal).trigger('change');
                        $('#_sale_price').val(discounted).trigger('change');
                    }
                };

                const bind = ($context) => {
                    $context.find('.ekusey-buy-price, .ekusey-sale-price, .ekusey-discount-percent')
                        .off('input.ekusey change.ekusey')
                        .on('input.ekusey change.ekusey', function () {
                            const $scope = getScope($(this));
                            syncWooPrices($scope, $(this));
                            updateProfit($scope);
                        });
                };

                const renameWooSaleLabels = ($context) => {
                    $context.find('label[for="_sale_price"]').text('Discounted price');
                    $context.find('label[for^="variable_sale_price"]').text('Discounted price');
                    $context.find('.sale_price_field label').text('Discounted price');
                };

                const lockWooSaleInputs = ($context) => {
                    $context.find('#_sale_price, .variable_sale_price').prop('readonly', true);
                };

                bind($(document));
                renameWooSaleLabels($(document));
                lockWooSaleInputs($(document));

                $(document.body).on('woocommerce_variations_loaded woocommerce_variation_added', function () {
                    bind($(document));
                    renameWooSaleLabels($(document));
                    lockWooSaleInputs($(document));
                });
            });
        </script>
        <?php
    }

    /**
     * Format profit display for server-rendered values.
     */
    private function format_profit_display( $sale_price, $buy_price, $discount ): string {
        if ( $sale_price === '' || $buy_price === '' ) {
            return '--';
        }

        if ( ! is_numeric( $sale_price ) || ! is_numeric( $buy_price ) ) {
            return '--';
        }

        $effective_sale = $sale_price;
        if ( $discount !== '' && is_numeric( $discount ) ) {
            $effective_sale = (float) $sale_price * ( 1 - ( (float) $discount / 100 ) );
        }

        $profit = (float) $effective_sale - (float) $buy_price;

        return number_format( $profit, 2, '.', '' );
    }

    private function is_invalid_price_pair( $buy_price, $sale_price ): bool {
        if ( $buy_price === '' || $sale_price === '' ) {
            return false;
        }

        if ( ! is_numeric( $buy_price ) || ! is_numeric( $sale_price ) ) {
            return false;
        }

        return (float) $sale_price < (float) $buy_price;
    }

    private function get_discounted_price( $sale_price, $discount ): ?string {
        if ( $sale_price === '' || ! is_numeric( $sale_price ) ) {
            return null;
        }

        if ( $discount === '' || ! is_numeric( $discount ) ) {
            return null;
        }

        $discount = max( 0, min( 100, (float) $discount ) );
        $discounted = (float) $sale_price * ( 1 - ( $discount / 100 ) );

        return number_format( $discounted, 2, '.', '' );
    }

    private function add_admin_error( string $message ): void {
        if ( class_exists( 'WC_Admin_Meta_Boxes' ) && method_exists( 'WC_Admin_Meta_Boxes', 'add_error' ) ) {
            \WC_Admin_Meta_Boxes::add_error( $message );
            return;
        }

        add_action( 'admin_notices', function () use ( $message ) {
            echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
        } );
    }
}
