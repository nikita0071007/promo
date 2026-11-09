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
    return get_theme_file_uri('img/' . $k . '.jpg');
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
    $parts = array();
    foreach ($words as $w) {
        $w = str_replace('ё', 'е', $w);
        $n = mb_strlen($w);
        if ($n > 5) $w = mb_substr($w, 0, $n - 2); elseif ($n > 3) $w = mb_substr($w, 0, $n - 1);
        $like = '%' . $wpdb->esc_like($w) . '%';
        $parts[] = $wpdb->prepare("(REPLACE(LOWER({$wpdb->posts}.post_title),'ё','е') LIKE %s OR REPLACE(LOWER({$wpdb->posts}.post_excerpt),'ё','е') LIKE %s OR REPLACE(LOWER({$wpdb->posts}.post_content),'ё','е') LIKE %s)", $like, $like, $like);
    }
    return $parts ? ' AND (' . implode(' AND ', $parts) . ') ' : $search;
}, 20, 2);
