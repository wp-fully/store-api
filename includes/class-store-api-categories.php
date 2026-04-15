<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Store_API_Categories
 * ① GET /store/v1/categories
 * ② GET /store/v1/categories/{id}/products
 */
class Store_API_Categories {

    // ──────────────────────────────────────────────────────────
    // ① GET /store/v1/categories
    // ──────────────────────────────────────────────────────────
    public function get_categories( WP_REST_Request $request ): WP_REST_Response|WP_Error {

        $config   = Store_API_Config::categories_config();   // الظاهرة فقط
        $merged   = Store_API_Config::merged_categories();
        $base_url = get_rest_url( null, 'store/v1' );

        if ( empty( $config ) ) {
            return rest_ensure_response( [ 'success' => true, 'count' => 0, 'data' => [] ] );
        }

        // ── جمع كل IDs المدمجة ────────────────────────────────
        $all_merged_ids = [];
        foreach ( $merged as $mc ) {
            foreach ( $mc['merge_ids'] as $mid ) {
                $all_merged_ids[] = (int) $mid;
            }
        }

        // ── جمع كل IDs للجلب من DB ────────────────────────────
        $fetch_ids = [];
        foreach ( $config as $entry ) {
            $fetch_ids[] = (int) $entry['id'];
        }
        foreach ( $merged as $mc ) {
            foreach ( $mc['merge_ids'] as $mid ) {
                $fetch_ids[] = (int) $mid;
            }
        }
        $fetch_ids = array_unique( $fetch_ids );

        $terms = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'include'    => $fetch_ids,
            'orderby'    => 'include',
            'order'      => 'ASC',
        ] );

        if ( is_wp_error( $terms ) ) {
            return new WP_Error( 'error', $terms->get_error_message(), [ 'status' => 500 ] );
        }

        // ── فهرسة بالـ ID ─────────────────────────────────────
        $terms_map = [];
        foreach ( $terms as $term ) {
            $terms_map[ $term->term_id ] = $term;
        }

        // ── خريطة الدمج ───────────────────────────────────────
        $merge_map = [];
        foreach ( $merged as $mc ) {
            $did             = (int) $mc['display_id'];
            $merge_map[$did] = [
                'merge_ids'    => array_map( 'intval', $mc['merge_ids'] ),
                'display_name' => $mc['display_name'] ?? null,
            ];
        }

        $data          = [];
        $processed_ids = [];

        foreach ( $config as $entry ) {

            $cat_id = (int) $entry['id'];

            // تخطى لو sub في دمج وليس display_id
            if (
                in_array( $cat_id, $all_merged_ids, true )
                && ! isset( $merge_map[$cat_id] )
            ) {
                continue;
            }

            if ( in_array( $cat_id, $processed_ids, true ) ) continue;
            if ( ! isset( $terms_map[$cat_id] ) ) continue;

            $term = $terms_map[$cat_id];

            // ── الاسم الظاهر ───────────────────────────────────
            if ( isset( $merge_map[$cat_id] ) && ! empty( $merge_map[$cat_id]['display_name'] ) ) {
                $display_name = $merge_map[$cat_id]['display_name'];
            } elseif ( ! empty( $entry['display_name'] ) ) {
                $display_name = $entry['display_name'];
            } else {
                $display_name = $term->name;
            }

            // ── عدد المنتجات ───────────────────────────────────
            $product_count = (int) $term->count;
            if ( isset( $merge_map[$cat_id] ) ) {
                $product_count = 0;
                foreach ( $merge_map[$cat_id]['merge_ids'] as $mid ) {
                    if ( isset( $terms_map[$mid] ) ) {
                        $product_count += (int) $terms_map[$mid]->count;
                    }
                }
            }

            // ── صورة التصنيف ───────────────────────────────────
            $image = $this->resolve_category_image( $term, $entry, $display_name );

            $data[]          = [
                'id'          => $term->term_id,
                'name'        => $display_name,
                'description' => wp_strip_all_tags( $term->description ),
                'image'       => $image,
                'count'       => $product_count,
                'href'        => rtrim( $base_url, '/' ) . '/categories/' . $term->term_id . '/products',
            ];
            $processed_ids[] = $cat_id;
        }

        return rest_ensure_response( [
            'success' => true,
            'count'   => count( $data ),
            'data'    => $data,
        ] );
    }

    // ──────────────────────────────────────────────────────────
    // ② GET /store/v1/categories/{id}/products
    // ──────────────────────────────────────────────────────────
    public function get_category_products( WP_REST_Request $request ): WP_REST_Response|WP_Error {

        $category_id = (int) $request['id'];
        $page        = max( 1, (int) $request->get_param( 'page' ) );
        $per_page    = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );

        // ── التحقق من أن التصنيف مسموح به ────────────────────
        $config      = Store_API_Config::categories_config();
        $merged      = Store_API_Config::merged_categories();

        $allowed_ids        = array_map( fn( $e ) => (int) $e['id'], $config );
        $merged_display_ids = array_map( fn( $m ) => (int) $m['display_id'], $merged );

        $is_allowed = in_array( $category_id, $allowed_ids, true )
                   || in_array( $category_id, $merged_display_ids, true );

        if ( ! $is_allowed ) {
            return new WP_Error( 'forbidden', 'هذا التصنيف غير متاح', [ 'status' => 403 ] );
        }

        $term = get_term( $category_id, 'product_cat' );
        if ( ! $term || is_wp_error( $term ) ) {
            return new WP_Error( 'not_found', 'التصنيف غير موجود', [ 'status' => 404 ] );
        }

        // ── تحديد الـ Slugs والاسم المعروض ────────────────────
        $category_slugs = [ $term->slug ];
        $display_name   = $term->name;
        $category_entry = [];

        foreach ( $merged as $mc ) {
            if ( (int) $mc['display_id'] !== $category_id ) continue;

            $category_slugs = [];
            foreach ( $mc['merge_ids'] as $mid ) {
                $sub = get_term( (int) $mid, 'product_cat' );
                if ( $sub && ! is_wp_error( $sub ) ) {
                    $category_slugs[] = $sub->slug;
                }
            }

            if ( ! empty( $mc['display_name'] ) ) {
                $display_name = $mc['display_name'];
            }
            break;
        }

        foreach ( $config as $entry ) {
            if ( (int) $entry['id'] !== $category_id ) continue;
            $category_entry = $entry;
            if ( ! empty( $entry['display_name'] ) ) {
                $display_name = $entry['display_name'];
            }
            break;
        }

        // ── جلب المنتجات ───────────────────────────────────────
        $hidden_ids = Store_API_Config::hidden_product_ids();

        $base_args = [
            'status'   => 'publish',
            'category' => $category_slugs,
            'orderby'  => 'date',
            'order'    => 'DESC',
        ];

        // جلب كل الـ IDs لحساب الـ pagination بعد الفلترة
        $all_ids = wc_get_products( array_merge( $base_args, [
            'limit'  => -1,
            'return' => 'ids',
        ] ) );

        $all_ids = array_values( array_filter(
            $all_ids,
            fn( $id ) => ! in_array( (int) $id, $hidden_ids, true )
        ) );

        $total       = count( $all_ids );
        $total_pages = (int) ceil( $total / $per_page );

        // جلب الصفحة الحالية
        $page_ids = array_slice( $all_ids, ( $page - 1 ) * $per_page, $per_page );

        $products_data = [];
        foreach ( $page_ids as $pid ) {
            $product = wc_get_product( (int) $pid );
            if ( ! $product ) continue;
            $handler         = new Store_API_Products();
            $products_data[] = $handler->format_product( $product );
        }

        return rest_ensure_response( [
            'success'    => true,
            'category'   => [
                'id'    => $term->term_id,
                'name'  => $display_name,
                'image' => $this->resolve_category_image( $term, $category_entry, $display_name ),
            ],
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => $total_pages,
                'has_more'    => $page < $total_pages,
            ],
            'data' => $products_data,
        ] );
    }

    // ──────────────────────────────────────────────────────────
    // Helper: اختيار صورة التصنيف (أساسية أو بديلة)
    // ──────────────────────────────────────────────────────────
    private function resolve_category_image( WP_Term $term, array $entry, string $fallback_alt ): ?array {
        $primary_image = $this->build_primary_term_image( $term );

        $mode = ( isset( $entry['image_mode'] ) && $entry['image_mode'] === 'alternative' )
            ? 'alternative'
            : 'primary';

        $alternative_url = isset( $entry['alternative_image'] )
            ? esc_url_raw( trim( (string) $entry['alternative_image'] ) )
            : '';

        if ( $mode === 'alternative' && $alternative_url !== '' ) {
            return [
                'id'     => 0,
                'src'    => $alternative_url,
                'alt'    => $fallback_alt ?: $term->name,
                'source' => 'alternative',
            ];
        }

        return $primary_image;
    }

    // ──────────────────────────────────────────────────────────
    // Helper: صورة WooCommerce الأساسية للتصنيف
    // ──────────────────────────────────────────────────────────
    private function build_primary_term_image( WP_Term $term ): ?array {
        $thumb_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
        if ( ! $thumb_id ) {
            return null;
        }

        $full = wp_get_attachment_image_src( $thumb_id, 'full' );
        if ( ! $full || empty( $full[0] ) ) {
            return null;
        }

        return [
            'id'     => $thumb_id,
            'src'    => $full[0],
            'alt'    => get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) ?: $term->name,
            'source' => 'primary',
        ];
    }
}
