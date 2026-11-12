<?php
if (!defined('ABSPATH')) exit;

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array('search-form', 'comment-form', 'gallery', 'caption', 'style', 'script'));
    add_theme_support('woocommerce');
});

add_filter('woocommerce_enqueue_styles', '__return_empty_array');

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('dm-fonts', 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Onest:wght@400;500;600&display=swap', array(), null);
    $v = wp_get_theme()->get('Version') . '.' . filemtime(get_theme_file_path('woo.css'));
    wp_enqueue_style('dm-style', get_stylesheet_uri(), array(), $v);
    wp_enqueue_style('dm-woo', get_theme_file_uri('woo.css'), array('dm-style'), $v);
});

add_filter('body_class', function ($c) {
    if (!is_front_page()) $c[] = 'inner';
    return $c;
});

function dm_shop_url() {
    $id = function_exists('wc_get_page_id') ? (int) wc_get_page_id('shop') : 0;
    if ($id > 0 && $id !== (int) get_option('page_on_front')) return get_permalink($id);
    $p = get_page_by_path('shop');
    return $p ? get_permalink($p) : home_url('/shop/');
}

function dm_account_url() {
    $id = function_exists('wc_get_page_id') ? (int) wc_get_page_id('myaccount') : 0;
    if ($id > 0 && $id !== (int) get_option('page_on_front') && get_post_status($id) === 'publish') return get_permalink($id);
    return home_url('/my-account/');
}

function dm_img($k) {
    $p = get_theme_file_path('img/' . $k . '.jpg');
    return get_theme_file_uri('img/' . $k . '.jpg') . (file_exists($p) ? '?v=' . filemtime($p) : '');
}

function dm_cat_url($slug) {
    $t = get_term_by('slug', $slug, 'product_cat');
    $u = $t ? get_term_link($t) : '';
    return is_wp_error($u) || !$u ? dm_shop_url() : $u;
}

function dm_url($go, $cf = '', $sc = '') {
    $map = array('home' => '/', 'documents' => '/dokumenty/', 'reviews' => '/otzyvy/', 'payment' => '/oplata/',
                 'delivery' => '/dostavka/', 'about' => '/o-nas/', 'contacts' => '/kontakty/', 'privacy' => '/politika/', 'offer' => '/oferta/');
    $cats = array('med' => 'k-med', 'tea' => 'k-tea', 'perga' => 'k-perga', 'gift' => 'k-gift');
    if ($go === 'catalog') return $cf && isset($cats[$cf]) ? dm_cat_url($cats[$cf]) : dm_shop_url();
    if ($go === 'cart') return wc_get_cart_url();
    if ($go === 'checkout') return wc_get_checkout_url();
    $u = home_url(isset($map[$go]) ? $map[$go] : '/');
    if ($go === 'home' && $sc) $u .= '#' . $sc;
    return $u;
}

function dm_chips() {
    $items = array('' => 'Все', 'k-med' => 'Мёд', 'k-tea' => 'Травы и чаи', 'k-perga' => 'Пыльца и пчелопродукты', 'k-gift' => 'Горные дары Алтая');
    $cur = is_product_category() ? get_queried_object()->slug : '';
    echo '<div class="chips">';
    foreach ($items as $slug => $name) {
        $u = $slug ? dm_cat_url($slug) : dm_shop_url();
        echo '<a class="dm-chip' . ($slug === $cur ? ' on' : '') . '" href="' . esc_url($u) . '">' . esc_html($name) . '</a>';
    }
    echo '</div>';
}

// Каталог: 12 карточек на страницу, чтобы всё помещалось, сетка по 4
add_filter('loop_shop_per_page', function () { return 40; });
add_filter('loop_shop_columns', function () { return 4; });
add_filter('woocommerce_show_page_title', '__return_false');
add_filter('woocommerce_breadcrumb_defaults', function ($a) { $a['wrap_before'] = '<nav class="dm-bc">'; $a['wrap_after'] = '</nav>'; return $a; });
remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);
remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);
remove_action('woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30);
remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10);
remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50);

// Корзина: блок «Как мы считаем цену»
add_action('woocommerce_after_cart', function () { get_template_part('price-note'); });

// Админ-панель сверху на тестовой версии не нужна покупателям, но владельцу удобна: оставляем


/* ---------- Поиск по каталогу ---------- */

function dm_search_form($cls = 'dm-search') {
    $q = get_search_query();
    return '<form role="search" method="get" class="' . esc_attr($cls) . '" action="' . esc_url(dm_shop_url()) . '">'
        . '<input type="search" name="s" value="' . esc_attr($q) . '" placeholder="Найти товар: мёд, чай, корень…" aria-label="Поиск по каталогу">'
        . '<input type="hidden" name="post_type" value="product">'
        . '<button type="submit">Найти</button></form>';
}

// Ищем без различия «е» и «ё» и по основе слова (мед = мёд, травы = травяной)
add_filter('posts_search', function ($search, $q) {
    global $wpdb;
    if (is_admin() || !$q->is_main_query() || !$q->is_search() || $q->get('post_type') !== 'product') return $search;
    $raw = trim((string) $q->get('s'));
    if ($raw === '') return $search;
    $words = preg_split('/\s+/u', mb_strtolower($raw), -1, PREG_SPLIT_NO_EMPTY);
    // нормализация: ё=е, й=и (чай/чаи), чтобы находились и «чай», и раздел «Чаи»
    $norm = function ($col) { return "REPLACE(REPLACE(LOWER($col),'ё','е'),'й','и')"; };
    $strict = array();
    $loose  = array();
    foreach ($words as $w) {
        $w = str_replace(array('ё', 'й'), array('е', 'и'), $w);
        $n = mb_strlen($w);
        if ($n > 5) $w = mb_substr($w, 0, $n - 2); elseif ($n > 3) $w = mb_substr($w, 0, $n - 1);
        $like = '%' . $wpdb->esc_like($w) . '%';
        $in_title = $wpdb->prepare($norm("{$wpdb->posts}.post_title") . ' LIKE %s', $like);
        $in_terms = $wpdb->prepare(
            "EXISTS (SELECT 1 FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy IN ('product_cat','product_tag')
                JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
                WHERE tr.object_id = {$wpdb->posts}.ID AND " . $norm('t.name') . " LIKE %s)", $like);
        $in_text = $wpdb->prepare($norm("{$wpdb->posts}.post_excerpt") . ' LIKE %s OR ' . $norm("{$wpdb->posts}.post_content") . ' LIKE %s', $like, $like);
        $strict[] = "($in_title OR $in_terms)";
        $loose[]  = "($in_title OR $in_terms OR $in_text)";
    }
    if (!$strict) return $search;
    $strict_sql = ' AND (' . implode(' AND ', $strict) . ') ';
    // Сначала строго (название и раздел). Если пусто, ищем и по описанию.
    $cnt = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' $strict_sql");
    if ($cnt > 0) return $strict_sql;
    return ' AND (' . implode(' AND ', $loose) . ') ';
}, 20, 2);

/* Значок вкладки (фавикон): домик «Дом мёда». Если в настройках сайта задан свой значок, он главнее. */
function dm_favicon_links() {
    if (function_exists('has_site_icon') && has_site_icon()) return;
    $d = get_template_directory();
    $u = get_template_directory_uri() . '/img/';
    $v = function ($f) use ($d) { $p = $d . '/img/' . $f; return file_exists($p) ? '?v=' . filemtime($p) : ''; };
    echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url($u . 'favicon-32.png' . $v('favicon-32.png')) . '">' . "\n";
    echo '<link rel="icon" type="image/png" sizes="192x192" href="' . esc_url($u . 'favicon-192.png' . $v('favicon-192.png')) . '">' . "\n";
    echo '<link rel="apple-touch-icon" href="' . esc_url($u . 'apple-touch-icon.png' . $v('apple-touch-icon.png')) . '">' . "\n";
}
add_action('wp_head', 'dm_favicon_links', 2);
add_action('admin_head', 'dm_favicon_links', 2);
add_action('login_head', 'dm_favicon_links', 2);

/* Яндекс.Метрика и подтверждение Вебмастера. Счётчик грузится только после согласия на cookie. */
define('DM_METRIKA_ID', 114207819);
define('DM_WEBMASTER_META', 'b5db076080af54d9');
add_action('wp_head', function () {
    if (DM_WEBMASTER_META !== '') echo '<meta name="yandex-verification" content="' . esc_attr(DM_WEBMASTER_META) . '">' . "\n";
    $id = (int) DM_METRIKA_ID;
    if ($id <= 0 || current_user_can('edit_posts')) return; // не считаем визиты администраторов
    echo "<script>window.dmLoadMetrika=function(){if(window.__dmym)return;window.__dmym=1;(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window,document,'script','https://mc.yandex.ru/metrika/tag.js','ym');ym($id,'init',{clickmap:true,trackLinks:true,accurateTrackBounce:true,webvisor:false,ecommerce:'dataLayer'});};if(/(?:^|; )dm_cc=1/.test(document.cookie))window.dmLoadMetrika();</script>\n";
}, 5);

/* Баннер cookie */
add_action('wp_footer', function () {
    $pol = esc_url(home_url('/politika/'));
    echo '<style>#dm-cc{position:fixed;left:16px;right:16px;bottom:16px;z-index:99990;max-width:760px;margin:0 auto;background:#14291D;color:#f6efe0;border:1px solid rgba(243,185,62,.55);border-radius:16px;padding:16px 18px;box-shadow:0 14px 34px rgba(0,0,0,.35);display:none;font-size:14px;line-height:1.5}#dm-cc a{color:#f3b93e}#dm-cc .b{display:flex;gap:10px;margin-top:12px;flex-wrap:wrap}#dm-cc button{font:inherit;font-weight:600;border-radius:999px;padding:10px 22px;cursor:pointer;border:1.5px solid #f3b93e;min-height:44px}#dm-cc .y{background:#f3b93e;color:#1d1a12}#dm-cc .n{background:transparent;color:#f6efe0}@media(max-width:520px){#dm-cc{left:10px;right:10px;bottom:10px}#dm-cc button{flex:1}}</style>';
    echo '<div id="dm-cc" role="dialog" aria-label="Использование cookie"><div>Мы используем файлы cookie и сервис Яндекс.Метрика, чтобы сайт работал удобнее и мы понимали, чем он полезен. Необходимые cookie (корзина, вход) работают всегда. Подробнее в <a href="' . $pol . '">политике конфиденциальности</a>.</div><div class="b"><button type="button" class="y" id="dm-cc-y">Принять</button><button type="button" class="n" id="dm-cc-n">Отказаться</button></div></div>';
    echo '<script>(function(){var b=document.getElementById("dm-cc");if(!b)return;if(/(?:^|; )dm_cc=/.test(document.cookie))return;b.style.display="block";function s(v){document.cookie="dm_cc="+v+"; max-age=31536000; path=/; SameSite=Lax";b.style.display="none";if(v===1&&window.dmLoadMetrika)window.dmLoadMetrika()}document.getElementById("dm-cc-y").onclick=function(){s(1)};document.getElementById("dm-cc-n").onclick=function(){s(0)}})();</script>';
}, 50);

/* SEO: заголовок главной, описание, карточка для соцсетей, данные о магазине */
add_filter('pre_get_document_title', function ($t) {
    if (is_front_page()) return 'Дом мёда — алтайский мёд с доставкой по России';
    return $t;
});

function dm_seo_desc() {
    if (is_front_page()) return 'Натуральный мёд с Алтая от пчеловодов из Усть-Коксы: мёд в банках и в сотах, чай, перга, прополис. Доставка по всей России, самовывоз.';
    if (function_exists('is_product_category') && is_product_category()) {
        $d = trim(wp_strip_all_tags(term_description()));
        return $d !== '' ? wp_trim_words($d, 28, '…') : 'Каталог «Дом мёда»: ' . single_term_title('', false) . '. Алтайский мёд и продукты пчеловодства с доставкой по России.';
    }
    if (is_singular()) {
        $p = get_post();
        $x = has_excerpt($p) ? get_the_excerpt($p) : $p->post_content;
        $x = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags(strip_shortcodes($x))));
        if ($x !== '') return mb_strimwidth($x, 0, 158, '…', 'UTF-8');
    }
    return 'Дом мёда: алтайский мёд и продукты пчеловодства от пчеловодов из Усть-Коксы с доставкой по России.';
}

add_action('wp_head', function () {
    if (is_admin() || is_404() || is_search()) return;
    $desc = dm_seo_desc();
    $title = wp_get_document_title();
    $url = is_singular() ? get_permalink() : home_url(add_query_arg(array()));
    if (is_front_page()) $url = home_url('/');
    $img = '';
    if (is_singular() && has_post_thumbnail()) { $i = wp_get_attachment_image_src(get_post_thumbnail_id(), 'large'); if ($i) $img = $i[0]; }
    if (!$img) { $f = get_theme_file_path('img/about-paseka.jpg'); $img = file_exists($f) ? get_theme_file_uri('img/about-paseka.jpg') : get_theme_file_uri('img/logo-sm.png'); }
    $type = (function_exists('is_product') && is_product()) ? 'product' : 'website';
    echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
    echo '<meta property="og:locale" content="ru_RU">' . "\n";
    echo '<meta property="og:type" content="' . $type . '">' . "\n";
    echo '<meta property="og:site_name" content="Дом мёда">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
    echo '<meta property="og:image" content="' . esc_url($img) . '">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    if (is_front_page()) {
        $ld = array(
            '@context' => 'https://schema.org',
            '@type' => 'Store',
            'name' => 'Дом мёда',
            'legalName' => 'ИП Суртаев В.М.',
            'url' => home_url('/'),
            'logo' => get_theme_file_uri('img/logo-sm.png'),
            'image' => $img,
            'description' => $desc,
            'telephone' => array('+7 913 995-97-80', '+7 913 698-09-63'),
            'address' => array('@type' => 'PostalAddress', 'streetAddress' => 'ул. Мичурина, 1В', 'addressLocality' => 'Усть-Кокса', 'addressRegion' => 'Республика Алтай', 'addressCountry' => 'RU'),
            'areaServed' => 'RU',
        );
        echo '<script type="application/ld+json">' . wp_json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
}, 3);
