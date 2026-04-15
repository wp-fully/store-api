<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Store_API_Admin
 * - إضافة صفحة الإعدادات
 * - تسجيل Settings
 * - تحميل Assets
 */
class Store_API_Admin {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'add_menu' ] );
        add_action( 'admin_init',            [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    // ──────────────────────────────────────────────────────────
    // القائمة
    // ──────────────────────────────────────────────────────────
    public function add_menu(): void {
        add_menu_page(
            'Store API — إعدادات',
            'Store API',
            'manage_options',
            'store-api-settings',
            [ $this, 'render_page' ],
            'dashicons-rest-api',
            56
        );
    }

    // ──────────────────────────────────────────────────────────
    // تسجيل الإعدادات
    // ──────────────────────────────────────────────────────────
    public function register_settings(): void {

        // General
        register_setting( 'store_api_general', 'store_api_key', [
            'sanitize_callback' => 'sanitize_text_field',
        ] );
        register_setting( 'store_api_general', 'store_telr_store_id', [
            'sanitize_callback' => 'sanitize_text_field',
        ] );
        register_setting( 'store_api_general', 'store_telr_auth_key', [
            'sanitize_callback' => 'sanitize_text_field',
        ] );
        register_setting( 'store_api_general', 'store_telr_test_mode', [
            'sanitize_callback' => 'absint',
        ] );
        register_setting( 'store_api_general', 'store_cors_enabled', [
            'sanitize_callback' => 'absint',
        ] );

        // Categories
        register_setting( 'store_api_categories', 'store_categories_config', [
            'sanitize_callback' => [ $this, 'sanitize_json_array' ],
        ] );
        register_setting( 'store_api_categories', 'store_merged_categories', [
            'sanitize_callback' => [ $this, 'sanitize_json_array' ],
        ] );
    }

    // ──────────────────────────────────────────────────────────
    // Sanitize Helpers
    // ──────────────────────────────────────────────────────────
    public function sanitize_json_array( $value ): string {
        $decoded = json_decode( $value, true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
            add_settings_error(
                'store_api_settings',
                'invalid_json',
                'خطأ: البيانات المدخلة ليست JSON صحيحاً.',
                'error'
            );
            return $value;
        }
        return wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE );
    }

    // ──────────────────────────────────────────────────────────
    // Assets
    // ──────────────────────────────────────────────────────────
    public function enqueue_assets( string $hook ): void {

        if ( $hook !== 'toplevel_page_store-api-settings' ) return;

        wp_enqueue_style(
            'store-api-admin',
            STORE_API_URL . 'admin/assets/admin.css',
            [],
            STORE_API_VERSION
        );

        wp_enqueue_script(
            'store-api-admin',
            STORE_API_URL . 'admin/assets/admin.js',
            [],
            STORE_API_VERSION,
            true
        );

        wp_localize_script( 'store-api-admin', 'StoreAPI', [
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'rest_url'   => get_rest_url( null, 'store/v1' ),
            'nonce'      => wp_create_nonce( 'store_api_nonce' ),
            'api_key'    => Store_API_Config::api_key(),
            'strings'    => [
                'confirm_generate' => 'هل تريد توليد مفتاح جديد؟ المفتاح الحالي سيُستبدل عند الحفظ.',
                'copied'           => '✅ تم النسخ',
                'copy_failed'      => '❌ فشل النسخ',
                'generated'        => '✅ تم التوليد',
                'saved'            => '✅ تم الحفظ',
                'save_error'       => '❌ حدث خطأ',
                'loading'          => 'جاري التحميل...',
                'no_products'      => 'لا توجد منتجات في هذا التصنيف',
            ],
        ] );
    }

    // ──────────────────────────────────────────────────────────
    // رسم الصفحة
    // ──────────────────────────────────────────────────────────
    public function render_page(): void {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'ليس لديك صلاحية الوصول.' );
        }

        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';

        $tabs = [
            'general'    => '🔑 الإعدادات العامة',
            'categories' => '📂 التصنيفات',
            'products'   => '📦 المنتجات',
            'status'     => '📊 حالة الـ API',
        ];

        if ( ! array_key_exists( $tab, $tabs ) ) {
            $tab = 'general';
        }

        ?>
        <div class="wrap store-api-wrap" dir="rtl">

            <!-- Header -->
            <div class="store-api-header">
                <div class="store-api-header-logo">
                    <span class="dashicons dashicons-rest-api"></span>
                </div>
                <div class="store-api-header-info">
                    <h1>
                        Store API
                        <span class="store-api-version">v<?php echo esc_html( STORE_API_VERSION ); ?></span>
                    </h1>
                    <p>إدارة REST API تطبيق Flutter — WooCommerce</p>
                </div>
            </div>

            <?php settings_errors( 'store_api_settings' ); ?>

            <!-- Tabs -->
            <nav class="nav-tab-wrapper store-api-tabs">
                <?php foreach ( $tabs as $key => $label ) : ?>
                    <a
                        href="<?php echo esc_url( admin_url( 'admin.php?page=store-api-settings&tab=' . $key ) ); ?>"
                        class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>"
                    >
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <!-- Content -->
            <div class="store-api-tab-content">
                <?php
                $view = STORE_API_PATH . 'admin/views/tab-' . $tab . '.php';
                if ( file_exists( $view ) ) {
                    include $view;
                } else {
                    echo '<div class="store-api-notice store-api-notice-error">الصفحة غير موجودة.</div>';
                }
                ?>
            </div>

        </div>
        <?php
    }
}
