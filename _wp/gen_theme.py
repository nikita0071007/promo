#!/usr/bin/env python3
"""Собирает тему WordPress «Дом мёда» из макета tools/gen_photo.py.
Запуск: python3 gen_theme.py <путь к gen_photo.py> <куда класть тему> <папка с картинками (/tmp/zz/c)> <папка docs>"""
import re, sys, shutil, pathlib

SRC, OUT, IMG, DOCS = (pathlib.Path(a) for a in sys.argv[1:5])
text = SRC.read_text(encoding="utf8")
html = text[text.index("html='''") + 8: text.index("\n'''\n")]

css = html[html.index("<style>") + 7: html.index("</style>")]
css = css.replace("url(@@hero@@)", "url(img/hero.jpg)")
# модальное окно входа из макета не нужно
css = re.sub(r"\.mdl\{[^\n]*\n", "", css, count=1)

def section(sid):
    m = re.search(r'<section id="%s".*?</section>' % sid, html, re.S)
    if not m:
        raise SystemExit("нет секции " + sid)
    return m.group(0)

def conv(s):
    """Ссылки макета (#якорь + data-go) в настоящие адреса, картинки в файлы темы."""
    def link(m):
        attrs = dict(re.findall(r'data-([a-z]+)="([^"]*)"', m.group(1)))
        go = attrs.get("go", "home")
        return 'href="<?php echo esc_url(dm_url(\'%s\',\'%s\',\'%s\')); ?>"' % (go, attrs.get("cf", ""), attrs.get("sc", ""))
    s = re.sub(r'href="#[^"]*"((?:\s+data-[a-z]+="[^"]*")*)', link, s)
    s = re.sub(r'\sdata-v="[^"]*"', "", s)
    s = re.sub(r'@@([a-z0-9_]+)@@', lambda m: "<?php echo esc_url(dm_img('%s')); ?>" % m.group(1), s)
    s = re.sub(r'href="\.\./docs/([^"]+)"', lambda m: 'href="<?php echo esc_url(get_theme_file_uri(\'docs/%s\')); ?>"' % m.group(1), s)
    return s

def w(name, body):
    (OUT / name).write_text(body, encoding="utf8")

if OUT.exists():
    shutil.rmtree(OUT)
(OUT / "img").mkdir(parents=True)
(OUT / "docs").mkdir()
for k in ["hero", "c1", "c2", "c3", "g_dary", "api2", "shop2", "doc1", "doc2", "doc3"]:
    shutil.copy(IMG / (k + ".jpg"), OUT / "img" / (k + ".jpg"))
for f in DOCS.glob("*.pdf"):
    shutil.copy(f, OUT / "docs" / f.name)

# ---------- шапка и подвал ----------
head = html[html.index('<header class="top">'): html.index("</header>") + 9]
head = conv(head)
head = head.replace('href="<?php echo esc_url(dm_url(\'home\',\'\',\'\')); ?>" id="acc"', 'href="<?php echo esc_url(dm_account_url()); ?>"')
head = head.replace('<b id="cn">0</b>', '<b id="cn"><?php echo function_exists(\'WC\') && WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0; ?></b>')
head = head.replace('<a class="logo" href="<?php echo esc_url(dm_url(\'home\',\'\',\'\')); ?>" data-go="home">', '<a class="logo" href="<?php echo esc_url(home_url(\'/\')); ?>">')

w("header.php", """<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
""" + head + "\n<main>\n")

foot = html[html.index("<footer>"): html.index("</footer>") + 9]
foot = conv(foot).replace("Демо-макет, фото и тексты примерные.", "Тестовая версия сайта.")
w("footer.php", """</main>
""" + foot + """
<div id="toast" role="status" aria-live="polite"></div><a href="#" id="up" aria-label="Наверх">↑</a>
<script>
(function(){var b=document.querySelector(".burger"),m=document.getElementById("menu"),u=document.getElementById("up");
if(b&&m)b.addEventListener("click",function(){var o=m.classList.toggle("open");b.setAttribute("aria-expanded",o?"true":"false")});
if(u){window.addEventListener("scroll",function(){u.classList.toggle("on",window.scrollY>600)});u.addEventListener("click",function(e){e.preventDefault();window.scrollTo({top:0,behavior:"smooth"})})}})();
</script>
<?php wp_footer(); ?>
</body>
</html>
""")

# ---------- страницы ----------
hero = html[html.index('<section class="hero"'): html.index("</section>", html.index('<section class="hero"')) + 10]
home = [hero] + [section(i) for i in ["cat", "how", "api", "contact", "shop", "delivery"]]
w("front-page.php", "<?php get_header(); ?>\n" + conv("\n".join(home)) + "\n<?php get_footer(); ?>\n")

def page(slug, sids):
    w("page-%s.php" % slug, "<?php get_header(); ?>\n" + conv("\n".join(section(i) for i in sids)) + "\n<?php get_footer(); ?>\n")

page("oplata", ["payment"])
page("dostavka", ["delivery2"])
page("dokumenty", ["documents"])
page("otzyvy", ["reviews"])
page("oferta", ["offer"])
page("politika", ["privacy"])
import os as _os
for _slug in ("oferta", "politika"):
    _f = _os.path.join(_os.path.dirname(_os.path.abspath(__file__)), "legal", _slug + ".html")
    if _os.path.exists(_f):
        w("page-%s.php" % _slug, "<?php get_header(); ?>\n" + open(_f, encoding="utf-8").read() + "\n<?php get_footer(); ?>\n")
page("kontakty", ["contact", "shop", "delivery"])

# Блок «Как мы считаем цену» для карточки товара и корзины
price = section("price")
w("price-note.php", conv(price))

# ---------- общие шаблоны ----------
w("page.php", """<?php get_header(); ?>
<section class="inner1"><div class="wrap">
<div class="sh"><h2><?php the_title(); ?></h2></div>
<?php while (have_posts()) : the_post(); ?><div class="dm-content"><?php the_content(); ?></div><?php endwhile; ?>
</div></section>
<?php get_footer(); ?>
""")
w("index.php", """<?php get_header(); ?>
<section class="inner1"><div class="wrap">
<div class="sh"><h2><?php echo is_home() ? 'Новости' : esc_html(get_the_archive_title()); ?></h2></div>
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
<article class="dm-post"><h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3><div><?php the_excerpt(); ?></div></article>
<?php endwhile; the_posts_pagination(); endif; ?>
</div></section>
<?php get_footer(); ?>
""")
w("single.php", """<?php get_header(); ?>
<section class="inner1"><div class="wrap doc">
<?php while (have_posts()) : the_post(); ?><h2><?php the_title(); ?></h2><div class="dm-content"><?php the_content(); ?></div><?php endwhile; ?>
</div></section>
<?php get_footer(); ?>
""")
w("woocommerce.php", """<?php get_header(); ?>
<section class="inner1"><div class="wrap">
<?php if (is_singular('product')) : ?>
<a class="back" href="<?php echo esc_url(dm_shop_url()); ?>">← Назад в каталог</a>
<?php else : ?>
<div class="sh"><h2><?php echo is_product_category() ? esc_html(single_term_title('', false)) : 'Каталог'; ?></h2></div>
<?php dm_chips(); ?>
<?php endif; ?>
<?php woocommerce_content(); ?>
</div></section>
<?php if (is_singular('product')) { get_template_part('price-note'); } ?>
<?php get_footer(); ?>
""")

# ---------- functions.php ----------
w("functions.php", r"""<?php
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
                 'delivery' => '/dostavka/', 'contacts' => '/kontakty/', 'privacy' => '/politika/', 'offer' => '/oferta/');
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
""")

# ---------- style.css ----------
header = """/*
Theme Name: Дом мёда
Theme URI: https://dom-meda.com
Author: GusevMade
Description: Лёгкая тема магазина «Дом мёда» в стиле фото-макета. Под WooCommerce.
Version: 0.1.0
Text Domain: dom-meda
*/
"""
w("style.css", header + css)

woo = r"""
/* ---------- WooCommerce в стиле макета ---------- */
.woocommerce-notices-wrapper{margin:0 0 18px}
.woocommerce-message,.woocommerce-info,.woocommerce-error{list-style:none;margin:0 0 18px;padding:14px 18px;border-radius:12px;background:var(--paper);border:1px solid var(--line);display:flex;gap:14px;align-items:center;flex-wrap:wrap}
.woocommerce-error{background:#fbe9e5;color:#8a2c1a;border-color:#efc7bf}
.woocommerce-message .button,.woocommerce-info .button{margin-left:auto}
.dm-chip{display:inline-flex;align-items:center;min-height:44px;font-size:15px;border:1.5px solid var(--line);background:var(--paper);border-radius:99px;padding:9px 18px;color:var(--ink)}
.dm-chip.on{background:var(--forest);color:#fff;border-color:var(--forest)}
.woocommerce a.button,.woocommerce button.button,.woocommerce input.button,.woocommerce #respond input#submit,.woocommerce .button,button.single_add_to_cart_button,#place_order{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:46px;background:var(--gold);color:#1a2a1f;font:600 15.5px Onest,sans-serif;padding:12px 24px;border:0;border-radius:99px;cursor:pointer;box-shadow:0 8px 22px rgba(190,130,10,.22);transition:transform .15s,filter .15s}
.woocommerce a.button:hover,.woocommerce button.button:hover,#place_order:hover{transform:translateY(-2px)}
.woocommerce a.button:active,.woocommerce button.button:active,#place_order:active{transform:scale(.96);filter:brightness(.93)}
#place_order{width:100%}
.woocommerce a.button.alt,.woocommerce button.button.alt{background:var(--gold)}
/* каталог */
.woocommerce ul.products{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;list-style:none;padding:0;margin:0}
.woocommerce ul.products li.product{background:var(--paper);border:1px solid var(--line);border-radius:18px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 8px 22px rgba(60,50,20,.07);margin:0;padding:0;float:none;width:auto}
.woocommerce ul.products li.product a.woocommerce-LoopProduct-link{display:flex;flex-direction:column;flex:1}
.woocommerce ul.products li.product img{display:block;width:100%;aspect-ratio:4/3;object-fit:cover;margin:0}
.woocommerce ul.products li.product .woocommerce-loop-product__title{font:700 19px "Playfair Display",serif;color:var(--forest);padding:14px 16px 4px;margin:0}
.woocommerce ul.products li.product .price{display:block;font:600 16px Onest,sans-serif;color:var(--ink);padding:0 16px 8px;margin:0}
.woocommerce ul.products li.product .button{margin:auto 16px 16px;align-self:stretch}
.woocommerce nav.woocommerce-pagination{text-align:center;margin:26px 0}
.woocommerce nav.woocommerce-pagination ul{display:inline-flex;gap:8px;list-style:none;padding:0;border:0}
.woocommerce nav.woocommerce-pagination li{border:0}.woocommerce nav.woocommerce-pagination a,.woocommerce nav.woocommerce-pagination span{display:grid;place-items:center;min-width:44px;height:44px;border-radius:50%;border:1px solid var(--line);background:var(--paper)}
.woocommerce nav.woocommerce-pagination span.current{background:var(--forest);color:#fff}
/* карточка товара */
.woocommerce div.product{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:start}
.woocommerce div.product div.images{margin:0;float:none;width:auto;border-radius:22px;overflow:hidden;box-shadow:0 14px 34px rgba(60,50,20,.14)}
.woocommerce div.product div.images img{display:block;width:100%;aspect-ratio:4/3;object-fit:cover}
.woocommerce div.product div.summary{float:none;width:auto;margin:0}
.woocommerce div.product .product_title{font:700 clamp(28px,3.4vw,38px) "Playfair Display",serif;color:var(--forest);margin:0 0 6px}
.woocommerce div.product p.price,.woocommerce div.product span.price{font:700 26px "Playfair Display",serif;color:var(--forest);display:block;margin:10px 0 16px}
.woocommerce div.product .woocommerce-product-details__short-description{color:var(--muted);margin:0 0 16px}
.woocommerce div.product .woocommerce-tabs{grid-column:1/-1;margin-top:10px}
.woocommerce div.product .woocommerce-tabs ul.tabs{display:flex;gap:8px;list-style:none;padding:0;margin:0 0 16px;flex-wrap:wrap}
.woocommerce div.product .woocommerce-tabs ul.tabs li{border:1.5px solid var(--line);border-radius:99px;background:var(--paper)}
.woocommerce div.product .woocommerce-tabs ul.tabs li a{display:block;padding:9px 18px}.woocommerce div.product .woocommerce-tabs ul.tabs li.active{background:var(--forest)}.woocommerce div.product .woocommerce-tabs ul.tabs li.active a{color:#fff}
.woocommerce div.product .related,.woocommerce div.product .upsells{grid-column:1/-1}
.woocommerce div.product .related>h2,.woocommerce div.product .upsells>h2{font-size:28px;margin:30px 0 16px}
.woocommerce .quantity input.qty{width:80px;min-height:46px;border:1.5px solid var(--line);border-radius:12px;text-align:center;font:inherit;background:#fff}
form.cart{margin:0}form.cart .quantity{display:inline-block;margin-right:10px}
/* формы */
.woocommerce form .form-row{margin:0 0 16px;padding:0;display:grid;gap:6px}
.woocommerce form .form-row label{font-weight:600;font-size:15.5px}
.woocommerce form .form-row input.input-text,.woocommerce form .form-row textarea,.woocommerce form .form-row select,.woocommerce-cart table.cart td.actions .coupon .input-text{font:inherit;font-weight:400;font-size:16px;padding:13px 14px;border:1.5px solid var(--line);border-radius:12px;background:#fff;color:var(--ink);width:100%;min-height:48px}
.woocommerce form .form-row input:focus,.woocommerce form .form-row textarea:focus{outline:3px solid #f3c04f;outline-offset:1px}
.woocommerce form .required{color:#b3361f;text-decoration:none}
/* таблицы корзины и заказа */
.woocommerce table.shop_table{width:100%;border-collapse:collapse;background:var(--paper);border:1px solid var(--line);border-radius:18px;overflow:hidden;margin:0 0 22px}
.woocommerce table.shop_table th,.woocommerce table.shop_table td{padding:14px 16px;border-bottom:1px solid var(--line);text-align:left;vertical-align:middle}
.woocommerce table.shop_table th{font-weight:600;color:var(--forest)}
.woocommerce table.shop_table td.product-thumbnail img{width:72px;height:72px;object-fit:cover;border-radius:10px;display:block}
.woocommerce table.shop_table td.product-remove a{color:#8a3b2a;font-size:22px;text-decoration:none}
.woocommerce table.shop_table dl.variation{margin:4px 0 0;font-size:14px;color:var(--muted)}.woocommerce table.shop_table dl.variation dt,.woocommerce table.shop_table dl.variation dd{display:inline;margin:0}.woocommerce table.shop_table dl.variation p{display:inline;margin:0}
.woocommerce table.cart td.actions{text-align:right}
.woocommerce .cart-collaterals{display:flex;justify-content:flex-end}.woocommerce .cart_totals{width:min(440px,100%)}
.woocommerce .cart_totals h2,.woocommerce #order_review_heading,.woocommerce-checkout h3{font-size:24px;margin:0 0 14px}
.wc-proceed-to-checkout a.checkout-button{width:100%;font-size:17px}
/* оформление заказа */
.woocommerce form.checkout{display:grid;grid-template-columns:1.3fr 1fr;gap:34px;background:var(--paper);border:1px solid var(--line);border-radius:22px;padding:30px;align-items:start}
.woocommerce form.checkout #customer_details{grid-column:1;grid-row:1/span 2}
.woocommerce form.checkout #order_review_heading{grid-column:2;margin:0}
.woocommerce form.checkout #order_review{grid-column:2;background:#fff;border:1px solid var(--line);border-radius:18px;padding:20px}
.woocommerce form.checkout .col-1,.woocommerce form.checkout .col-2{float:none;width:auto}
.woocommerce-checkout #payment{background:none}.woocommerce-checkout #payment ul.payment_methods{list-style:none;padding:0;margin:0 0 14px}
.woocommerce-checkout #payment div.payment_box{background:#F6EAC9;border-radius:12px;padding:12px 16px;margin:8px 0 0;font-size:15px}
.woocommerce-checkout #payment .place-order{padding:0}
.doc ul{margin:0 0 14px;padding-left:22px;list-style:disc}.doc li{margin:0 0 6px;line-height:1.55}
/* Личный кабинет */
.dm-acc-hello{background:linear-gradient(135deg,#fff7e0,#ffe9b0);border:1px solid var(--line);border-radius:18px;padding:22px 26px;margin-bottom:18px}
.dm-acc-hello h3{margin:0 0 6px;font-size:24px}.dm-acc-hello p{margin:0;color:var(--muted)}
.dm-acc-card{background:var(--paper);border:1px solid var(--line);border-radius:18px;padding:20px 24px;margin-bottom:18px}
.dm-acc-top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.dm-st{padding:4px 12px;border-radius:999px;background:#eee;font-size:13px;font-weight:600}
.dm-st-on-hold{background:#fff1c9}.dm-st-dm-await-pay{background:#ffe0b3}.dm-st-processing{background:#d8f0d4}.dm-st-completed{background:#cfe8f5}
.dm-steps{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
.dm-step{display:flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;border:1px solid var(--line);font-size:14px;color:var(--muted)}
.dm-step i{font-style:normal;width:22px;height:22px;border-radius:50%;background:var(--line);display:grid;place-items:center;font-size:12px}
.dm-step.done{color:var(--forest)}.dm-step.done i{background:#bfe0b8}
.dm-step.now{background:var(--forest);color:#fff;border-color:var(--forest)}.dm-step.now i{background:#fff;color:var(--forest)}
.dm-acc-sum span{color:var(--muted);font-size:14px}
.woocommerce-MyAccount-navigation a{transition:background .15s,border-color .15s}.woocommerce-MyAccount-navigation li:not(.is-active) a:hover{border-color:var(--forest)}
.dm-login-note{color:var(--muted);font-size:14px;margin:0 0 12px}
.dm-social{margin-top:18px;padding-top:16px;border-top:1px solid var(--line);display:flex;flex-wrap:wrap;gap:10px;align-items:center}.dm-social span{width:100%;color:var(--muted);font-size:14px}
.woocommerce-orders-table .button.dm_pay{background:var(--honey,#e8a317);color:#222;border-radius:10px;font-weight:700}
.woocommerce-privacy-policy-text{font-size:14px;color:var(--muted);margin:0 0 12px}
.woocommerce-order{max-width:640px;margin:0 auto;background:var(--paper);border:1px solid var(--line);border-radius:22px;padding:30px}
.woocommerce-order ul.order_details{list-style:none;padding:0;display:grid;gap:8px;margin:0 0 18px}
.woocommerce-thankyou-order-received{font:700 24px "Playfair Display",serif;color:var(--forest)}
/* личный кабинет */
.woocommerce-account .woocommerce{display:grid;grid-template-columns:240px 1fr;gap:30px}
.woocommerce-MyAccount-navigation ul{list-style:none;padding:0;margin:0;display:grid;gap:8px}
.woocommerce-MyAccount-navigation a{display:block;padding:12px 16px;border:1px solid var(--line);border-radius:12px;background:var(--paper)}
.woocommerce-MyAccount-navigation .is-active a{background:var(--forest);color:#fff}
.woocommerce-account .u-columns{display:grid;grid-template-columns:1fr 1fr;gap:24px}.woocommerce form.login,.woocommerce form.register{border:1px solid var(--line);border-radius:18px;padding:22px;background:var(--paper)}
.dm-content{max-width:900px;margin:0 auto}.dm-content.wide,.woocommerce-cart .dm-content,.woocommerce-checkout .dm-content,.woocommerce-account .dm-content{max-width:none}
.dm-post{margin:0 0 24px}.dm-bc{font-size:14px;color:var(--muted);margin:0 0 14px}

/* классы WC cart совпали с макетом: сбрасываем */
.woocommerce form.cart{display:block;background:none;border:0;padding:0;border-radius:0;gap:0;grid-template-columns:none}
.woocommerce table.cart{display:table;background:none;border:0;padding:0;border-radius:0;gap:0;grid-template-columns:none}
.woocommerce table.cart .actions .coupon{display:flex;gap:10px;float:left}
/* звёзды рейтинга без шрифта WC */
.star-rating{position:relative;display:inline-block;height:1.3em;width:5.9em;overflow:hidden;line-height:1.3;letter-spacing:.1em;vertical-align:middle}
.star-rating::before{content:"★★★★★";color:#d9cfc0;position:absolute;left:0;top:0}
.star-rating span{position:absolute;left:0;top:0;overflow:hidden;padding-top:2em;height:0}
.star-rating span::before{content:"★★★★★";color:#E8A21F;position:absolute;left:0;top:0}
.woocommerce-product-rating{display:flex;gap:10px;align-items:center;margin:0 0 6px;color:var(--muted);font-size:14.5px}
p.stars a{color:#E8A21F}

/* шапка под панелью администратора WordPress (когда владелец вошёл в сайт) */
.admin-bar .top{top:var(--wp-admin--admin-bar--height,32px)}
.admin-bar #up{bottom:84px}
.screen-reader-text{position:absolute;left:-9999px}
@media (max-width:900px){.woocommerce ul.products{grid-template-columns:1fr 1fr}.woocommerce div.product,.woocommerce form.checkout,.woocommerce-account .woocommerce,.woocommerce-account .u-columns{grid-template-columns:1fr}.woocommerce form.checkout #customer_details,.woocommerce form.checkout #order_review_heading,.woocommerce form.checkout #order_review{grid-column:1;grid-row:auto}.woocommerce form.checkout{padding:22px 18px}.woocommerce table.shop_table_responsive tr td::before{font-weight:600}}
@media (max-width:520px){.woocommerce ul.products{grid-template-columns:1fr}}
"""
w("woo.css", woo)
print("тема собрана:", OUT)
