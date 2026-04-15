/* ============================================================
   Store API v6 — Admin JavaScript
   ============================================================ */

( function () {
    'use strict';

    // ── State ─────────────────────────────────────────────────
    const state = {
        categories  : [],   // كل تصنيفات WooCommerce
        merged      : [],   // قائمة الدمج
        catsDirty   : false,
        products    : {
            catId      : 0,
            page       : 1,
            totalPages : 1,
        },
    };
    let catsBeforeUnloadBound = false;

    // ── Helpers ───────────────────────────────────────────────
    const $  = ( sel, ctx = document ) => ctx.querySelector( sel );
    const $$ = ( sel, ctx = document ) => [ ...ctx.querySelectorAll( sel ) ];
    const nonce    = () => StoreAPI.nonce;
    const ajaxUrl  = () => StoreAPI.ajax_url;
    const strings  = () => StoreAPI.strings;

    // ════════════════════════════════════════════════════════════
    // BOOT
    // ════════════════════════════════════════════════════════════
    document.addEventListener( 'DOMContentLoaded', function () {
        initGeneral();
        initCategories();
        initProducts();
        initStatus();
    } );

    // ════════════════════════════════════════════════════════════
    // 1. GENERAL TAB
    // ════════════════════════════════════════════════════════════
    function initGeneral() {

        // إظهار/إخفاء كلمة المرور
        $$( '.store-api-toggle-password' ).forEach( btn => {
            btn.addEventListener( 'click', function () {
                const input = document.getElementById( this.dataset.target );
                if ( ! input ) return;
                const icon = this.querySelector( '.dashicons' );
                if ( input.type === 'password' ) {
                    input.type = 'text';
                    icon?.classList.replace( 'dashicons-visibility', 'dashicons-hidden' );
                } else {
                    input.type = 'password';
                    icon?.classList.replace( 'dashicons-hidden', 'dashicons-visibility' );
                }
            } );
        } );

        // توليد API Key
        $$( '.store-api-generate-key' ).forEach( btn => {
            btn.addEventListener( 'click', function () {
                if ( ! confirm( strings().confirm_generate ) ) return;
                const input = document.getElementById( this.dataset.target );
                if ( ! input ) return;
                const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                let key = 'sk_';
                for ( let i = 0; i < 32; i++ ) {
                    key += chars[ Math.floor( Math.random() * chars.length ) ];
                }
                input.value = key;
                input.type  = 'text';
                showFeedback( this, strings().generated, 'ok' );
            } );
        } );

        // نسخ قيمة Input
        $$( '.store-api-copy-btn' ).forEach( btn => {
            btn.addEventListener( 'click', function () {
                const input = document.getElementById( this.dataset.target );
                if ( ! input ) return;
                copyText( input.value || input.innerText || '', this );
            } );
        } );

        // نسخ Endpoint
        $$( '.store-api-copy-endpoint' ).forEach( btn => {
            btn.addEventListener( 'click', function () {
                const el = document.getElementById( this.dataset.target );
                if ( ! el ) return;
                copyText( el.innerText.trim(), this );
            } );
        } );
    }

    // ════════════════════════════════════════════════════════════
    // 2. CATEGORIES TAB
    // ════════════════════════════════════════════════════════════
    function initCategories() {

        const wrap      = $( '#categories-manager' );
        if ( ! wrap ) return;

        const tbody     = $( '#cats-tbody' );
        const loading   = $( '#cats-loading' );
        const tableWrap = $( '#cats-table-wrap' );
        const badge     = $( '#cats-visible-count' );
        const catsSearch = $( '#cats-search' );
        const mergedList= $( '#merged-list' );
        const mergedBadge = $( '#merged-count' );

        if ( ! catsBeforeUnloadBound ) {
            window.addEventListener( 'beforeunload', function ( e ) {
                if ( ! state.catsDirty ) return;
                e.preventDefault();
                e.returnValue = '';
            } );
            catsBeforeUnloadBound = true;
        }

        function normalizeCategory( cat, index ) {
            return {
                ...cat,
                id: parseInt( cat.id ),
                visible: !! cat.visible,
                display_name: cat.display_name || '',
                note: cat.note || '',
                sort_order: Number.isFinite( Number( cat.sort_order ) ) ? Number( cat.sort_order ) : index,
                image_mode: cat.image_mode === 'alternative' ? 'alternative' : 'primary',
                alternative_image: cat.alternative_image || '',
                thumbnail: cat.thumbnail || '',
            };
        }

        function getCategoryApiImage( cat ) {
            const alternative = ( cat.alternative_image || '' ).trim();
            if ( cat.image_mode === 'alternative' && alternative ) {
                return alternative;
            }
            return cat.thumbnail || '';
        }

        function getCategoryImageSourceLabel( cat ) {
            const hasAlternative = ( cat.alternative_image || '' ).trim() !== '';
            if ( cat.image_mode === 'alternative' ) {
                return hasAlternative
                    ? 'المصدر: الصورة البديلة'
                    : 'المصدر: الأساسية (لا يوجد رابط بديل)';
            }
            return 'المصدر: الصورة الأساسية';
        }

        // ── تحميل التصنيفات ────────────────────────────────────
        function loadCategories() {
            loading.style.display  = 'flex';
            tableWrap.style.display= 'none';

            ajax( 'store_api_get_wc_categories', {}, function ( res ) {
                loading.style.display   = 'none';
                tableWrap.style.display = 'block';
                state.categories = ( res.categories || [] ).map( ( cat, i ) => normalizeCategory( cat, i ) );
                state.merged     = res.merged     || [];
                state.catsDirty  = false;

                renderCatsTable();
                renderMergedList();
            }, function ( err ) {
                loading.innerHTML = `<span style="color:red">❌ ${err}</span>`;
            } );
        }

        // ── رسم جدول التصنيفات ────────────────────────────────
        function renderCatsTable() {
            tbody.innerHTML = '';

            if ( ! state.categories.length ) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#999;padding:20px">لا توجد تصنيفات في WooCommerce</td></tr>';
                updateVisibleBadge();
                return;
            }

            state.categories.forEach( ( cat, i ) => {
                const tmpl = $( '#tmpl-cat-row' );
                if ( ! tmpl ) return;
                const apiImage = getCategoryApiImage( cat );

                let html = tmpl.innerHTML
                    .replace( /\{\{id\}\}/g,          cat.id )
                    .replace( /\{\{name\}\}/g,         escHtml( cat.name ) )
                    .replace( /\{\{count\}\}/g,        cat.count )
                    .replace( /\{\{display_name\}\}/g, escHtml( cat.display_name || '' ) )
                    .replace( /\{\{note\}\}/g,         escHtml( cat.note || '' ) )
                    .replace( /\{\{checked\}\}/g,      cat.visible ? 'checked' : '' )
                    .replace( /\{\{thumbnail\}\}/g,    cat.thumbnail || '' )
                    .replace( /\{\{thumb_hidden\}\}/g, cat.thumbnail ? 'display:none' : '' )
                    .replace( /\{\{merged_hidden\}\}/g,cat.is_merged_sub ? '' : 'display:none' )
                    .replace( /\{\{mode_primary_selected\}\}/g,      cat.image_mode === 'primary' ? 'selected' : '' )
                    .replace( /\{\{mode_alternative_selected\}\}/g,  cat.image_mode === 'alternative' ? 'selected' : '' )
                    .replace( /\{\{alternative_image\}\}/g,           escHtml( cat.alternative_image || '' ) )
                    .replace( /\{\{alt_input_hidden\}\}/g,            cat.image_mode === 'alternative' ? '' : 'display:none' )
                    .replace( /\{\{api_image\}\}/g,                   apiImage )
                    .replace( /\{\{api_thumb_hidden\}\}/g,            apiImage ? '' : 'display:none' )
                    .replace( /\{\{api_no_thumb_hidden\}\}/g,         apiImage ? 'display:none' : '' )
                    .replace( /\{\{api_image_label\}\}/g,             getCategoryImageSourceLabel( cat ) );

                const tr = document.createElement( 'tr' );
                tr.className   = 'store-api-cat-row';
                tr.dataset.id  = cat.id;
                tr.dataset.idx = i;
                tr.innerHTML   = html.replace( /<tr[^>]*>|<\/tr>/gi, '' );
                tbody.appendChild( tr );

                updateCategoryImageUI( tr, cat );
            } );

            updateVisibleBadge();
            bindCatEvents();
            initDragSort();
            applyCatsFilter();
        }

        function updateCategoryImageUI( row, cat ) {
            if ( ! row || ! cat ) return;

            const modeSelect   = $( '.cat-image-mode', row );
            const altInput     = $( '.cat-alt-image', row );
            const previewImg   = $( '.cat-api-thumb', row );
            const emptyPreview = $( '.cat-api-no-thumb', row );
            const previewLabel = $( '.cat-api-preview-label', row );
            const imageSrc     = getCategoryApiImage( cat );

            if ( modeSelect ) {
                modeSelect.value = cat.image_mode === 'alternative' ? 'alternative' : 'primary';
            }

            if ( altInput ) {
                altInput.style.display = cat.image_mode === 'alternative' ? '' : 'none';
                altInput.value = cat.alternative_image || '';
            }

            if ( previewImg ) {
                if ( imageSrc ) {
                    previewImg.src = imageSrc;
                    previewImg.style.display = 'inline-block';
                } else {
                    previewImg.removeAttribute( 'src' );
                    previewImg.style.display = 'none';
                }
            }

            if ( emptyPreview ) {
                emptyPreview.style.display = imageSrc ? 'none' : 'inline-flex';
            }

            if ( previewLabel ) {
                previewLabel.textContent = getCategoryImageSourceLabel( cat );
            }
        }

        function applyCatsFilter() {
            const query = ( catsSearch?.value || '' ).trim().toLowerCase();
            if ( ! query ) {
                $$( '.store-api-cat-row', tbody ).forEach( row => {
                    row.style.display = '';
                } );
                return;
            }

            $$( '.store-api-cat-row', tbody ).forEach( row => {
                const id    = row.dataset.id || '';
                const title = ( $( '.col-name strong', row )?.textContent || '' ).toLowerCase();
                row.style.display = title.includes( query ) || id.includes( query ) ? '' : 'none';
            } );
        }

        // ── Bind أحداث صفوف التصنيفات ─────────────────────────
        function bindCatEvents() {

            // Toggle visible
            $$( '.cat-visible', tbody ).forEach( cb => {
                cb.addEventListener( 'change', function () {
                    const id  = parseInt( this.dataset.id );
                    const cat = state.categories.find( c => c.id === id );
                    if ( cat ) cat.visible = this.checked;
                    updateVisibleBadge();
                    state.catsDirty = true;
                } );
            } );

            // display_name
            $$( '.cat-display-name', tbody ).forEach( inp => {
                inp.addEventListener( 'input', function () {
                    const id  = parseInt( this.dataset.id );
                    const cat = state.categories.find( c => c.id === id );
                    if ( cat ) cat.display_name = this.value;
                    state.catsDirty = true;
                } );
            } );

            // note
            $$( '.cat-note', tbody ).forEach( inp => {
                inp.addEventListener( 'input', function () {
                    const id  = parseInt( this.dataset.id );
                    const cat = state.categories.find( c => c.id === id );
                    if ( cat ) cat.note = this.value;
                    state.catsDirty = true;
                } );
            } );

            // image mode
            $$( '.cat-image-mode', tbody ).forEach( select => {
                select.addEventListener( 'change', function () {
                    const id  = parseInt( this.dataset.id );
                    const row = this.closest( '.store-api-cat-row' );
                    const cat = state.categories.find( c => c.id === id );
                    if ( ! cat ) return;

                    cat.image_mode = this.value === 'alternative' ? 'alternative' : 'primary';
                    state.catsDirty = true;
                    updateCategoryImageUI( row, cat );
                } );
            } );

            // alternative image URL
            $$( '.cat-alt-image', tbody ).forEach( input => {
                input.addEventListener( 'input', function () {
                    const id  = parseInt( this.dataset.id );
                    const row = this.closest( '.store-api-cat-row' );
                    const cat = state.categories.find( c => c.id === id );
                    if ( ! cat ) return;

                    cat.alternative_image = this.value.trim();
                    state.catsDirty = true;
                    updateCategoryImageUI( row, cat );
                } );
            } );
        }

        // ── Drag & Drop Sort ───────────────────────────────────
        function initDragSort() {
            let dragSrc = null;

            $$( '.store-api-cat-row', tbody ).forEach( row => {

                row.setAttribute( 'draggable', 'true' );

                row.addEventListener( 'dragstart', function () {
                    dragSrc = this;
                    setTimeout( () => this.classList.add( 'dragging' ), 0 );
                } );

                row.addEventListener( 'dragend', function () {
                    this.classList.remove( 'dragging' );
                    $$( '.drag-over', tbody ).forEach( r => r.classList.remove( 'drag-over' ) );
                    syncCatOrderFromDOM();
                } );

                row.addEventListener( 'dragover', function ( e ) {
                    e.preventDefault();
                    if ( this !== dragSrc ) {
                        $$( '.drag-over', tbody ).forEach( r => r.classList.remove( 'drag-over' ) );
                        this.classList.add( 'drag-over' );
                    }
                } );

                row.addEventListener( 'drop', function ( e ) {
                    e.preventDefault();
                    if ( this !== dragSrc ) {
                        const rows    = $$( '.store-api-cat-row', tbody );
                        const srcIdx  = rows.indexOf( dragSrc );
                        const destIdx = rows.indexOf( this );
                        if ( srcIdx < destIdx ) {
                            tbody.insertBefore( dragSrc, this.nextSibling );
                        } else {
                            tbody.insertBefore( dragSrc, this );
                        }
                    }
                } );
            } );
        }

        // ── مزامنة الترتيب من الـ DOM ──────────────────────────
        function syncCatOrderFromDOM() {
            const rows     = $$( '.store-api-cat-row', tbody );
            const reordered= [];
            rows.forEach( ( row, i ) => {
                const id  = parseInt( row.dataset.id );
                const cat = state.categories.find( c => c.id === id );
                if ( cat ) {
                    cat.sort_order = i;
                    reordered.push( cat );
                }
            } );
            state.categories = reordered;
            state.catsDirty  = true;
        }

        // ── رسم قائمة الدمج ───────────────────────────────────
        function renderMergedList() {
            mergedList.innerHTML = '';

            if ( ! state.merged.length ) {
                mergedList.innerHTML = '<p style="color:#999; text-align:center; padding:10px 0">لا توجد عمليات دمج — اضغط "إضافة دمج"</p>';
                mergedBadge.textContent = '0 دمج';
                return;
            }

            state.merged.forEach( ( mc, i ) => {
                mergedList.appendChild( buildMergeRow( mc, i ) );
            } );

            mergedBadge.textContent = state.merged.length + ' دمج';
            bindMergeEvents();
        }

        function buildMergeRow( mc, i ) {
            const tmpl = $( '#tmpl-merge-row' );
            if ( ! tmpl ) return document.createElement( 'div' );

            const div = document.createElement( 'div' );
            div.innerHTML = tmpl.innerHTML
                .replace( /\{\{index\}\}/g,        i )
                .replace( /\{\{num\}\}/g,           i + 1 )
                .replace( /\{\{display_id\}\}/g,    mc.display_id   || '' )
                .replace( /\{\{display_name\}\}/g,  escHtml( mc.display_name || '' ) )
                .replace( /\{\{merge_ids\}\}/g,     ( mc.merge_ids || [] ).join( ',' ) );

            const node = div.firstElementChild;
            node.dataset.index = i;
            return node;
        }

        function bindMergeEvents() {
            $$( '.store-api-delete-merge', mergedList ).forEach( btn => {
                btn.addEventListener( 'click', function () {
                    const idx = parseInt( this.closest( '.store-api-merge-row' ).dataset.index );
                    state.merged.splice( idx, 1 );
                    state.catsDirty = true;
                    renderMergedList();
                } );
            } );

            $$( '.merge-display-id, .merge-display-name, .merge-ids', mergedList ).forEach( inp => {
                inp.addEventListener( 'input', function () {
                    syncMergedFromDOM();
                    state.catsDirty = true;
                } );
            } );
        }

        function syncMergedFromDOM() {
            const rows = $$( '.store-api-merge-row', mergedList );
            state.merged = rows.map( row => ( {
                display_id  : parseInt( $( '.merge-display-id',   row ).value ) || 0,
                display_name: $( '.merge-display-name', row ).value.trim(),
                merge_ids   : $( '.merge-ids', row ).value
                                .split( ',' )
                                .map( v => parseInt( v.trim() ) )
                                .filter( v => v > 0 ),
            } ) ).filter( mc => mc.display_id > 0 && mc.merge_ids.length > 0 );
        }

        // ── إضافة دمج جديد ────────────────────────────────────
        const btnAddMerge = $( '#btn-add-merge' );
        if ( btnAddMerge ) {
            btnAddMerge.addEventListener( 'click', function () {
                state.merged.push( { display_id: 0, display_name: '', merge_ids: [] } );
                state.catsDirty = true;
                renderMergedList();
            } );
        }

        // ── حفظ التصنيفات ─────────────────────────────────────
        const btnSave = $( '#btn-save-cats' );
        if ( btnSave ) {
            btnSave.addEventListener( 'click', function () {
                syncCatOrderFromDOM();
                syncMergedFromDOM();

                const catsToSave = state.categories.map( ( cat, i ) => ( {
                    id          : cat.id,
                    visible     : cat.visible     || false,
                    display_name: cat.display_name|| '',
                    note        : cat.note        || '',
                    image_mode  : cat.image_mode === 'alternative' ? 'alternative' : 'primary',
                    alternative_image: ( cat.alternative_image || '' ).trim(),
                    sort_order  : i,
                } ) );

                setBtnLoading( btnSave, true );

                ajax( 'store_api_save_categories', {
                    categories: JSON.stringify( catsToSave ),
                    merged    : JSON.stringify( state.merged ),
                }, function ( res ) {
                    setBtnLoading( btnSave, false );
                    showFeedback( btnSave, `✅ ${res.message}`, 'ok' );
                    state.catsDirty = false;
                }, function ( err ) {
                    setBtnLoading( btnSave, false );
                    showFeedback( btnSave, `❌ ${err}`, 'err' );
                } );
            } );
        }

        // ── تحديث من WooCommerce ───────────────────────────────
        const btnReload = $( '#btn-reload-cats' );
        if ( btnReload ) {
            btnReload.addEventListener( 'click', loadCategories );
        }

        if ( catsSearch ) {
            catsSearch.addEventListener( 'input', applyCatsFilter );
        }

        // ── Badge العدد ────────────────────────────────────────
        function updateVisibleBadge() {
            const count = state.categories.filter( c => c.visible ).length;
            if ( badge ) badge.textContent = count + ' ظاهر';
        }

        // ── تحميل تلقائي عند وجود الصفحة ──────────────────────
        loadCategories();
    }

    // ════════════════════════════════════════════════════════════
    // 3. PRODUCTS TAB
    // ════════════════════════════════════════════════════════════
    function initProducts() {

        const wrap = $( '#products-manager' );
        if ( ! wrap ) return;

        const tbody      = $( '#products-tbody' );
        const listCard   = $( '#products-list-card' );
        const loading    = $( '#products-loading' );
        const tableWrap  = $( '#products-table-wrap' );
        const title      = $( '#products-list-title' );
        const countBadge = $( '#products-count-badge' );
        const pageInfo   = $( '#products-page-info' );
        const btnPrev    = $( '#btn-prev-page' );
        const btnNext    = $( '#btn-next-page' );
        const catSelect  = $( '#products-cat-select' );
        const btnLoad    = $( '#btn-load-products' );

        if ( ! btnLoad ) return;

        // ── تحميل منتجات تصنيف ────────────────────────────────
        function loadProducts( page = 1 ) {
            const catId = catSelect ? parseInt( catSelect.value ) : 0;
            if ( ! catId ) return;

            state.products.catId = catId;
            state.products.page  = page;

            listCard.style.display  = 'block';
            loading.style.display   = 'flex';
            tableWrap.style.display = 'none';

            ajax( 'store_api_get_cat_products', {
                cat_id: catId,
                page  : page,
            }, function ( res ) {
                loading.style.display   = 'none';
                tableWrap.style.display = 'block';

                state.products.totalPages = res.total_pages;

                if ( title )      title.textContent      = catSelect.options[ catSelect.selectedIndex ]?.text || 'المنتجات';
                if ( countBadge ) countBadge.textContent  = res.total + ' منتج';
                if ( pageInfo )   pageInfo.textContent    = `صفحة ${res.page} من ${res.total_pages}`;

                btnPrev && ( btnPrev.disabled = page <= 1 );
                btnNext && ( btnNext.disabled = page >= res.total_pages );

                renderProductsTable( res.products );
            }, function ( err ) {
                loading.style.display = 'none';
                loading.innerHTML     = `<span style="color:red">❌ ${err}</span>`;
            } );
        }

        // ── رسم جدول المنتجات ─────────────────────────────────
        function renderProductsTable( products ) {
            tbody.innerHTML = '';

            if ( ! products.length ) {
                tbody.innerHTML = `<tr><td colspan="10" style="text-align:center;color:#999;padding:20px">${strings().no_products}</td></tr>`;
                return;
            }

            const tmpl = $( '#tmpl-product-row' );
            if ( ! tmpl ) return;

            products.forEach( p => {
                const typeLabels  = { simple: 'بسيط', variable: 'متغير' };
                const stockLabels = { instock: 'متوفر', outofstock: 'غير متوفر', onbackorder: 'بالطلب' };
                const stockClass  = { instock: 'status-instock', outofstock: 'status-outofstock', onbackorder: 'status-draft' };

                let html = tmpl.innerHTML
                    .replace( /\{\{id\}\}/g,            p.id )
                    .replace( /\{\{name\}\}/g,           escHtml( p.name ) )
                    .replace( /\{\{price\}\}/g,          p.price || '—' )
                    .replace( /\{\{type\}\}/g,           p.type )
                    .replace( /\{\{type_label\}\}/g,     typeLabels[ p.type ] || p.type )
                    .replace( /\{\{stock_class\}\}/g,    stockClass[ p.stock_status ] || '' )
                    .replace( /\{\{stock_label\}\}/g,    stockLabels[ p.stock_status ] || p.stock_status )
                    .replace( /\{\{thumbnail\}\}/g,      p.thumbnail || '' )
                    .replace( /\{\{thumb_hidden\}\}/g,   p.thumbnail ? 'display:none' : '' )
                    .replace( /\{\{custom_name\}\}/g,    escHtml( p.custom_name || '' ) )
                    .replace( /\{\{custom_price\}\}/g,   p.custom_price || '' )
                    .replace( /\{\{custom_desc\}\}/g,    escHtml( p.custom_desc || '' ) )
                    .replace( /\{\{hidden_checked\}\}/g, p.hidden ? 'checked' : '' )
                    .replace( /\{\{hidden_class\}\}/g,   p.hidden ? 'is-hidden' : '' )
                    .replace( /\{\{edit_url\}\}/g,       `post.php?post=${p.id}&action=edit` );

                const tr        = document.createElement( 'tr' );
                tr.className    = 'store-api-product-row' + ( p.hidden ? ' is-hidden' : '' );
                tr.dataset.id   = p.id;
                tr.innerHTML    = html.replace( /<tr[^>]*>|<\/tr>/gi, '' );
                tbody.appendChild( tr );
            } );

            bindProductEvents();
        }

        // ── Bind أحداث المنتجات ───────────────────────────────
        function bindProductEvents() {

            // Toggle hidden — يؤثر فوراً على مظهر الصف
            $$( '.product-hidden', tbody ).forEach( cb => {
                cb.addEventListener( 'change', function () {
                    const row = this.closest( 'tr' );
                    row?.classList.toggle( 'is-hidden', this.checked );
                } );
            } );

            // حفظ منتج واحد
            $$( '.btn-save-product', tbody ).forEach( btn => {
                btn.addEventListener( 'click', function () {
                    const pid     = parseInt( this.dataset.id );
                    const row     = this.closest( 'tr' );
                    if ( ! row ) return;

                    const hidden  = $( '.product-hidden',       row )?.checked || false;
                    const name    = $( '.product-custom-name',  row )?.value.trim() || '';
                    const price   = $( '.product-custom-price', row )?.value.trim() || '';
                    const desc    = $( '.product-custom-desc',  row )?.value.trim() || '';

                    setBtnLoading( btn, true );

                    ajax( 'store_api_save_product', {
                        product_id  : pid,
                        hidden      : hidden ? 'true' : 'false',
                        custom_name : name,
                        custom_price: price,
                        custom_desc : desc,
                    }, function ( res ) {
                        setBtnLoading( btn, false );
                        showFeedback( btn, '✅', 'ok' );
                    }, function ( err ) {
                        setBtnLoading( btn, false );
                        showFeedback( btn, '❌', 'err' );
                    } );
                } );
            } );
        }

// ── Drag & Drop للمنتجات ────────────────────────────────────
function initProductDragSort() {
    const tbody = $( '#products-tbody' );
    if ( ! tbody ) return;

    let dragSrc = null;

    $$( '.store-api-product-row', tbody ).forEach( row => {
        // جعل العمود الأول drag handle
        const handle = row.querySelector( 'td:first-child' );
        if ( handle ) {
            handle.style.cursor = 'grab';
            handle.title = 'اسحب لترتيب المنتجات';
        }

        row.setAttribute( 'draggable', 'true' );

        row.addEventListener( 'dragstart', function ( e ) {
            dragSrc = this;
            this.classList.add( 'dragging' );
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData( 'text/html', '' );
        });

        row.addEventListener( 'dragend', function () {
            this.classList.remove( 'dragging' );
            $$( '.drag-over', tbody ).forEach( r => r.classList.remove( 'drag-over' ) );
            syncProductOrderFromDOM();
            toggleSaveOrderBtn();
        });

        row.addEventListener( 'dragover', function ( e ) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            
            if ( this !== dragSrc ) {
                $$( '.drag-over', tbody ).forEach( r => r.classList.remove( 'drag-over' ) );
                this.classList.add( 'drag-over' );
            }
        });

        row.addEventListener( 'drop', function ( e ) {
            e.preventDefault();
            if ( this !== dragSrc && dragSrc ) {
                const rows = $$( '.store-api-product-row', tbody );
                const srcIdx = rows.indexOf( dragSrc );
                const destIdx = rows.indexOf( this );
                
                if ( srcIdx < destIdx ) {
                    tbody.insertBefore( dragSrc, this.nextSibling );
                } else {
                    tbody.insertBefore( dragSrc, this );
                }
            }
        });

        // Touch support للموبايل
        let touchStartY = 0;
        row.addEventListener( 'touchstart', function ( e ) {
            touchStartY = e.touches[0].clientY;
        });

        row.addEventListener( 'touchmove', function ( e ) {
            if ( Math.abs( e.touches[0].clientY - touchStartY ) > 10 ) {
                e.preventDefault();
            }
        });
    });
}

// ── مزامنة ترتيب المنتجات من DOM ───────────────────────────
function syncProductOrderFromDOM() {
    const tbody = $( '#products-tbody' );
    const rows = $$( '.store-api-product-row', tbody );
    
    rows.forEach( ( row, i ) => {
        row.dataset.sortOrder = i;
    });
}

// ── إظهار/إخفاء زر حفظ الترتيب ─────────────────────────────
function toggleSaveOrderBtn() {
    const btn = $( '#btn-save-product-order' );
    if ( ! btn ) return;

    const tbody = $( '#products-tbody' );
    const rows = $$( '.store-api-product-row', tbody );
    
    if ( rows.length > 1 ) {
        btn.style.display = 'inline-block';
    } else {
        btn.style.display = 'none';
    }
}

// ── حفظ ترتيب المنتجات ─────────────────────────────────────
function initSaveProductOrder() {
    const btn = $( '#btn-save-product-order' );
    if ( ! btn ) return;

    btn.addEventListener( 'click', function () {
        const catId = parseInt( $( '#products-cat-select' )?.value || 0 );
        if ( ! catId ) {
            showFeedback( btn, '❌ اختر تصنيف أولاً', 'err' );
            return;
        }

        const tbody = $( '#products-tbody' );
        const order = $$( '.store-api-product-row', tbody ).map( row => 
            parseInt( row.dataset.id )
        );

        setBtnLoading( btn, true );

        ajax( 'store_api_save_product_order', {
            cat_id: catId,
            order: JSON.stringify( order )
        }, function ( res ) {
            setBtnLoading( btn, false );
            showFeedback( btn, '✅ تم حفظ الترتيب', 'ok' );
            btn.style.display = 'none';
        }, function ( err ) {
            setBtnLoading( btn, false );
            showFeedback( btn, `❌ ${err}`, 'err' );
        });
    });
}


        // ── أزرار التحكم ──────────────────────────────────────
        btnLoad && btnLoad.addEventListener( 'click', () => loadProducts( 1 ) );
        btnPrev && btnPrev.addEventListener( 'click', () => loadProducts( state.products.page - 1 ) );
        btnNext && btnNext.addEventListener( 'click', () => loadProducts( state.products.page + 1 ) );

        // تحميل عند تغيير التصنيف مباشرة
        catSelect && catSelect.addEventListener( 'change', function () {
            if ( this.value ) loadProducts( 1 );
        } );
    }

    // ════════════════════════════════════════════════════════════
    // 4. STATUS TAB
    // ════════════════════════════════════════════════════════════
    function initStatus() {
        // نسخ Endpoints
        $$( '.store-api-copy-endpoint' ).forEach( btn => {
            btn.addEventListener( 'click', function () {
                const el = document.getElementById( this.dataset.target );
                if ( el ) copyText( el.innerText.trim(), this );
            } );
        } );
    }

    // ════════════════════════════════════════════════════════════
    // UTILITIES
    // ════════════════════════════════════════════════════════════

    // ── AJAX Helper ───────────────────────────────────────────
    function ajax( action, data, onSuccess, onError ) {
        const body  = new URLSearchParams();
        body.append( 'action', action );
        body.append( 'nonce',  nonce() );
        Object.entries( data ).forEach( ( [ k, v ] ) => body.append( k, v ) );

        fetch( ajaxUrl(), {
            method  : 'POST',
            headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
            body    : body.toString(),
        } )
        .then( r => r.json() )
        .then( res => {
            if ( res.success ) {
                onSuccess( res.data );
            } else {
                const msg = res.data?.message || 'حدث خطأ';
                onError?.( msg );
            }
        } )
        .catch( err => onError?.( err.message || 'Network error' ) );
    }

    // ── Copy to Clipboard ──────────────────────────────────────
    function copyText( text, btn ) {
        if ( ! text ) return;
        if ( navigator.clipboard?.writeText ) {
            navigator.clipboard.writeText( text )
                .then( () => showFeedback( btn, strings().copied, 'ok' ) )
                .catch( () => fallbackCopy( text, btn ) );
        } else {
            fallbackCopy( text, btn );
        }
    }

    function fallbackCopy( text, btn ) {
        const ta        = document.createElement( 'textarea' );
        ta.value        = text;
        ta.style.cssText= 'position:fixed;opacity:0;top:0;right:0';
        document.body.appendChild( ta );
        ta.focus(); ta.select();
        try {
            document.execCommand( 'copy' );
            showFeedback( btn, strings().copied, 'ok' );
        } catch {
            showFeedback( btn, strings().copy_failed, 'err' );
        }
        document.body.removeChild( ta );
    }

    // ── Feedback ───────────────────────────────────────────────
    function showFeedback( btn, message, type = 'ok' ) {
        btn.parentNode?.querySelectorAll( '.store-api-copy-feedback, .store-api-save-feedback-ok, .store-api-save-feedback-err' )
            .forEach( el => el.remove() );

        const span     = document.createElement( 'span' );
        span.textContent = message;
        span.className = type === 'ok'
            ? 'store-api-copy-feedback store-api-save-feedback-ok'
            : 'store-api-copy-feedback store-api-save-feedback-err';

        btn.parentNode?.insertBefore( span, btn.nextSibling );
        setTimeout( () => span.remove(), 2200 );
    }

    // ── Button Loading ─────────────────────────────────────────
    function setBtnLoading( btn, loading ) {
        btn.disabled = loading;
        const existing = btn.querySelector( '.store-api-btn-spinner' );
        if ( loading ) {
            if ( ! existing ) {
                const s = document.createElement( 'span' );
                s.className = 'store-api-btn-spinner';
                btn.appendChild( s );
            }
        } else {
            existing?.remove();
        }
    }

    // ── Escape HTML ────────────────────────────────────────────
    function escHtml( str ) {
        return String( str )
            .replace( /&/g,  '&amp;' )
            .replace( /</g,  '&lt;' )
            .replace( />/g,  '&gt;' )
            .replace( /"/g,  '&quot;' )
            .replace( /'/g,  '&#039;' );
    }

} )();
