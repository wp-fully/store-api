<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="store-api-section" id="categories-manager">

    <!-- ── شرح ────────────────────────────────────────────── -->
    <div class="store-api-notice store-api-notice-info">
        💡 التصنيفات تُجلب من WooCommerce تلقائياً.
        فعّل ما تريد إظهاره، رتّبه، وخصّص اسمه وصورة التطبيق (أساسية أو بديلة).
        <strong>لا يؤثر على الموقع.</strong>
    </div>

    <!-- ── جدول التصنيفات ─────────────────────────────────── -->
    <div class="store-api-card">
        <div class="store-api-card-header">
            <span class="dashicons dashicons-category"></span>
            <h2>التصنيفات</h2>
            <span class="store-api-badge" id="cats-visible-count">0 ظاهر</span>
            <div class="store-api-cats-tools">
                <input type="search" id="cats-search" class="regular-text" placeholder="بحث بالاسم أو #ID..." />
            </div>
            <div style="margin-right:auto; display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" class="button" id="btn-reload-cats">
                    🔄 تحديث من WooCommerce
                </button>
                <button type="button" class="button button-primary" id="btn-save-cats">
                    💾 حفظ التصنيفات
                </button>
            </div>
        </div>
        <div class="store-api-card-body" style="padding:0">

            <!-- Loading -->
            <div id="cats-loading" class="store-api-loading">
                <span class="spinner is-active"></span>
                جاري تحميل التصنيفات...
            </div>

            <!-- الجدول -->
            <div id="cats-table-wrap" style="display:none">
                <table class="store-api-table" id="cats-table">
                    <thead>
                        <tr>
                            <th style="width:36px">⠿</th>
                            <th style="width:60px">صورة WooCommerce</th>
                            <th>اسم التصنيف</th>
                            <th style="width:80px">عدد المنتجات</th>
                            <th style="width:310px">صورة التطبيق</th>
                            <th style="width:180px">الاسم في الـ API</th>
                            <th style="width:180px">ملاحظة (داخلية)</th>
                            <th style="width:70px; text-align:center">ظاهر</th>
                        </tr>
                    </thead>
                    <tbody id="cats-tbody">
                        <!-- تُملأ بالـ JS -->
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- ── دمج التصنيفات ──────────────────────────────────── -->
    <div class="store-api-card">
        <div class="store-api-card-header">
            <span class="dashicons dashicons-editor-ul"></span>
            <h2>دمج التصنيفات</h2>
            <span class="store-api-badge" id="merged-count">0 دمج</span>
            <button type="button" class="button button-secondary" id="btn-add-merge" style="margin-right:auto">
                ➕ إضافة دمج
            </button>
        </div>
        <div class="store-api-card-body">

            <p class="description" style="margin-bottom:16px">
                يجمع منتجات تصنيفين أو أكثر تحت تصنيف واحد في الـ API.
                <strong>Display ID</strong> هو التصنيف الذي سيظهر للتطبيق.
                <strong>Merge IDs</strong> تشمل Display ID نفسه.
            </p>

            <div id="merged-list">
                <!-- تُملأ بالـ JS -->
            </div>

        </div>
    </div>

</div>

<!-- Template: صف تصنيف -->
<template id="tmpl-cat-row">
    <tr class="store-api-cat-row" data-id="{{id}}">
        <td class="col-drag">
            <span class="dashicons dashicons-menu drag-handle" title="اسحب لإعادة الترتيب"></span>
        </td>
        <td class="col-thumb">
            <img src="{{thumbnail}}" alt="" class="cat-thumb" onerror="this.style.display='none'" />
            <span class="cat-no-thumb" style="{{thumb_hidden}}">
                <span class="dashicons dashicons-category"></span>
            </span>
        </td>
        <td class="col-name">
            <strong>{{name}}</strong>
            <span class="cat-id-badge">#{{id}}</span>
            <span class="merged-sub-badge" style="{{merged_hidden}}">مدمج</span>
        </td>
        <td class="col-count">{{count}}</td>
        <td class="col-api-image">
            <div class="cat-api-image-controls">
                <select class="cat-image-mode" data-id="{{id}}">
                    <option value="primary" {{mode_primary_selected}}>الصورة الأساسية</option>
                    <option value="alternative" {{mode_alternative_selected}}>صورة بديلة</option>
                </select>
                <input
                    type="url"
                    class="regular-text cat-alt-image"
                    value="{{alternative_image}}"
                    placeholder="https://example.com/category.jpg"
                    data-id="{{id}}"
                    style="{{alt_input_hidden}}"
                />
            </div>
            <div class="cat-api-preview">
                <img src="{{api_image}}" alt="" class="cat-thumb cat-api-thumb" style="{{api_thumb_hidden}}" onerror="this.style.display='none';this.parentElement.querySelector('.cat-api-no-thumb').style.display='inline-flex'" />
                <span class="cat-no-thumb cat-api-no-thumb" style="{{api_no_thumb_hidden}}">
                    <span class="dashicons dashicons-format-image"></span>
                </span>
                <span class="cat-api-preview-label">{{api_image_label}}</span>
            </div>
        </td>
        <td class="col-display-name">
            <input
                type="text"
                class="regular-text cat-display-name"
                value="{{display_name}}"
                placeholder="{{name}}"
                data-id="{{id}}"
            />
        </td>
        <td class="col-note">
            <input
                type="text"
                class="regular-text cat-note"
                value="{{note}}"
                placeholder="ملاحظة لك فقط..."
                data-id="{{id}}"
            />
        </td>
        <td class="col-visible" style="text-align:center">
            <label class="store-api-toggle-label" style="justify-content:center">
                <input
                    type="checkbox"
                    class="store-api-toggle-input cat-visible"
                    data-id="{{id}}"
                    {{checked}}
                />
                <span class="store-api-toggle-slider"></span>
            </label>
        </td>
    </tr>
</template>

<!-- Template: صف دمج -->
<template id="tmpl-merge-row">
    <div class="store-api-merge-row" data-index="{{index}}">
        <div class="store-api-merge-row-header">
            <strong>دمج #{{num}}</strong>
            <button type="button" class="button-link store-api-delete-merge">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </div>
        <div class="store-api-merge-row-body">
            <div class="store-api-merge-field">
                <label>Display ID</label>
                <input type="number" class="small-text merge-display-id" value="{{display_id}}" min="1" placeholder="38" />
            </div>
            <div class="store-api-merge-field">
                <label>الاسم الظاهر</label>
                <input type="text" class="regular-text merge-display-name" value="{{display_name}}" placeholder="اختياري" />
            </div>
            <div class="store-api-merge-field">
                <label>Merge IDs (مفصولة بفاصلة)</label>
                <input type="text" class="regular-text merge-ids" value="{{merge_ids}}" placeholder="مثال: 38,105" />
            </div>
        </div>
    </div>
</template>
