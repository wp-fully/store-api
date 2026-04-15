<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// جلب التصنيفات المفعّلة فقط للـ Dropdown
$visible_cats = Store_API_Config::categories_config();
$all_cats_raw = Store_API_Config::categories_config_all();

// خريطة ID => اسم لكل التصنيفات
$cat_names = [];
foreach ( $all_cats_raw as $entry ) {
    $term = get_term( (int) $entry['id'], 'product_cat' );
    if ( $term && ! is_wp_error( $term ) ) {
        $cat_names[ $entry['id'] ] = ! empty( $entry['display_name'] )
            ? $entry['display_name'] . ' (' . $term->name . ')'
            : $term->name;
    }
}
?>

<div class="store-api-section" id="products-manager">

    <!-- ── شرح ────────────────────────────────────────────── -->
    <div class="store-api-notice store-api-notice-info">
        💡 التخصيصات هنا <strong>لا تؤثر على الموقع أو WooCommerce</strong> — تظهر فقط في استجابات الـ API للتطبيق.
        يمكنك تخصيص الاسم والسعر والوصف أو إخفاء المنتج كلياً.
    </div>

    <!-- ── اختيار التصنيف ──────────────────────────────────── -->
    <div class="store-api-card">
        <div class="store-api-card-header">
            <span class="dashicons dashicons-filter"></span>
            <h2>اختر التصنيف</h2>
        </div>
        <div class="store-api-card-body">

            <?php if ( empty( $visible_cats ) ) : ?>
                <div class="store-api-notice store-api-notice-warning">
                    ⚠️ لا توجد تصنيفات مفعّلة —
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=store-api-settings&tab=categories' ) ); ?>">
                        فعّل تصنيفاً أولاً
                    </a>
                </div>
            <?php else : ?>
                <div class="store-api-field">
                    <div class="store-api-input-group">
                        <select id="products-cat-select" class="regular-text">
                            <option value="">— اختر تصنيفاً —</option>
                            <?php foreach ( $visible_cats as $entry ) :
                                $tid  = (int) $entry['id'];
                                $name = $cat_names[ $tid ] ?? "تصنيف #{$tid}";
                            ?>
                                <option value="<?php echo esc_attr( $tid ); ?>">
                                    <?php echo esc_html( $name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="button button-primary" id="btn-load-products">
                            📦 تحميل المنتجات
                        </button>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- ── قائمة المنتجات ─────────────────────────────────── -->
    <div class="store-api-card" id="products-list-card" style="display:none">
        <div class="store-api-card-header">
            <span class="dashicons dashicons-products"></span>
            <h2 id="products-list-title">المنتجات</h2>
            <span class="store-api-badge" id="products-count-badge">0 منتج</span>
            <button
                type="button"
                class="button"
                id="btn-save-product-order"
                style="display:none"
                title="حفظ ترتيب المنتجات الحالي"
            >
                💾 حفظ الترتيب
            </button>
            <div id="products-pagination" style="margin-right:auto; display:flex; align-items:center; gap:8px;">
                <button type="button" class="button" id="btn-prev-page" disabled>◀ السابق</button>
                <span id="products-page-info" style="font-size:13px; color:#646970">صفحة 1</span>
                <button type="button" class="button" id="btn-next-page" disabled>التالي ▶</button>
            </div>
        </div>
        <div class="store-api-card-body" style="padding:0">

            <!-- Loading -->
            <div id="products-loading" class="store-api-loading" style="display:none">
                <span class="spinner is-active"></span>
                جاري تحميل المنتجات...
            </div>

            <!-- الجدول -->
            <div id="products-table-wrap">
                <table class="store-api-table" id="products-table">
                    <thead>
                        <tr>
                            <th style="width:36px">⠿</th> <!-- ✅ Drag Handle -->
                            <th style="width:50px">صورة</th>
                            <th>اسم المنتج</th>
                            <th style="width:90px">السعر الأصلي</th>
                            <th style="width:80px">النوع</th>
                            <th style="width:100px">المخزون</th>
                            <th style="width:160px">اسم مخصص</th>
                            <th style="width:120px">سعر مخصص</th>
                            <th style="width:180px">وصف مخصص</th>
                            <th style="width:70px; text-align:center">مخفي</th>
                            <th style="width:70px; text-align:center">حفظ</th>
                        </tr>
                    </thead>
                    <tbody id="products-tbody">
                        <!-- تُملأ بالـ JS -->
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- ── إحصائيات التخصيصات ────────────────────────────── -->
    <?php
    $overrides     = Store_API_Config::product_overrides();
    $hidden_count  = 0;
    $custom_count  = 0;

    foreach ( $overrides as $pid => $data ) {
        if ( ! empty( $data['hidden'] ) )   $hidden_count++;
        if (
            ! empty( $data['name'] )
            || ! empty( $data['price'] )
            || ! empty( $data['description'] )
        ) $custom_count++;
    }
    ?>

    <?php if ( ! empty( $overrides ) ) : ?>
    <div class="store-api-card">
        <div class="store-api-card-header">
            <span class="dashicons dashicons-chart-bar"></span>
            <h2>ملخص التخصيصات</h2>
        </div>
        <div class="store-api-card-body">
            <div class="store-api-stats-grid">

                <div class="store-api-stat-card stat-ok">
                    <div class="stat-icon">📦</div>
                    <div class="stat-info">
                        <div class="stat-label">إجمالي المخصَّصة</div>
                        <div class="stat-value"><?php echo count( $overrides ); ?> منتج</div>
                    </div>
                </div>

                <div class="store-api-stat-card stat-error">
                    <div class="stat-icon">🙈</div>
                    <div class="stat-info">
                        <div class="stat-label">المخفية</div>
                        <div class="stat-value"><?php echo $hidden_count; ?> منتج</div>
                    </div>
                </div>

                <div class="store-api-stat-card stat-warn">
                    <div class="stat-icon">✏️</div>
                    <div class="stat-info">
                        <div class="stat-label">بها تخصيص</div>
                        <div class="stat-value"><?php echo $custom_count; ?> منتج</div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Template: صف منتج مع Drag Handle -->
<template id="tmpl-product-row">
    <tr class="store-api-product-row {{hidden_class}}" data-id="{{id}}">
        <td class="col-drag">
            <span class="dashicons dashicons-menu drag-handle" title="اسحب لإعادة الترتيب"></span>
        </td>
        <td class="col-thumb">
            <img src="{{thumbnail}}" alt="" class="product-thumb" onerror="this.style.display='none'" />
            <span class="product-no-thumb" style="{{thumb_hidden}}">
                <span class="dashicons dashicons-format-image"></span>
            </span>
        </td>
        <td class="col-name">
            <a href="{{edit_url}}" target="_blank" class="product-name-link">{{name}}</a>
            <span class="cat-id-badge">#{{id}}</span>
        </td>
        <td class="col-price">
            <span class="product-original-price">{{price}}</span>
        </td>
        <td class="col-type">
            <span class="store-api-type-badge store-api-type-{{type}}">{{type_label}}</span>
        </td>
        <td class="col-stock">
            <span class="store-api-status {{stock_class}}">{{stock_label}}</span>
        </td>
        <td class="col-custom-name">
            <input
                type="text"
                class="regular-text product-custom-name"
                value="{{custom_name}}"
                placeholder="{{name}}"
                data-id="{{id}}"
            />
        </td>
        <td class="col-custom-price">
            <input
                type="number"
                class="small-text product-custom-price"
                value="{{custom_price}}"
                placeholder="{{price}}"
                min="0"
                step="0.01"
                data-id="{{id}}"
            />
        </td>
        <td class="col-custom-desc">
            <textarea
                class="product-custom-desc"
                rows="2"
                placeholder="وصف مخصص للتطبيق..."
                data-id="{{id}}"
            >{{custom_desc}}</textarea>
        </td>
        <td class="col-hidden" style="text-align:center">
            <label class="store-api-toggle-label" style="justify-content:center">
                <input
                    type="checkbox"
                    class="store-api-toggle-input product-hidden"
                    data-id="{{id}}"
                    {{hidden_checked}}
                />
                <span class="store-api-toggle-slider store-api-toggle-danger"></span>
            </label>
        </td>
        <td class="col-save" style="text-align:center">
            <button
                type="button"
                class="button button-small btn-save-product"
                data-id="{{id}}"
                title="حفظ تخصيصات هذا المنتج"
            >
                💾
            </button>
        </td>
    </tr>
</template>
