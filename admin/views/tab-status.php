<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$rest_url  = get_rest_url( null, 'store/v1' );
$api_key   = Store_API_Config::api_key();
$cors      = Store_API_Config::cors_enabled();
$telr_id   = Store_API_Config::telr_store_id();
$telr_key  = Store_API_Config::telr_auth_key();
$test_mode = Store_API_Config::telr_test_mode();
$cats      = Store_API_Config::categories_config();
$hidden    = Store_API_Config::hidden_product_ids();
$merged    = Store_API_Config::merged_categories();
$overrides = Store_API_Config::product_overrides();

// طلبات التطبيق آخر 30 يوم
$app_orders = count( wc_get_orders( [
    'limit'      => -1,
    'return'     => 'ids',
    'meta_key'   => 'order_source',
    'meta_value' => 'flutter_app',
    'date_query' => [ [ 'after' => '30 days ago', 'inclusive' => true ] ],
] ) );

$endpoints = [
    [ 'method' => 'GET',  'path' => '/categories',               'desc' => 'قائمة التصنيفات' ],
    [ 'method' => 'GET',  'path' => '/categories/{id}/products', 'desc' => 'منتجات تصنيف' ],
    [ 'method' => 'GET',  'path' => '/products/{id}',            'desc' => 'منتج واحد' ],
    [ 'method' => 'POST', 'path' => '/cart/add',                 'desc' => 'التحقق من منتج' ],
    [ 'method' => 'POST', 'path' => '/checkout',                 'desc' => 'إنشاء طلب' ],
    [ 'method' => 'GET',  'path' => '/orders/{id}/status',       'desc' => 'حالة طلب' ],
];
?>

<div class="store-api-section">

    <!-- ── تحذيرات ───────────────────────────────────────── -->
    <?php if ( $api_key === '' ) : ?>
        <div class="store-api-notice store-api-notice-error">
            ❌ <strong>API Key غير مُعيَّن</strong> — الـ API معطّل كلياً.
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=store-api-settings&tab=general' ) ); ?>">اضغط هنا</a>
        </div>
    <?php endif; ?>

    <?php if ( $test_mode ) : ?>
        <div class="store-api-notice store-api-notice-warning">
            ⚠️ <strong>Telr في وضع الاختبار</strong> — لن تُسجَّل مدفوعات حقيقية.
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=store-api-settings&tab=general' ) ); ?>">اضغط هنا</a>
        </div>
    <?php endif; ?>

    <!-- ── بطاقات الإحصاء ──────────────────────────────────── -->
    <div class="store-api-stats-grid">

        <div class="store-api-stat-card <?php echo $api_key !== '' ? 'stat-ok' : 'stat-error'; ?>">
            <div class="stat-icon">🔑</div>
            <div class="stat-info">
                <div class="stat-label">API Key</div>
                <div class="stat-value"><?php echo $api_key !== '' ? '✅ مُعيَّن' : '❌ مفقود'; ?></div>
            </div>
        </div>

        <div class="store-api-stat-card <?php echo $cors ? 'stat-ok' : 'stat-warn'; ?>">
            <div class="stat-icon">🌐</div>
            <div class="stat-info">
                <div class="stat-label">CORS</div>
                <div class="stat-value"><?php echo $cors ? '✅ مفعّل' : '⚠️ معطّل'; ?></div>
            </div>
        </div>

        <div class="store-api-stat-card <?php echo ( $telr_id !== '' && $telr_key !== '' ) ? ( $test_mode ? 'stat-warn' : 'stat-ok' ) : 'stat-warn'; ?>">
            <div class="stat-icon">💳</div>
            <div class="stat-info">
                <div class="stat-label">Telr</div>
                <div class="stat-value">
                    <?php
                    if ( $telr_id !== '' && $telr_key !== '' ) {
                        echo $test_mode ? '⚠️ Test Mode' : '✅ Production';
                    } else {
                        echo '⚠️ غير مُعد';
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="store-api-stat-card stat-ok">
            <div class="stat-icon">📂</div>
            <div class="stat-info">
                <div class="stat-label">تصنيفات ظاهرة</div>
                <div class="stat-value"><?php echo count( $cats ); ?> تصنيف</div>
            </div>
        </div>

        <div class="store-api-stat-card stat-ok">
            <div class="stat-icon">🙈</div>
            <div class="stat-info">
                <div class="stat-label">منتجات مخفية</div>
                <div class="stat-value"><?php echo count( $hidden ); ?> منتج</div>
            </div>
        </div>

        <div class="store-api-stat-card stat-ok">
            <div class="stat-icon">✏️</div>
            <div class="stat-info">
                <div class="stat-label">منتجات مخصَّصة</div>
                <div class="stat-value"><?php echo count( $overrides ); ?> منتج</div>
            </div>
        </div>

        <div class="store-api-stat-card stat-ok">
            <div class="stat-icon">📦</div>
            <div class="stat-info">
                <div class="stat-label">طلبات التطبيق (30 يوم)</div>
                <div class="stat-value"><?php echo $app_orders; ?> طلب</div>
            </div>
        </div>

        <div class="store-api-stat-card stat-ok">
            <div class="stat-icon">🔀</div>
            <div class="stat-info">
                <div class="stat-label">تصنيفات مدمجة</div>
                <div class="stat-value"><?php echo count( $merged ); ?> دمج</div>
            </div>
        </div>

    </div>

    <!-- ── Endpoints ─────────────────────────────────────────── -->
    <div class="store-api-card">
        <div class="store-api-card-header">
            <span class="dashicons dashicons-rest-api"></span>
            <h2>الـ Endpoints</h2>
            <span class="store-api-badge"><?php echo count( $endpoints ); ?> endpoints</span>
        </div>
        <div class="store-api-card-body" style="padding:0">
            <div class="store-api-table-wrap">
                <table class="store-api-table">
                    <thead>
                        <tr>
                            <th style="width:70px">Method</th>
                            <th>الرابط الكامل</th>
                            <th style="width:160px">الوصف</th>
                            <th style="width:60px">نسخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $endpoints as $ep ) :
                            $full_url    = rtrim( $rest_url, '/' ) . $ep['path'];
                            $method_cls  = $ep['method'] === 'GET' ? 'method-get' : 'method-post';
                            $uid         = 'ep-' . md5( $ep['path'] );
                        ?>
                        <tr>
                            <td><span class="store-api-method <?php echo esc_attr( $method_cls ); ?>"><?php echo esc_html( $ep['method'] ); ?></span></td>
                            <td><code id="<?php echo esc_attr( $uid ); ?>" class="store-api-endpoint-url"><?php echo esc_html( $full_url ); ?></code></td>
                            <td><?php echo esc_html( $ep['desc'] ); ?></td>
                            <td>
                                <button type="button" class="button button-small store-api-copy-endpoint" data-target="<?php echo esc_attr( $uid ); ?>">📋</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── Base URL + Header ──────────────────────────────────── -->
    <div class="store-api-card">
        <div class="store-api-card-header">
            <span class="dashicons dashicons-admin-links"></span>
            <h2>Base URL والـ Header المطلوب</h2>
        </div>
        <div class="store-api-card-body">

            <div class="store-api-field">
                <label>Base URL</label>
                <div class="store-api-input-group">
                    <input type="text" id="status-base-url" class="large-text" value="<?php echo esc_attr( rtrim( $rest_url, '/' ) ); ?>" readonly />
                    <button type="button" class="button store-api-copy-btn" data-target="status-base-url">📋 نسخ</button>
                </div>
            </div>

            <div class="store-api-field" style="margin-top:16px">
                <label>API Key Header</label>
                <div class="store-api-input-group">
                    <code style="padding:6px 10px; background:#f6f7f7; border:1px solid #ddd; border-radius:4px">X-Store-Api-Key</code>
                    <input type="password" id="status-api-key" class="regular-text" value="<?php echo esc_attr( $api_key ); ?>" readonly />
                    <button type="button" class="button store-api-toggle-password" data-target="status-api-key">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                    <button type="button" class="button store-api-copy-btn" data-target="status-api-key">📋 نسخ</button>
                </div>
            </div>

        </div>
    </div>

    <!-- ── معلومات النظام ─────────────────────────────────────── -->
    <div class="store-api-card">
        <div class="store-api-card-header">
            <span class="dashicons dashicons-info"></span>
            <h2>معلومات النظام</h2>
        </div>
        <div class="store-api-card-body" style="padding:0">
            <table class="store-api-table">
                <tbody>
                    <tr><td><strong>إصدار البلجن</strong></td><td><?php echo esc_html( STORE_API_VERSION ); ?></td></tr>
                    <tr><td><strong>WordPress</strong></td><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
                    <tr><td><strong>WooCommerce</strong></td><td><?php echo esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : '—' ); ?></td></tr>
                    <tr><td><strong>PHP</strong></td><td><?php echo esc_html( PHP_VERSION ); ?></td></tr>
                    <tr><td><strong>Namespace</strong></td><td><code>store/v1</code></td></tr>
                    <tr><td><strong>عملة المتجر</strong></td><td><?php echo esc_html( get_woocommerce_currency() ); ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
