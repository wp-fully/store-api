<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Store_API_Routes
 * تسجيل كل الـ REST Endpoints
 */
class Store_API_Routes {

    private const NS = 'store/v1';

    public function register(): void {

        $categories = new Store_API_Categories();
        $products   = new Store_API_Products();
        $orders     = new Store_API_Orders();

        // ① GET /categories
        register_rest_route( self::NS, '/categories', [
            'methods'             => 'GET',
            'callback'            => [ $categories, 'get_categories' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        // ② GET /categories/{id}/products
        register_rest_route( self::NS, '/categories/(?P<id>\\d+)/products', [
            'methods'             => 'GET',
            'callback'            => [ $categories, 'get_category_products' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args' => [
                'id'       => [ 'required' => true, 'validate_callback' => fn( $v ) => is_numeric( $v ) ],
                'page'     => [ 'default'  => 1,    'sanitize_callback' => 'absint' ],
                'per_page' => [ 'default'  => 20,   'sanitize_callback' => 'absint' ],
            ],
        ] );

        // ③ GET /products/{id}
        register_rest_route( self::NS, '/products/(?P<id>\\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $products, 'get_single_product' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args' => [
                'id' => [ 'required' => true, 'validate_callback' => fn( $v ) => is_numeric( $v ) ],
            ],
        ] );

        // ④ POST /cart/add
        register_rest_route( self::NS, '/cart/add', [
            'methods'             => 'POST',
            'callback'            => [ $products, 'cart_add' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args' => [
                'product_id' => [ 'required' => true, 'sanitize_callback' => 'absint' ],
                'quantity'   => [ 'default'  => 1,    'sanitize_callback' => 'absint' ],
            ],
        ] );

        // ⑤ POST /checkout
        register_rest_route( self::NS, '/checkout', [
            'methods'             => 'POST',
            'callback'            => [ $orders, 'checkout' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args' => [
                'first_name'     => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'phone'          => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'payment_method' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'items'          => [ 'required' => true ],
            ],
        ] );

        // ⑥ GET /orders/{id}/status
        register_rest_route( self::NS, '/orders/(?P<id>\\d+)/status', [
            'methods'             => 'GET',
            'callback'            => [ $orders, 'get_order_status' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args' => [
                'id' => [ 'required' => true, 'validate_callback' => fn( $v ) => is_numeric( $v ) ],
            ],
        ] );
    }

    // ──────────────────────────────────────────────────────────
    // Permission Callback
    // ──────────────────────────────────────────────────────────
    public function check_permission( WP_REST_Request $request ): bool|WP_Error {

        $key = $request->get_header( 'X-Store-Api-Key' )
            ?: (string) $request->get_param( 'api_key' );

        if ( Store_API_Config::verify_api_key( $key ) ) {
            return true;
        }

        return new WP_Error(
            'unauthorized',
            'غير مصرح. أرسل X-Store-Api-Key الصحيح في الـ Header.',
            [ 'status' => 401 ]
        );
    }
}
