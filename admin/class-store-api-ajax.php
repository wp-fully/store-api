<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Store_API_Ajax
 * كل الـ AJAX Actions الخاصة بالـ Admin
 *
 * Actions:
 *  store_api_get_wc_categories   — جلب تصنيفات WooCommerce
 *  store_api_get_cat_products    — جلب منتجات تصنيف معين
 *  store_api_save_product        — حفظ override منتج
 *  store_api_save_categories     — حفظ إعدادات التصنيفات
 *  store_api_save_product_order  — حفظ ترتيب المنتجات لتصنيف ← ✅ جديد
 */
class Store_API_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_store_api_get_wc_categories', [ $this, 'get_wc_categories' ] );
        add_action( 'wp_ajax_store_api_get_cat_products',  [ $this, 'get_cat_products' ] );
        add_action( 'wp_ajax_store_api_save_product',      [ $this, 'save_product' ] );
        add_action( 'wp_ajax_store_api_save_categories',   [ $this, 'save_categories' ] );
        add_action( 'wp_ajax_store_api_save_product_order', [ $this, 'save_product_order' ] ); // ✅ جديد
    }

    // ──────────────────────────────────────────────────────────
    // Nonce Check
    // ──────────────────────────────────────────────────────────
    private function verify(): void {
        if (
            ! isset( $_POST['nonce'] )
            || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'store_api_nonce' )
        ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        }
    }

    // ──────────────────────────────────────────────────────────
    // ① جلب كل تصنيفات WooCommerce
    // ──────────────────────────────────────────────────────────
    public function get_wc_categories(): void {

        $this->verify();

        $terms = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
            'number'     => 0,
        ] );

        if ( is_wp_error( $terms ) ) {
            wp_send_json_error( [ 'message' => $terms->get_error_message() ] );
        }

        // الإعدادات الحالية
        $config_all = Store_API_Config::categories_config_all();
        $merged     = Store_API_Config::merged_categories();

        // خريطة الإعدادات الحالية بالـ ID
        $config_map = [];
        foreach ( $config_all as $entry ) {
            $config_map[ (int) $entry['id'] ] = $entry;
        }

        // IDs المدمجة
        $merged_ids = [];
        foreach ( $merged as $mc ) {
            foreach ( $mc['merge_ids'] as $mid ) {
                $merged_ids[] = (int) $mid;
            }
        }

        $data = [];
        foreach ( $terms as $term ) {

            $tid      = $term->term_id;
            $existing = $config_map[ $tid ] ?? null;
            $image_mode = ( isset( $existing['image_mode'] ) && $existing['image_mode'] === 'alternative' )
                ? 'alternative'
                : 'primary';
            $alternative_image = isset( $existing['alternative_image'] )
                ? esc_url_raw( (string) $existing['alternative_image'] )
                : '';

            // صورة التصنيف
            $thumb_id  = get_term_meta( $tid, 'thumbnail_id', true );
            $thumb_src = '';
            if ( $thumb_id ) {
                $thumb     = wp_get_attachment_image_src( (int) $thumb_id, 'thumbnail' );
                $thumb_src = $thumb ? $thumb[0] : '';
            }

            $data[] = [
                'id'           => $tid,
                'name'         => $term->name,
                'slug'         => $term->slug,
                'count'        => $term->count,
                'thumbnail'    => $thumb_src,
                'is_merged_sub'=> in_array( $tid, $merged_ids, true ),
                // من الإعدادات الحالية
                'visible'      => $existing['visible']      ?? false,
                'display_name' => $existing['display_name'] ?? '',
                'note'         => $existing['note']         ?? '',
                'image_mode'   => $image_mode,
                'alternative_image' => $alternative_image,
                'sort_order'   => $existing['sort_order']   ?? 999,
            ];
        }

        // ترتيب: المفعّلة أولاً حسب sort_order، ثم الباقية
        usort( $data, function ( $a, $b ) {
            if ( $a['visible'] !== $b['visible'] ) {
                return $a['visible'] ? -1 : 1;
            }
            return $a['sort_order'] <=> $b['sort_order'];
        } );

        wp_send_json_success( [
            'categories' => $data,
            'merged'     => $merged,
        ] );
    }

    // ──────────────────────────────────────────────────────────
    // ② جلب منتجات تصنيف معين
    // ──────────────────────────────────────────────────────────
    public function get_cat_products(): void {

        $this->verify();

        $cat_id   = isset( $_POST['cat_id'] ) ? (int) $_POST['cat_id'] : 0;
        $page     = isset( $_POST['page'] )   ? max( 1, (int) $_POST['page'] ) : 1;
        $per_page = 30;

        if ( ! $cat_id ) {
            wp_send_json_error( [ 'message' => 'cat_id مطلوب' ] );
        }

        $term = get_term( $cat_id, 'product_cat' );
        if ( ! $term || is_wp_error( $term ) ) {
            wp_send_json_error( [ 'message' => 'التصنيف غير موجود' ] );
        }

        $all_ids = wc_get_products( [
            'status'   => 'publish',
            'category' => [ $term->slug ],
            'limit'    => -1,
            'return'   => 'ids',
        ] );

        $total     = count( $all_ids );
        $page_ids  = array_slice( $all_ids, ( $page - 1 ) * $per_page, $per_page );
        $overrides = Store_API_Config::product_overrides();

        $products = [];
        foreach ( $page_ids as $pid ) {
            $product = wc_get_product( (int) $pid );
            if ( ! $product ) continue;

            $override   = $overrides[ (string) $pid ] ?? [];
            $thumb_id   = $product->get_image_id();
            $thumb_src  = '';
            if ( $thumb_id ) {
                $thumb     = wp_get_attachment_image_src( (int) $thumb_id, 'thumbnail' );
                $thumb_src = $thumb ? $thumb[0] : '';
            }

            $products[] = [
                'id'           => $pid,
                'name'         => $product->get_name(),
                'price'        => $product->get_price(),
                'type'         => $product->get_type(),
                'stock_status' => $product->get_stock_status(),
                'thumbnail'    => $thumb_src,
                // overrides
                'hidden'       => $override['hidden']      ?? false,
                'custom_name'  => $override['name']        ?? '',
                'custom_price' => $override['price']       ?? '',
                'custom_desc'  => $override['description'] ?? '',
            ];
        }

        wp_send_json_success( [
            'products'    => $products,
            'total'       => $total,
            'total_pages' => (int) ceil( $total / $per_page ),
            'page'        => $page,
        ] );
    }

    // ──────────────────────────────────────────────────────────
    // ③ حفظ Override منتج
    // ──────────────────────────────────────────────────────────
    public function save_product(): void {

        $this->verify();

        $pid = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
        if ( ! $pid ) {
            wp_send_json_error( [ 'message' => 'product_id مطلوب' ] );
        }

        $hidden      = isset( $_POST['hidden'] )      && $_POST['hidden'] === 'true';
        $custom_name = isset( $_POST['custom_name'] ) ? sanitize_text_field( $_POST['custom_name'] ) : '';
        $custom_price= isset( $_POST['custom_price']) ? sanitize_text_field( $_POST['custom_price'] ) : '';
        $custom_desc = isset( $_POST['custom_desc'] ) ? sanitize_textarea_field( $_POST['custom_desc'] ) : '';

        $data = [];

        if ( $hidden ) {
            $data['hidden'] = true;
        }
        if ( $custom_name !== '' ) {
            $data['name'] = $custom_name;
        }
        if ( $custom_price !== '' && is_numeric( $custom_price ) ) {
            $data['price'] = $custom_price;
        }
        if ( $custom_desc !== '' ) {
            $data['description'] = $custom_desc;
        }

        if ( empty( $data ) ) {
            Store_API_Config::delete_product_override( $pid );
        } else {
            Store_API_Config::save_product_override( $pid, $data );
        }

        wp_send_json_success( [ 'message' => 'تم الحفظ بنجاح', 'product_id' => $pid ] );
    }

    // ──────────────────────────────────────────────────────────
    // ④ حفظ إعدادات التصنيفات
    // ──────────────────────────────────────────────────────────
    public function save_categories(): void {

        $this->verify();

        $categories_json = isset( $_POST['categories'] ) ? stripslashes( $_POST['categories'] ) : '[]';
        $merged_json     = isset( $_POST['merged'] )     ? stripslashes( $_POST['merged'] )     : '[]';

        $categories = json_decode( $categories_json, true );
        $merged     = json_decode( $merged_json,     true );

        if ( ! is_array( $categories ) || ! is_array( $merged ) ) {
            wp_send_json_error( [ 'message' => 'بيانات غير صحيحة' ] );
        }

        // Sanitize categories
        $clean_cats = [];
        foreach ( $categories as $entry ) {
            $id = isset( $entry['id'] ) ? (int) $entry['id'] : 0;
            if ( ! $id ) continue;
            $image_mode = ( isset( $entry['image_mode'] ) && $entry['image_mode'] === 'alternative' )
                ? 'alternative'
                : 'primary';
            $alternative_image = isset( $entry['alternative_image'] )
                ? esc_url_raw( trim( (string) $entry['alternative_image'] ) )
                : '';
            $clean_cats[] = [
                'id'           => $id,
                'visible'      => isset( $entry['visible'] )      ? (bool) $entry['visible']     : true,
                'display_name' => isset( $entry['display_name'] ) ? sanitize_text_field( $entry['display_name'] ) : '',
                'note'         => isset( $entry['note'] )         ? sanitize_text_field( $entry['note'] )         : '',
                'image_mode'   => $image_mode,
                'alternative_image' => $alternative_image,
                'sort_order'   => isset( $entry['sort_order'] )   ? (int) $entry['sort_order']   : 999,
            ];
        }

        // Sanitize merged
        $clean_merged = [];
        foreach ( $merged as $mc ) {
            $did = isset( $mc['display_id'] ) ? (int) $mc['display_id'] : 0;
            if ( ! $did ) continue;
            $merge_ids = isset( $mc['merge_ids'] ) && is_array( $mc['merge_ids'] )
                ? array_values( array_filter( array_map( 'intval', $mc['merge_ids'] ) ) )
                : [];
            if ( empty( $merge_ids ) ) continue;
            $clean_merged[] = [
                'display_id'   => $did,
                'display_name' => isset( $mc['display_name'] ) ? sanitize_text_field( $mc['display_name'] ) : '',
                'merge_ids'    => $merge_ids,
            ];
        }

        update_option( 'store_categories_config', wp_json_encode( $clean_cats,   JSON_UNESCAPED_UNICODE ) );
        update_option( 'store_merged_categories', wp_json_encode( $clean_merged, JSON_UNESCAPED_UNICODE ) );

        wp_send_json_success( [
            'message'    => 'تم حفظ التصنيفات بنجاح',
            'categories' => count( $clean_cats ),
            'merged'     => count( $clean_merged ),
        ] );
    }

    // ──────────────────────────────────────────────────────────
    // ⑤ حفظ ترتيب المنتجات لتصنيف معين ← ✅ جديد
    // ──────────────────────────────────────────────────────────
    public function save_product_order(): void {

        $this->verify();

        $cat_id    = isset( $_POST['cat_id'] )     ? (int) $_POST['cat_id']          : 0;
        $order_json= isset( $_POST['order'] )      ? stripslashes( $_POST['order'] ) : '[]';

        if ( ! $cat_id ) {
            wp_send_json_error( [ 'message' => 'cat_id مطلوب' ] );
        }

        $sorted_ids = json_decode( $order_json, true );
        if ( ! is_array( $sorted_ids ) ) {
            wp_send_json_error( [ 'message' => 'بيانات الترتيب غير صحيحة' ] );
        }

        $sorted_ids = array_values( array_filter( array_map( 'intval', $sorted_ids ) ) );

        Store_API_Config::save_product_sort_order( $cat_id, $sorted_ids );

        wp_send_json_success( [
            'message'  => 'تم حفظ الترتيب بنجاح',
            'cat_id'   => $cat_id,
            'count'    => count( $sorted_ids ),
        ] );
    }
}
