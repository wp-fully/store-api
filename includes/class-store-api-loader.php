<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Store_API_Loader
 * تسجيل كل الـ Hooks الخاصة بالـ API
 */
class Store_API_Loader {

    public function init(): void {
        $this->register_cors();
        $this->register_routes();
        $this->register_session_fix();
    }

    // ──────────────────────────────────────────────────────────
    // CORS Headers
    // ──────────────────────────────────────────────────────────
    private function register_cors(): void {

        add_action( 'rest_api_init', function (): void {

            if ( ! Store_API_Config::cors_enabled() ) return;

            remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );

            add_filter( 'rest_pre_serve_request', function ( $served, $result, $request, $server ) {

                if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
                    return $served;
                }

                if ( ! headers_sent() ) {
                    header( 'Access-Control-Allow-Origin: *' );
                    header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
                    header( 'Access-Control-Allow-Headers: Content-Type, X-Store-Api-Key, Authorization' );
                    header( 'Access-Control-Max-Age: 3600' );
                }

                return $served;

            }, 10, 4 );

        }, 15 );

        // OPTIONS Preflight
        add_action( 'init', function (): void {

            if ( ! Store_API_Config::cors_enabled() ) return;

            if (
                isset( $_SERVER['REQUEST_METHOD'] )
                && $_SERVER['REQUEST_METHOD'] === 'OPTIONS'
                && ! headers_sent()
            ) {
                header( 'Access-Control-Allow-Origin: *' );
                header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
                header( 'Access-Control-Allow-Headers: Content-Type, X-Store-Api-Key, Authorization' );
                header( 'Access-Control-Max-Age: 3600' );
                header( 'Content-Length: 0' );
                header( 'Content-Type: text/plain' );
                exit( 0 );
            }
        } );
    }

    // ──────────────────────────────────────────────────────────
    // REST Routes
    // ──────────────────────────────────────────────────────────
    private function register_routes(): void {
        add_action( 'rest_api_init', function (): void {
            ( new Store_API_Routes() )->register();
        } );
    }

    // ──────────────────────────────────────────────────────────
    // Fix order-pay Session
    // ──────────────────────────────────────────────────────────
    private function register_session_fix(): void {
        add_action( 'template_redirect', function (): void {
            ( new Store_API_Payment() )->fix_order_pay_session();
        }, 1 );
    }
}
