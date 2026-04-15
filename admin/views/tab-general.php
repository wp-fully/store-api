<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<form method="post" action="options.php">
    <?php settings_fields( 'store_api_general' ); ?>

    <div class="store-api-section">

        <!-- ── API Key ─────────────────────────────────────── -->
        <div class="store-api-card">
            <div class="store-api-card-header">
                <span class="dashicons dashicons-lock"></span>
                <h2>مفتاح الـ API</h2>
            </div>
            <div class="store-api-card-body">

                <?php $current_key = get_option( 'store_api_key', '' ); ?>

                <?php if ( $current_key === '' ) : ?>
                    <div class="store-api-notice store-api-notice-error">
                        ❌ لم يتم تعيين API Key — الـ API غير متاح حتى تحدد مفتاحاً واحفظ.
                    </div>
                <?php endif; ?>

                <div class="store-api-field">
                    <label for="store_api_key">API Key</label>
                    <div class="store-api-input-group">
                        <input
                            type="password"
                            id="store_api_key"
                            name="store_api_key"
                            value="<?php echo esc_attr( $current_key ); ?>"
                            class="regular-text"
                            autocomplete="off"
                            placeholder="sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                        />
                        <button type="button" class="button store-api-toggle-password" data-target="store_api_key">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                        <button type="button" class="button store-api-generate-key" data-target="store_api_key">
                            🔄 توليد
                        </button>
                        <button type="button" class="button store-api-copy-btn" data-target="store_api_key">
                            📋 نسخ
                        </button>
                    </div>
                    <p class="description">
                        أرسله في كل طلب داخل الـ Header:
                        <code>X-Store-Api-Key: YOUR_KEY</code>
                    </p>
                </div>

            </div>
        </div>

        <!-- ── CORS ────────────────────────────────────────── -->
        <div class="store-api-card">
            <div class="store-api-card-header">
                <span class="dashicons dashicons-networking"></span>
                <h2>إعدادات CORS</h2>
            </div>
            <div class="store-api-card-body">
                <div class="store-api-field">
                    <label class="store-api-toggle-label">
                        <input
                            type="checkbox"
                            name="store_cors_enabled"
                            value="1"
                            class="store-api-toggle-input"
                            <?php checked( get_option( 'store_cors_enabled', '1' ), '1' ); ?>
                        />
                        <span class="store-api-toggle-slider"></span>
                        <span class="store-api-toggle-text">تفعيل CORS Headers</span>
                    </label>
                    <p class="description">
                        يسمح للتطبيق بالوصول للـ API من أي domain.
                        لا تعطّله إلا لو عندك إعداد CORS مخصص على السيرفر.
                    </p>
                </div>
            </div>
        </div>

        <!-- ── Telr ────────────────────────────────────────── -->
        <div class="store-api-card">
            <div class="store-api-card-header">
                <span class="dashicons dashicons-credit-card"></span>
                <h2>بوابة Telr</h2>
            </div>
            <div class="store-api-card-body">

                <?php if ( (int) get_option( 'store_telr_test_mode', 0 ) === 1 ) : ?>
                    <div class="store-api-notice store-api-notice-warning">
                        ⚠️ وضع الاختبار مفعّل — المدفوعات الحقيقية معطّلة.
                    </div>
                <?php endif; ?>

                <div class="store-api-field">
                    <label for="store_telr_store_id">Store ID</label>
                    <input
                        type="text"
                        id="store_telr_store_id"
                        name="store_telr_store_id"
                        value="<?php echo esc_attr( get_option( 'store_telr_store_id', '' ) ); ?>"
                        class="regular-text"
                        placeholder="مثال: 33922"
                    />
                </div>

                <div class="store-api-field">
                    <label for="store_telr_auth_key">Auth Key</label>
                    <div class="store-api-input-group">
                        <input
                            type="password"
                            id="store_telr_auth_key"
                            name="store_telr_auth_key"
                            value="<?php echo esc_attr( get_option( 'store_telr_auth_key', '' ) ); ?>"
                            class="regular-text"
                            autocomplete="off"
                            placeholder="مفتاح المصادقة من لوحة Telr"
                        />
                        <button type="button" class="button store-api-toggle-password" data-target="store_telr_auth_key">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                </div>

                <div class="store-api-field">
                    <label class="store-api-toggle-label">
                        <input
                            type="checkbox"
                            name="store_telr_test_mode"
                            value="1"
                            class="store-api-toggle-input"
                            <?php checked( get_option( 'store_telr_test_mode', '0' ), '1' ); ?>
                        />
                        <span class="store-api-toggle-slider"></span>
                        <span class="store-api-toggle-text">وضع الاختبار (Test Mode)</span>
                    </label>
                    <p class="description">فعّله للاختبار فقط — عطّله في Production.</p>
                </div>

            </div>
        </div>

    </div>

    <?php submit_button( '💾 حفظ الإعدادات', 'primary store-api-submit' ); ?>

</form>
