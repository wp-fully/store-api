<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;
// لحذف البيانات نهائياً: عرّف هذا الثابت في wp-config.php
// define( 'STORE_API_DELETE_DATA_ON_UNINSTALL', true );
if ( ! defined( 'STORE_API_DELETE_DATA_ON_UNINSTALL' ) || STORE_API_DELETE_DATA_ON_UNINSTALL !== true ) {
    return;
}

// حذف كل الـ Options
$options = [
    'store_api_key',
    'store_telr_store_id',
    'store_telr_auth_key',
    'store_telr_test_mode',
    'store_cors_enabled',
    'store_categories_config',
    'store_merged_categories',
    'store_product_overrides',
    'store_product_sort_orders',
    'store_api_db_version',
];

foreach ( $options as $option ) {
    delete_option( $option );
}
