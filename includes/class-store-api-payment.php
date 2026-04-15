<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Store_API_Payment
 * Helper A: get_payment_url()
 * Helper B: telr_create_payment()
 * Helper C: order_pay_url()
 * Helper D: fix_order_pay_session()
 */
class Store_API_Payment {

    // ──────────────────────────────────────────────────────────
    // Helper A: توجيه رابط الدفع
    // ──────────────────────────────────────────────────────────
    public function get_payment_url( int $order_id, string $payment_method ): string {

        if ( $payment_method === 'wctelr' ) {
            return $this->telr_create_payment( $order_id );
        }

        if ( ! WC()->session ) {
            WC()->initialize_session();
        }
        WC()->session->set( 'order_awaiting_payment', $order_id );

        $gateways = WC()->payment_gateways()->payment_gateways();

        if (
            ! isset( $gateways[ $payment_method ] )
            || $gateways[ $payment_method ]->enabled !== 'yes'
        ) {
            return $this->order_pay_url( $order_id );
        }

        try {
            $result = $gateways[ $payment_method ]->process_payment( $order_id );
            if (
                is_array( $result )
                && ( $result['result'] ?? '' ) === 'success'
                && ! empty( $result['redirect'] )
            ) {
                return (string) $result['redirect'];
            }
        } catch ( Throwable $e ) {
            error_log( '[Store API] process_payment error: ' . $e->getMessage() );
        }

        return $this->order_pay_url( $order_id );
    }

    // ──────────────────────────────────────────────────────────
    // Helper B: Telr — إنشاء رابط دفع
    // ──────────────────────────────────────────────────────────
    public function telr_create_payment( int $order_id ): string {

        $store_id = Store_API_Config::telr_store_id();
        $auth_key = Store_API_Config::telr_auth_key();

        if ( $store_id === '' || $auth_key === '' ) {
            error_log( '[Store API] Telr غير مُعد — fallback.' );
            return $this->order_pay_url( $order_id );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return $this->order_pay_url( $order_id );
        }

        $total    = number_format( (float) $order->get_total(), 2, '.', '' );
        $currency = get_woocommerce_currency();
        $fname    = $order->get_billing_first_name() ?: 'Customer';
        $phone    = $order->get_billing_phone()       ?: '';
        $email    = $order->get_billing_email()       ?: 'app@store.com';

        $return_auth = add_query_arg(
            [ 'wc-api' => 'WC_Telr', 'order_id' => $order_id, 'status' => 'auth' ],
            home_url( '/' )
        );
        $return_can  = add_query_arg(
            [ 'wc-api' => 'WC_Telr', 'order_id' => $order_id, 'status' => 'cancel' ],
            home_url( '/' )
        );
        $return_decl = add_query_arg(
            [ 'wc-api' => 'WC_Telr', 'order_id' => $order_id, 'status' => 'declined' ],
            home_url( '/' )
        );

        $payload = [
            'ivp_method'   => 'create',
            'ivp_store'    => $store_id,
            'ivp_authkey'  => $auth_key,
            'ivp_cart'     => $order_id,
            'ivp_test'     => Store_API_Config::telr_test_mode(),
            'ivp_amount'   => $total,
            'ivp_currency' => $currency,
            'ivp_desc'     => 'Order #' . $order->get_order_number(),
            'ivp_lang'     => 'AR',
            'return_auth'  => $return_auth,
            'return_can'   => $return_can,
            'return_decl'  => $return_decl,
            'bill_fname'   => $fname,
            'bill_sname'   => '.',
            'bill_email'   => $email,
            'bill_tel'     => $phone,
            'bill_addr1'   => 'App Order',
            'bill_city'    => 'Riyadh',
            'bill_country' => 'SA',
        ];

        $response = wp_remote_post( 'https://secure.telr.com/gateway/order.json', [
            'method'  => 'POST',
            'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
            'body'    => http_build_query( $payload ),
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( '[Store API] Telr error: ' . $response->get_error_message() );
            return $this->order_pay_url( $order_id );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $body['order']['ref'] ) ) {
            $order->update_meta_data( '_telr_order_ref', $body['order']['ref'] );
            $order->save();
        }

        if ( ! empty( $body['order']['url'] ) ) {
            return (string) $body['order']['url'];
        }

        error_log( '[Store API] Telr: ' . ( $body['error']['message'] ?? 'no url returned' ) );
        return $this->order_pay_url( $order_id );
    }

    // ──────────────────────────────────────────────────────────
    // Helper C: Fallback — order-pay URL
    // ──────────────────────────────────────────────────────────
    public function order_pay_url( int $order_id ): string {

        $order = wc_get_order( $order_id );
        if ( ! $order ) return home_url( '/checkout/' );

        return add_query_arg(
            [
                'pay_for_order' => 'true',
                'key'           => $order->get_order_key(),
            ],
            home_url( "/checkout/order-pay/{$order_id}/" )
        );
    }

    // ──────────────────────────────────────────────────────────
    // Helper D: Fix order-pay Session
    // ──────────────────────────────────────────────────────────
    public function fix_order_pay_session(): void {

        if (
            ! function_exists( 'is_wc_endpoint_url' )
            || ! is_wc_endpoint_url( 'order-pay' )
        ) {
            return;
        }

        $order_id  = absint( get_query_var( 'order-pay' ) );
        $order_key = sanitize_text_field( $_GET['key'] ?? '' );

        if ( ! $order_id || $order_key === '' ) return;

        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_order_key() !== $order_key ) return;
        if ( ! in_array( $order->get_status(), [ 'pending', 'failed' ], true ) ) return;

        if ( ! WC()->session ) {
            WC()->initialize_session();
        }

        WC()->session->set( 'order_awaiting_payment', $order_id );
    }
}
