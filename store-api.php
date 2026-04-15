<?php
/**
 * Plugin Name: Store API – Flutter App
 * Plugin URI:  https://github.com/wp-fully/store-api
 * Description: REST API متكامل لتطبيق Flutter — إدارة التصنيفات والمنتجات والطلبات والدفع
 * Version:     6.1.0
 * Author:      Store API
 * Text Domain: Ahmed Mohamed
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * WC requires at least: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Constants ──────────────────────────────────────────────
define( 'STORE_API_VERSION',  '6.1.0' );
define( 'STORE_API_PATH',     plugin_dir_path( __FILE__ ) );
define( 'STORE_API_URL',      plugin_dir_url( __FILE__ ) );
define( 'STORE_API_BASENAME', plugin_basename( __FILE__ ) );

// ── Autoload ───────────────────────────────────────────────
spl_autoload_register( function ( string $class ): void {

    $map = [
        'Store_API_Config'     => STORE_API_PATH . 'includes/class-store-api-config.php',
        'Store_API_Loader'     => STORE_API_PATH . 'includes/class-store-api-loader.php',
        'Store_API_Routes'     => STORE_API_PATH . 'includes/class-store-api-routes.php',
        'Store_API_Categories' => STORE_API_PATH . 'includes/class-store-api-categories.php',
        'Store_API_Products'   => STORE_API_PATH . 'includes/class-store-api-products.php',
        'Store_API_Orders'     => STORE_API_PATH . 'includes/class-store-api-orders.php',
        'Store_API_Payment'    => STORE_API_PATH . 'includes/class-store-api-payment.php',
        'Store_API_Admin'      => STORE_API_PATH . 'admin/class-store-api-admin.php',
        'Store_API_Ajax'       => STORE_API_PATH . 'admin/class-store-api-ajax.php',
    ];

    if ( isset( $map[ $class ] ) && file_exists( $map[ $class ] ) ) {
        require_once $map[ $class ];
    }
} );

// ── Activation / Deactivation ──────────────────────────────
register_activation_hook( __FILE__, 'store_api_activate' );
register_deactivation_hook( __FILE__, 'store_api_deactivate' );

function store_api_activate(): void {
    if ( ! function_exists( 'WC' ) ) {
        deactivate_plugins( STORE_API_BASENAME );
        wp_die( 'يتطلب هذا البلجن تفعيل WooCommerce أولاً.' );
    }
    store_api_run_migrations();

    flush_rewrite_rules();
}

function store_api_deactivate(): void {
    flush_rewrite_rules();
}

// ── Migrations ───────────────────────────────────────────────
function store_api_run_migrations(): void {
    $db_version = Store_API_Config::get_db_version();

    // v6.1.0: Category image source settings
    if ( version_compare( $db_version, '6.1.0', '<' ) ) {
        $raw_categories = get_option( 'store_categories_config', '[]' );
        $categories     = json_decode( $raw_categories, true );

        if ( is_array( $categories ) ) {
            $has_changes = false;
            foreach ( $categories as &$entry ) {
                if ( ! is_array( $entry ) ) continue;

                if ( ! isset( $entry['image_mode'] ) ) {
                    $entry['image_mode'] = 'primary';
                    $has_changes = true;
                }

                if ( ! isset( $entry['alternative_image'] ) ) {
                    $entry['alternative_image'] = '';
                    $has_changes = true;
                }
            }
            unset( $entry );

            if ( $has_changes ) {
                update_option( 'store_categories_config', wp_json_encode( $categories, JSON_UNESCAPED_UNICODE ) );
            }
        }
    }

    // Set defaults if missing (update-safe)
    $defaults = Store_API_Config::get_defaults();
    foreach ( $defaults as $key => $value ) {
        if ( get_option( $key ) === false ) {
            update_option( $key, $value );
        }
    }

    Store_API_Config::set_db_version( STORE_API_VERSION );
}

// ── i18n ────────────────────────────────────────────────────
add_action( 'plugins_loaded', function (): void {
    load_plugin_textdomain( 'store-api', false, dirname( STORE_API_BASENAME ) . '/languages/' );
} );

// ── Boot ───────────────────────────────────────────────────
add_action( 'plugins_loaded', function (): void {

    if ( ! function_exists( 'WC' ) ) return;

    ( new Store_API_Loader() )->init();

    if ( is_admin() ) {
        new Store_API_Admin();
        new Store_API_Ajax();
    }
} );

// ── Upgrade Hook ────────────────────────────────────────────
add_action( 'init', function (): void {
    if ( ! function_exists( 'WC' ) ) return;

    $db_version = Store_API_Config::get_db_version();
    if ( version_compare( $db_version, STORE_API_VERSION, '<' ) ) {
        store_api_run_migrations();
        flush_rewrite_rules();
    }
} );

// ── In-place Plugin Update Hook (no delete/reinstall) ───────
add_action( 'upgrader_process_complete', function ( $upgrader, $options ): void {
    if ( ! function_exists( 'WC' ) ) return;
    if ( ! is_array( $options ) ) return;

    if ( ( $options['action'] ?? '' ) !== 'update' || ( $options['type'] ?? '' ) !== 'plugin' ) {
        return;
    }

    $updated_plugins = $options['plugins'] ?? [];
    if ( ! is_array( $updated_plugins ) || ! in_array( STORE_API_BASENAME, $updated_plugins, true ) ) {
        return;
    }

    store_api_run_migrations();
    flush_rewrite_rules();
}, 10, 2 );
