<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Store_API_Config
 * المرجع الوحيد لقراءة كل الإعدادات من قاعدة البيانات
 * + إنشاء الجداول عند التفعيل
 */
class Store_API_Config {



    // ──────────────────────────────────────────────────────────
    // API Key
    // ──────────────────────────────────────────────────────────
    public static function api_key(): string {
        return (string) get_option( 'store_api_key', '' );
    }

    // ──────────────────────────────────────────────────────────
    // CORS
    // ──────────────────────────────────────────────────────────
    public static function cors_enabled(): bool {
        return (bool) get_option( 'store_cors_enabled', '1' );
    }

    // ──────────────────────────────────────────────────────────
    // Telr
    // ──────────────────────────────────────────────────────────
    public static function telr_store_id(): string {
        return (string) get_option( 'store_telr_store_id', '' );
    }

    public static function telr_auth_key(): string {
        return (string) get_option( 'store_telr_auth_key', '' );
    }

    public static function telr_test_mode(): int {
        return (int) get_option( 'store_telr_test_mode', 0 );
    }

    // ──────────────────────────────────────────────────────────
    // Categories Config
    // يرجع مصفوفة التصنيفات المُعدَّة للظهور في الـ API
    // كل عنصر: [ 'id' => int, 'display_name' => string, 'visible' => bool, 'image_mode' => 'primary|alternative', 'alternative_image' => string ]
    // ──────────────────────────────────────────────────────────
    public static function categories_config(): array {
        $raw     = get_option( 'store_categories_config', '[]' );
        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) return [];

        // فلترة: الظاهرة فقط
        return array_values( array_filter( $decoded, function ( $entry ) {
            return isset( $entry['id'] ) && ( $entry['visible'] ?? true ) === true;
        } ) );
    }

    // ──────────────────────────────────────────────────────────
    // Categories Config (كاملة — بما فيها المخفية) للـ Admin
    // ──────────────────────────────────────────────────────────
    public static function categories_config_all(): array {
        $raw     = get_option( 'store_categories_config', '[]' );
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : [];
    }

    // ──────────────────────────────────────────────────────────
    // Merged Categories
    // ──────────────────────────────────────────────────────────
    public static function merged_categories(): array {
        $raw     = get_option( 'store_merged_categories', '[]' );
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : [];
    }

    // ──────────────────────────────────────────────────────────
    // Product Overrides
    // يرجع مصفوفة بـ product_id => [ override fields ]
    // ──────────────────────────────────────────────────────────
    public static function product_overrides(): array {
        $raw     = get_option( 'store_product_overrides', '{}' );
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : [];
    }

    // ──────────────────────────────────────────────────────────
    // Override لمنتج واحد بالـ ID
    // ──────────────────────────────────────────────────────────
    public static function get_product_override( int $product_id ): array {
        $overrides = self::product_overrides();
        return $overrides[ (string) $product_id ] ?? [];
    }

    // ──────────────────────────────────────────────────────────
    // حفظ Override لمنتج واحد
    // ──────────────────────────────────────────────────────────
    public static function save_product_override( int $product_id, array $data ): void {
        $overrides = self::product_overrides();
        $overrides[ (string) $product_id ] = $data;
        update_option( 'store_product_overrides', wp_json_encode( $overrides, JSON_UNESCAPED_UNICODE ) );
    }

    // ──────────────────────────────────────────────────────────
    // حذف Override لمنتج
    // ──────────────────────────────────────────────────────────
    public static function delete_product_override( int $product_id ): void {
        $overrides = self::product_overrides();
        unset( $overrides[ (string) $product_id ] );
        update_option( 'store_product_overrides', wp_json_encode( $overrides, JSON_UNESCAPED_UNICODE ) );
    }

    // ──────────────────────────────────────────────────────────
    // هل المنتج مخفي؟
    // ──────────────────────────────────────────────────────────
    public static function is_product_hidden( int $product_id ): bool {
        $override = self::get_product_override( $product_id );
        return isset( $override['hidden'] ) && $override['hidden'] === true;
    }

    // ──────────────────────────────────────────────────────────
    // قائمة IDs المنتجات المخفية
    // ──────────────────────────────────────────────────────────
    public static function hidden_product_ids(): array {
        $overrides = self::product_overrides();
        $hidden    = [];
        foreach ( $overrides as $pid => $data ) {
            if ( isset( $data['hidden'] ) && $data['hidden'] === true ) {
                $hidden[] = (int) $pid;
            }
        }
        return $hidden;
    }

    // ──────────────────────────────────────────────────────────
    // التحقق من صحة الـ API Key
    // ──────────────────────────────────────────────────────────
    public static function verify_api_key( string $key ): bool {
        $stored = self::api_key();
        if ( $stored === '' ) return false;
        return hash_equals( $stored, $key );
    }

    // ══════════════════════════════════════════════════════════
    // Product Sort Order (per category)
    // ══════════════════════════════════════════════════════════

    /**
     * جلب ترتيب المنتجات لتصنيف معين
     * Returns: [ 'product_id' => sort_index, ... ]
     */
    public static function get_product_sort_order( int $cat_id ): array {
        $all = get_option( 'store_product_sort_orders', '{}' );
        $all = json_decode( $all, true );
        if ( ! is_array( $all ) ) return [];
        return $all[ (string) $cat_id ] ?? [];
    }

    /**
     * حفظ ترتيب المنتجات لتصنيف معين
     *
     * @param int   $cat_id
     * @param array $sorted_ids  [ product_id, product_id, ... ] بالترتيب المطلوب
     */
    public static function save_product_sort_order( int $cat_id, array $sorted_ids ): void {
        $all = get_option( 'store_product_sort_orders', '{}' );
        $all = json_decode( $all, true );
        if ( ! is_array( $all ) ) $all = [];

        // نخزن كـ [ 'product_id' => index ]
        $order_map = [];
        foreach ( $sorted_ids as $idx => $pid ) {
            $order_map[ (string)(int) $pid ] = $idx;
        }

        $all[ (string) $cat_id ] = $order_map;

        update_option(
            'store_product_sort_orders',
            wp_json_encode( $all, JSON_UNESCAPED_UNICODE )
        );
    }

    /**
     * حذف ترتيب تصنيف بالكامل (cleanup)
     */
    public static function delete_product_sort_order( int $cat_id ): void {
        $all = get_option( 'store_product_sort_orders', '{}' );
        $all = json_decode( $all, true );
        if ( ! is_array( $all ) ) return;
        unset( $all[ (string) $cat_id ] );
        update_option(
            'store_product_sort_orders',
            wp_json_encode( $all, JSON_UNESCAPED_UNICODE )
        );
    }

    // ──────────────────────────────────────────────────────────
    // Defaults
    // ──────────────────────────────────────────────────────────
    public static function get_defaults(): array {
        return [
            'store_api_key' => '',
            'store_cors_enabled' => '1',
            'store_categories_config' => '[]',
            'store_merged_categories' => '[]',
            'store_product_overrides' => '{}',
            'store_product_sort_orders' => '{}',
            'store_telr_store_id' => '',
            'store_telr_auth_key' => '',
            'store_telr_test_mode' => 0,
        ];
    }

    // ──────────────────────────────────────────────────────────
    // DB Version Tracking
    // ──────────────────────────────────────────────────────────
    public static function get_db_version(): string {
        return get_option( 'store_api_db_version', '0.0.0' );
    }

    public static function set_db_version( string $version ): void {
        update_option( 'store_api_db_version', $version );
    }
}
