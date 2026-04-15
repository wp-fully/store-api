<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Store_API_Orders
 * ⑤ POST /store/v1/checkout
 * ⑥ GET  /store/v1/orders/{id}/status
 */
class Store_API_Orders {

    // ──────────────────────────────────────────────────────────
    // ⑤ POST /store/v1/checkout
    // ──────────────────────────────────────────────────────────
    public function checkout( WP_REST_Request $request ): WP_REST_Response|WP_Error {

        $first_name     = $request->get_param( 'first_name' );
        $phone          = $request->get_param( 'phone' );
        $payment_method = $request->get_param( 'payment_method' );
        $items          = $request->get_param( 'items' );

        // ── Validation ─────────────────────────────────────────
        if ( empty( $first_name ) ) {
            return new WP_Error( 'validation', 'الاسم مطلوب', [ 'status' => 400 ] );
        }

        if ( empty( $phone ) || strlen( preg_replace( '/\D/', '', $phone ) ) < 9 ) {
            return new WP_Error( 'validation', 'رقم الهاتف غير صحيح', [ 'status' => 400 ] );
        }

        $allowed_methods = [ 'wctelr', 'tabby_installments' ];
        if ( ! in_array( $payment_method, $allowed_methods, true ) ) {
            return new WP_Error(
                'invalid_payment',
                'طريقة الدفع غير مدعومة. المتاح: ' . implode( ', ', $allowed_methods ),
                [ 'status' => 400 ]
            );
        }

        if ( ! is_array( $items ) || empty( $items ) ) {
            return new WP_Error( 'invalid_items', 'يجب إضافة منتج واحد على الأقل', [ 'status' => 400 ] );
        }

        try {

            // ── إنشاء الطلب ────────────────────────────────────
            $order = wc_create_order( [ 'status' => 'pending', 'customer_id' => 0 ] );
            if ( is_wp_error( $order ) ) {
                throw new Exception( $order->get_error_message() );
            }

            $added_items = [];
            $errors      = [];

            // ── إضافة المنتجات ─────────────────────────────────
            foreach ( $items as $item ) {

                $pid = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
                $qty = isset( $item['quantity'] )   ? max( 1, (int) $item['quantity'] ) : 1;

                if ( ! $pid ) {
                    $errors[] = 'product_id غير صحيح';
                    continue;
                }

                if ( Store_API_Config::is_product_hidden( $pid ) ) {
                    $errors[] = "المنتج #{$pid} غير موجود";
                    continue;
                }

                $product = wc_get_product( $pid );

                if ( ! $product || $product->get_status() !== 'publish' ) {
                    $errors[] = "المنتج #{$pid} غير موجود";
                    continue;
                }

                if ( $product->get_stock_status() !== 'instock' ) {
                    $errors[] = "المنتج «{$product->get_name()}» غير متوفر في المخزون";
                    continue;
                }

                // السعر الحقيقي من WooCommerce (لا تأثير للـ override على الطلب)
                $order->add_product( $product, $qty );

                $override      = Store_API_Config::get_product_override( $pid );
                $added_items[] = [
                    'product_id' => $pid,
                    'name'       => $override['name'] ?? $product->get_name(),
                    'quantity'   => $qty,
                    'price'      => $product->get_price(),
                ];
            }

            // ── لو ما فيش منتجات صالحة احذف الطلب ─────────────
            if ( empty( $added_items ) ) {
                $order->delete( true );
                return new WP_Error(
                    'no_valid_items',
                    'لا توجد منتجات صالحة: ' . implode( ' | ', $errors ),
                    [ 'status' => 400 ]
                );
            }

            // ── بيانات العميل ──────────────────────────────────
            $payment_labels = [
                'wctelr'             => 'مدى / فيزا / ماستركارد / Apple Pay',
                'tabby_installments' => 'تابي - ادفع لاحقاً',
            ];

            $order->set_billing_first_name( $first_name );
            $order->set_billing_last_name( '.' );
            $order->set_billing_email( 'app-order@store.com' );
            $order->set_billing_phone( $phone );
            $order->set_billing_country( 'SA' );
            $order->set_payment_method( $payment_method );
            $order->set_payment_method_title( $payment_labels[ $payment_method ] );
            $order->update_meta_data( 'order_source', 'flutter_app' );
            $order->calculate_totals();
            $order->save();

            // ── رابط الدفع ─────────────────────────────────────
            $payment     = new Store_API_Payment();
            $payment_url = $payment->get_payment_url( $order->get_id(), $payment_method );

            return rest_ensure_response( [
                'success'        => true,
                'order_id'       => $order->get_id(),
                'order_number'   => $order->get_order_number(),
                'status'         => $order->get_status(),
                'total'          => $order->get_total(),
                'currency'       => get_woocommerce_currency(),
                'payment_url'    => $payment_url,
                'payment_method' => $payment_method,
                'items_added'    => $added_items,
                'items_skipped'  => $errors,
                'created_at'     => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
            ] );

        } catch ( Exception $e ) {
            return new WP_Error( 'order_error', $e->getMessage(), [ 'status' => 500 ] );
        }
    }

    // ──────────────────────────────────────────────────────────
    // ⑥ GET /store/v1/orders/{id}/status
    // ──────────────────────────────────────────────────────────
    public function get_order_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {

        $order = wc_get_order( (int) $request['id'] );

        if ( ! $order ) {
            return new WP_Error( 'not_found', 'الطلب غير موجود', [ 'status' => 404 ] );
        }

        $status = $order->get_status();

        $labels = [
            'pending'    => 'في الانتظار',
            'processing' => 'قيد المعالجة',
            'completed'  => 'مكتمل',
            'cancelled'  => 'ملغي',
            'refunded'   => 'مسترد',
            'failed'     => 'فشل',
            'on-hold'    => 'معلق',
        ];

        return rest_ensure_response( [
            'success'      => true,
            'order_id'     => $order->get_id(),
            'status'       => $status,
            'status_label' => $labels[ $status ] ?? $status,
            'is_paid'      => in_array( $status, [ 'processing', 'completed' ], true ),
            'total'        => $order->get_total(),
            'currency'     => get_woocommerce_currency(),
            'updated_at'   => $order->get_date_modified()
                                ? $order->get_date_modified()->date( 'Y-m-d H:i:s' )
                                : null,
        ] );
    }
}
