<?php

namespace EkuseyEcom\Api;

use EkuseyEcom\Helper;
use EkuseyEcom\ProductProfit;
use WP_Error;
use WP_REST_Request;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST endpoint: GET /ekusey/v1/products
 */
class Products {

    /**
     * Register REST routes.
     */
    public function register(): void {
        register_rest_route( 'ekusey/v1', '/products', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_products' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Handle GET /ekusey/v1/products.
     */
    public function get_products( WP_REST_Request $request ) {
        if ( ! function_exists( 'wc_get_product' ) ) {
            return new WP_Error(
                'woocommerce_missing',
                'WooCommerce is not active.',
                [ 'status' => 500 ]
            );
        }

        $brand_tax = Helper::detect_brand_taxonomy();
        if ( ! $brand_tax ) {
            return new WP_Error(
                'brand_taxonomy_not_found',
                'Brand taxonomy not found for products. (No taxonomy containing "brand" detected.)',
                [ 'status' => 500 ]
            );
        }

        $per_page = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?: 12 ) ) );
        $page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
        $search   = (string) ( $request->get_param( 'search' ) ?: '' );
        $brand_slug = (string) ( $request->get_param( 'brand' ) ?: '' );

        // include_variations default ON.
        $include_variations = $request->get_param( 'include_variations' );
        $include_variations = ( $include_variations === null ) ? true : (bool) intval( $include_variations );

        $tax_query = [];
        if ( $brand_slug !== '' ) {
            $tax_query[] = [
                'taxonomy' => $brand_tax,
                'field'    => 'slug',
                'terms'    => [ $brand_slug ],
            ];
        }

        $q = new WP_Query( [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            's'              => $search,
            'tax_query'      => $tax_query,
        ] );

        $items = [];

        foreach ( $q->posts as $post ) {
            $product = wc_get_product( $post->ID );
            if ( ! $product ) {
                continue;
            }

            // Brand terms.
            $brand_terms = get_the_terms( $post->ID, $brand_tax );
            if ( is_wp_error( $brand_terms ) || empty( $brand_terms ) ) {
                $brand_terms = [];
            }

            // If ANY brand is disabled, exclude this product.
            $exclude    = false;
            $brands_out = [];

            foreach ( $brand_terms as $t ) {
                if ( Helper::is_term_disabled_brand( $t->term_id ) ) {
                    $exclude = true;
                    break;
                }
                $brands_out[] = [
                    'id'   => (int) $t->term_id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                ];
            }

            if ( $exclude ) {
                continue;
            }

            // Product main image.
            $image_id  = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : null;

            // Attributes.
            $attributes_out = $this->get_product_attributes( $product );

            // Variations.
            $variations_out = [];
            if ( $include_variations && $product->is_type( 'variable' ) ) {
                $variations_out = $this->get_variations( $product );
            }

            $profit_fields = $this->get_profit_fields( $product->get_id() );

            $items[] = [
                'id'        => (int) $product->get_id(),
                'type'      => $product->get_type(),
                'name'      => $product->get_name(),
                'slug'      => $product->get_slug(),
                'permalink' => $product->get_permalink(),

                'price'         => $product->get_price(),
                'regular_price' => $product->get_regular_price(),
                'sale_price'    => $product->get_sale_price(),
                'price_html'    => $product->get_price_html(),

                'buy_price'  => $profit_fields['buy_price'],
                'sale_price' => $profit_fields['sale_price'],
                'net_profit' => $profit_fields['net_profit'],

                'in_stock'     => (bool) $product->is_in_stock(),
                'stock_status' => $product->get_stock_status(),

                'image' => [
                    'id'  => $image_id ? (int) $image_id : null,
                    'url' => $image_url,
                ],

                'brands'     => $brands_out,
                'attributes' => $attributes_out,
                'variations' => $variations_out,
            ];
        }

        return rest_ensure_response( [
            'ok'             => true,
            'brand_taxonomy' => $brand_tax,
            'page'           => $page,
            'per_page'       => $per_page,
            'found_posts'    => (int) $q->found_posts,
            'total_pages'    => (int) $q->max_num_pages,
            'count'          => count( $items ),
            'items'          => $items,
        ] );
    }

    /**
     * Return product attributes in a frontend-friendly format.
     */
    private function get_product_attributes( $product ): array {
        $out = [];

        foreach ( $product->get_attributes() as $attr ) {
            if ( ! is_a( $attr, 'WC_Product_Attribute' ) ) {
                continue;
            }

            $name   = $attr->get_name();
            $is_tax = $attr->is_taxonomy();

            $options = [];

            if ( $is_tax ) {
                $terms = $attr->get_terms();
                if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                    foreach ( $terms as $t ) {
                        $options[] = [
                            'id'   => (int) $t->term_id,
                            'name' => $t->name,
                            'slug' => $t->slug,
                        ];
                    }
                }
            } else {
                foreach ( $attr->get_options() as $opt ) {
                    $options[] = (string) $opt;
                }
            }

            $out[] = [
                'name'        => $name,
                'label'       => wc_attribute_label( $name ),
                'is_taxonomy' => (bool) $is_tax,
                'variation'   => (bool) $attr->get_variation(),
                'visible'     => (bool) $attr->get_visible(),
                'options'     => $options,
            ];
        }

        return $out;
    }

    /**
     * Return all variations for a variable product.
     */
    private function get_variations( $product ): array {
        $out = [];

        $variation_ids = $product->get_children();

        foreach ( $variation_ids as $vid ) {
            $v = wc_get_product( $vid );
            if ( ! $v || ! is_a( $v, 'WC_Product_Variation' ) ) {
                continue;
            }

            // Variation image (fallback to parent if missing).
            $img_id = $v->get_image_id();
            if ( ! $img_id ) {
                $img_id = $product->get_image_id();
            }
            $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'full' ) : null;

            // Variation attributes.
            $attrs = [];
            foreach ( $v->get_attributes() as $k => $val ) {
                $attrs[] = [
                    'key'   => $k,
                    'label' => wc_attribute_label( str_replace( 'attribute_', '', $k ) ),
                    'value' => $val,
                ];
            }

            $profit_fields = $this->get_profit_fields( $v->get_id() );

            $out[] = [
                'id'             => (int) $v->get_id(),
                'sku'            => $v->get_sku(),
                'price'          => $v->get_price(),
                'regular_price'  => $v->get_regular_price(),
                'sale_price'     => $v->get_sale_price(),
                'price_html'     => $v->get_price_html(),

                'buy_price'      => $profit_fields['buy_price'],
                'sale_price'     => $profit_fields['sale_price'],
                'net_profit'     => $profit_fields['net_profit'],

                'in_stock'       => (bool) $v->is_in_stock(),
                'stock_status'   => $v->get_stock_status(),
                'stock_quantity' => $v->get_stock_quantity(),

                'image' => [
                    'id'  => $img_id ? (int) $img_id : null,
                    'url' => $img_url,
                ],

                'attributes' => $attrs,
            ];
        }

        return $out;
    }

    /**
     * Profit fields for a product or variation.
     */
    private function get_profit_fields( int $product_id ): array {
        $buy_price  = get_post_meta( $product_id, ProductProfit::META_BUY_PRICE, true );
        $sale_price = get_post_meta( $product_id, ProductProfit::META_SALE_PRICE, true );
        $discount   = get_post_meta( $product_id, ProductProfit::META_DISCOUNT, true );

        $buy_price  = ( $buy_price !== '' ) ? $buy_price : null;
        $sale_price = ( $sale_price !== '' ) ? $sale_price : null;
        $discount   = ( $discount !== '' ) ? $discount : null;

        $net_profit = null;
        if ( $buy_price !== null && $sale_price !== null && is_numeric( $buy_price ) && is_numeric( $sale_price ) ) {
            $effective_sale = (float) $sale_price;
            if ( $discount !== null && is_numeric( $discount ) ) {
                $effective_sale = (float) $sale_price * ( 1 - ( (float) $discount / 100 ) );
            }
            $net_profit = number_format( $effective_sale - (float) $buy_price, 2, '.', '' );
        }

        return [
            'buy_price'  => $buy_price,
            'sale_price' => $sale_price,
            'discount_percent' => $discount,
            'net_profit' => $net_profit,
        ];
    }
}
