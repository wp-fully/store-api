<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Store_API_Products
 * ③ GET  /store/v1/products/{id}
 * ④ POST /store/v1/cart/add
 * ⑤ GET  /store/v1/categories/{id}/products ← مع تطبيق ترتيب المنتجات المخصص
 * Helper: format_product()
 * Helper: get_price_data()
 * Helper: apply_override()   ← جديد — تطبيق التخصيصات دون المساس بالموقع
 */
class Store_API_Products {

    // ──────────────────────────────────────────────────────────
    // ③ GET /store/v1/products/{id}
    // ──────────────────────────────────────────────────────────
    public function get_single_product( WP_REST_Request $request ): WP_REST_Response|WP_Error {

        $product_id = (int) $request['id'];

        if ( Store_API_Config::is_product_hidden( $product_id ) ) {
            return new WP_Error( 'not_found', 'المنتج غير موجود', [ 'status' => 404 ] );
        }

        $product = wc_get_product( $product_id );

        if ( ! $product || $product->get_status() !== 'publish' ) {
            return new WP_Error( 'not_found', 'المنتج غير موجود', [ 'status' => 404 ] );
        }

        return rest_ensure_response( [
            'success' => true,
            'data'    => $this->format_product( $product ),
        ] );
    }

    // ──────────────────────────────────────────────────────────
    // ④ POST /store/v1/cart/add
    // ──────────────────────────────────────────────────────────
    public function cart_add( WP_REST_Request $request ): WP_REST_Response|WP_Error {

        $product_id = (int) $request->get_param( 'product_id' );
        $quantity   = max( 1, (int) $request->get_param( 'quantity' ) );

        if ( Store_API_Config::is_product_hidden( $product_id ) ) {
            return new WP_Error( 'not_found', 'المنتج غير موجود', [ 'status' => 404 ] );
        }

        $product = wc_get_product( $product_id );

        if ( ! $product || $product->get_status() !== 'publish' ) {
            return new WP_Error( 'not_found', 'المنتج غير موجود', [ 'status' => 404 ] );
        }

        if ( $product->get_stock_status() !== 'instock' ) {
            return new WP_Error( 'out_of_stock', 'المنتج غير متوفر في المخزون', [ 'status' => 400 ] );
        }

        $override   = Store_API_Config::get_product_override( $product_id );
        $price_data = $this->get_price_data( $product, $override );

        return rest_ensure_response( [
            'success' => true,
            'message' => 'المنتج صالح للإضافة',
            'product' => [
                'id'            => $product->get_id(),
                'name'          => $override['name'] ?? $product->get_name(),
                'type'          => $product->get_type(),
                'price'         => $price_data['price'],
                'price_range'   => $price_data['price_range'],
                'status'        => $product->get_stock_status(),
                'quantity'      => $quantity,
            ],
        ] );
    }

    // ──────────────────────────────────────────────────────────
    // ⑤ GET /store/v1/categories/{id}/products
    // مع تطبيق ترتيب المنتجات المخصص per-category
    // ──────────────────────────────────────────────────────────
    public function get_category_products( WP_REST_Request $request ): WP_REST_Response|WP_Error {

        $cat_id   = (int) $request['id'];
        $page     = max( 1, (int) $request->get_param( 'page' ) );
        $per_page = (int) $request->get_param( 'per_page' ) ?: 20;

        // التحقق من صحة التصنيف
        $term = get_term( $cat_id, 'product_cat' );
        if ( ! $term || is_wp_error( $term ) ) {
            return new WP_Error( 'invalid_category', 'التصنيف غير موجود', [ 'status' => 404 ] );
        }

        // جلب المنتجات الأساسية
        $products = wc_get_products( [
            'status'    => 'publish',
            'category'  => [ $term->slug ],
            'limit'     => $per_page,
            'page'      => $page,
            'paginate'  => true,
        ] );

        $total_products = $products->total;
        $formatted      = [];

        // تنسيق كل منتج
        foreach ( $products->products as $product ) {
            if ( Store_API_Config::is_product_hidden( $product->get_id() ) ) {
                continue;
            }
            $formatted[] = $this->format_product_list( $product );
        }

        // ── تطبيق الترتيب المخصص ───────────────────────────────
        $sort_order = Store_API_Config::get_product_sort_order( $cat_id );

        if ( ! empty( $sort_order ) ) {
            usort( $formatted, function ( $a, $b ) use ( $sort_order ) {
                $order_a = $sort_order[ (string) $a['id'] ] ?? PHP_INT_MAX;
                $order_b = $sort_order[ (string) $b['id'] ] ?? PHP_INT_MAX;
                return $order_a <=> $order_b;
            } );
        }

        return rest_ensure_response( [
            'success' => true,
            'data'    => [
                'products'     => $formatted,
                'total'        => $total_products,
                'page'         => $page,
                'per_page'     => $per_page,
                'total_pages'  => (int) ceil( $total_products / $per_page ),
            ],
        ] );
    }

    // ──────────────────────────────────────────────────────────
    // Helper: تنسيق منتج لقائمة التصنيفات (مختصر)
    // ──────────────────────────────────────────────────────────
    public function format_product_list( WC_Product $product ): array {

        $override = Store_API_Config::get_product_override( $product->get_id() );
        $price_data = $this->get_price_data( $product, $override );

        $thumb_id = $product->get_image_id();
        $thumb_src = '';
        if ( $thumb_id ) {
            $thumb = wp_get_attachment_image_src( (int) $thumb_id, 'thumbnail' );
            $thumb_src = $thumb ? $thumb[0] : '';
        }

        return [
            'id'             => $product->get_id(),
            'name'           => $override['name'] ?? $product->get_name(),
            'price'          => $price_data['price'],
            'type'           => $product->get_type(),
            'status'         => $product->get_stock_status(),
            'thumbnail'      => $thumb_src,
        ];
    }

    // ──────────────────────────────────────────────────────────
    // Helper: تنسيق بيانات المنتج الكاملة مع التخصيصات
    // ──────────────────────────────────────────────────────────
    public function format_product( WC_Product $product ): array {

        $override = Store_API_Config::get_product_override( $product->get_id() );

        // ── الصور ─────────────────────────────────────────────
        $images    = [];
        $image_ids = array_filter( array_merge(
            [ $product->get_image_id() ],
            $product->get_gallery_image_ids()
        ) );

        foreach ( $image_ids as $img_id ) {
            $full  = wp_get_attachment_image_src( (int) $img_id, 'full' );
            $thumb = wp_get_attachment_image_src( (int) $img_id, 'thumbnail' );
            $alt   = get_post_meta( (int) $img_id, '_wp_attachment_image_alt', true );

            if ( $full ) {
                $images[] = [
                    'id'        => (int) $img_id,
                    'src'       => $full[0],
                    'alt'       => $alt ?: $product->get_name(),
                    'thumbnail' => $thumb ? $thumb[0] : $full[0],
                ];
            }
        }

        // ── التصنيفات بالأسماء المخصصة ────────────────────────
        $categories = [];
        $config_map = [];

        foreach ( Store_API_Config::categories_config_all() as $entry ) {
            if ( ! empty( $entry['display_name'] ) ) {
                $config_map[ (int) $entry['id'] ] = $entry['display_name'];
            }
        }

        foreach ( $product->get_category_ids() as $tid ) {
            $term = get_term( (int) $tid, 'product_cat' );
            if ( ! $term || is_wp_error( $term ) ) continue;
            $categories[] = [
                'id'   => $term->term_id,
                'name' => $config_map[ $term->term_id ] ?? $term->name,
            ];
        }

        // ── السعر مع التخصيص ──────────────────────────────────
        $price_data = $this->get_price_data( $product, $override );

        // ── الاسم والوصف مع التخصيص ───────────────────────────
        $name             = $override['name']        ?? $product->get_name();
        $description      = $override['description'] ?? wp_strip_all_tags( $product->get_description() );
        $short_description = wp_strip_all_tags( $product->get_short_description() );

        // ── Variations ────────────────────────────────────────
        $variations = [];

        if ( $product->get_type() === 'variable' && $product instanceof WC_Product_Variable ) {

            foreach ( $product->get_available_variations() as $var_data ) {

                $var_product = wc_get_product( $var_data['variation_id'] );
                if ( ! $var_product || ! $var_product->is_in_stock() ) continue;

                $var_image  = null;
                $var_img_id = $var_product->get_image_id() ?: $product->get_image_id();

                if ( $var_img_id ) {
                    $var_full  = wp_get_attachment_image_src( (int) $var_img_id, 'full' );
                    $var_thumb = wp_get_attachment_image_src( (int) $var_img_id, 'thumbnail' );
                    if ( $var_full ) {
                        $var_image = [
                            'id'        => (int) $var_img_id,
                            'src'       => $var_full[0],
                            'thumbnail' => $var_thumb ? $var_thumb[0] : $var_full[0],
                        ];
                    }
                }

                // تخصيص سعر الـ variation لو موجود
                $var_override = Store_API_Config::get_product_override( $var_product->get_id() );
                $var_price    = $var_override['price'] ?? $var_product->get_price();

                $variations[] = [
                    'id'            => $var_product->get_id(),
                    'attributes'    => $var_data['attributes'],
                    'price'         => $var_price,
                    'regular_price' => $var_product->get_regular_price(),
                    'sale_price'    => $var_product->get_sale_price() ?: null,
                    'on_sale'       => $var_product->is_on_sale(),
                    'in_stock'      => $var_product->is_in_stock(),
                    'image'         => $var_image,
                ];
            }
        }

        // ── النتيجة ───────────────────────────────────────────
        return [
            'id'               => $product->get_id(),
            'name'             => $name,
            'type'             => $product->get_type(),
            'status'           => $product->get_stock_status(),
            'description'      => $description,
            'short_description' => $short_description,
            'price'            => $price_data['price'],
            'regular_price'    => $price_data['regular_price'],
            'sale_price'       => $price_data['sale_price'],
            'price_range'      => $price_data['price_range'],
            'on_sale'          => $product->is_on_sale(),
            'categories'       => $categories,
            'images'           => $images,
            'variations'       => $variations,
            'related_ids'      => array_values(
                                     array_slice(
                                         wc_get_related_products( $product->get_id(), 6 ),
                                         0, 6
                                     )
                                 ),
        ];
    }

    // ──────────────────────────────────────────────────────────
    // Helper: بيانات السعر مع تطبيق التخصيص
    // ──────────────────────────────────────────────────────────
    public function get_price_data( WC_Product $product, array $override = [] ): array {

        // لو في override للسعر يطغى على كل شيء
        if ( ! empty( $override['price'] ) ) {
            return [
                'price'         => (string) $override['price'],
                'regular_price' => $product->get_regular_price(),
                'sale_price'    => null,
                'price_range'   => null,
            ];
        }

        if ( $product->get_type() === 'variable' && $product instanceof WC_Product_Variable ) {

            $min_price   = $product->get_variation_price( 'min' );
            $max_price   = $product->get_variation_price( 'max' );
            $min_regular = $product->get_variation_regular_price( 'min' );
            $min_sale    = $product->get_variation_sale_price( 'min' );

            return [
                'price'         => $min_price,
                'regular_price' => $min_regular,
                'sale_price'    => $min_sale ?: null,
                'price_range'   => ( $min_price !== $max_price )
                                    ? [ 'min' => $min_price, 'max' => $max_price ]
                                    : null,
            ];
        }

        return [
            'price'         => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price'    => $product->get_sale_price() ?: null,
            'price_range'   => null,
        ];
    }
}
