<?php
/**
 * Plugin Name: Дом мёда — магазин
 * Description: Цена по фасовке (вес + тара), оформление заказа без облачных касс и оплата по ссылке напрямую на счёт ИП.
 * Version: 0.6.1
 * Author: GusevMade
 */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/includes/assembly.php';
require_once __DIR__ . '/includes/account.php';
require_once __DIR__ . '/includes/extras.php';

/* ---------- Модели цены ---------- */

function dm_models() {
    static $m = null;
    if ($m !== null) return $m;
    $jar = array(
        array('label' => '0,25 л ≈ 400 г', 'g' => 400,  'tare' => 45),
        array('label' => '0,33 л ≈ 500 г', 'g' => 500,  'tare' => 45),
        array('label' => '0,5 л ≈ 700 г',  'g' => 700,  'tare' => 55),
        array('label' => '0,75 л ≈ 1 кг',  'g' => 1000, 'tare' => 75),
        array('label' => '1 л ≈ 1,5 кг',   'g' => 1500, 'tare' => 75),
        array('label' => '1,5 л ≈ 2 кг',   'g' => 2000, 'tare' => 75),
    );
    $m = array(
        'jar'      => array('name' => 'Мёд в банке: цена за кг, тара отдельно', 'sizes' => $jar, 'note' => 'Вес банки примерный. Стоимость тары добавляется отдельной строкой.'),
        'tea'      => array('name' => 'Чай: 50 г или 100 г', 'sizes' => array(
            array('label' => '50 г',  'fix' => 200),
            array('label' => '100 г', 'fix' => 280),
        ), 'note' => ''),
        'perga'    => array('name' => 'Перга, пыльца: цена за кг', 'sizes' => array(
            array('label' => '≈ 100 г', 'g' => 100),
            array('label' => '≈ 200 г', 'g' => 200),
            array('label' => '≈ 500 г', 'g' => 500),
        ), 'note' => 'Вес примерный.'),
        'propolis' => array('name' => 'Прополис: цена за кг', 'sizes' => array(
            array('label' => '≈ 15 г',        'g' => 15),
            array('label' => 'шарик ≈ 30 г',  'g' => 30),
            array('label' => '≈ 60 г',        'g' => 60),
        ), 'note' => 'Вес примерный.'),
        'comb'     => array('name' => 'Мёд в сотах: рамка, цена за кг', 'sizes' => array(
            array('label' => 'рамка ≈ 1,5–2 кг (от 1,5 кг)', 'g' => 1500),
        ), 'note' => 'Соты продаются целой рамкой, мы их не режем. Вес рамки примерно 1,5–2 кг, итоговая сумма уточняется после взвешивания.'),
    );
    return $m;
}

function dm_model($pid) {
    $k = get_post_meta($pid, '_dm_model', true);
    $m = dm_models();
    return isset($m[$k]) ? $k : '';
}

function dm_fmt($n) {
    return number_format(round($n), 0, '', "\xC2\xA0");
}

function dm_price($pid, $i) {
    $k = dm_model($pid);
    if (!$k) return null;
    $sizes = dm_models()[$k]['sizes'];
    if (!isset($sizes[$i])) $i = 0;
    $s = $sizes[$i];
    if (isset($s['fix'])) return (float) $s['fix'];
    $kg = (float) get_post_meta($pid, '_dm_kg', true);
    return round($kg * $s['g'] / 1000);
}

/* Тара считается отдельно от мёда: отдельной строкой в корзине и в заказе */
function dm_tare($pid, $i) {
    $k = dm_model($pid);
    if (!$k) return 0;
    $sizes = dm_models()[$k]['sizes'];
    if (!isset($sizes[$i])) $i = 0;
    return !empty($sizes[$i]['tare']) ? (float) $sizes[$i]['tare'] : 0;
}

/* ---------- Самовывоз ---------- */

define('DM_PICKUP_ADDR', 'Усть-Кокса, ул. Мичурина, 1В');

function dm_is_pickup($order) {
    return $order && ($order->get_meta('_dm_receive') === 'pickup' || $order->get_meta('_dm_carrier') === 'pickup');
}

/* ---------- Поля товара ---------- */

add_action('add_meta_boxes', function () {
    add_meta_box('dm_box', 'Цена по фасовке (Дом мёда)', function ($post) {
        $cur = get_post_meta($post->ID, '_dm_model', true);
        $kg = get_post_meta($post->ID, '_dm_kg', true);
        wp_nonce_field('dm_save', 'dm_nonce');
        echo '<p><select name="dm_model"><option value="">Обычная цена (поле «Цена» выше)</option>';
        foreach (dm_models() as $k => $m) {
            echo '<option value="' . esc_attr($k) . '"' . selected($cur, $k, false) . '>' . esc_html($m['name']) . '</option>';
        }
        echo '</select></p><p><label>Цена за 1 кг, ₽: <input type="number" step="1" min="0" name="dm_kg" value="' . esc_attr($kg) . '"></label></p>';
        echo '<p class="description">Для чая цена за кг не нужна. Набор фасовок и тара зашиты в плагине.</p>';
    }, 'product', 'normal', 'high');
});

add_action('save_post_product', function ($pid) {
    if (!isset($_POST['dm_nonce']) || !wp_verify_nonce($_POST['dm_nonce'], 'dm_save')) return;
    if (!current_user_can('edit_post', $pid)) return;
    update_post_meta($pid, '_dm_model', sanitize_text_field(wp_unslash($_POST['dm_model'] ?? '')));
    update_post_meta($pid, '_dm_kg', (float) ($_POST['dm_kg'] ?? 0));
});

/* ---------- Витрина ---------- */

add_filter('woocommerce_get_price_html', function ($html, $product) {
    $pid = $product->get_id();
    $k = dm_model($pid);
    if (!$k) return $html;
    $kg = (float) get_post_meta($pid, '_dm_kg', true);
    if ($k === 'jar' || $k === 'comb') return '<span class="price">' . dm_fmt($kg) . "\xC2\xA0₽ за 1 кг</span>";
    if ($k === 'propolis') return '<span class="price">' . dm_fmt($kg / 1000) . "\xC2\xA0₽ за 1 г</span>";
    if ($k === 'perga') return '<span class="price">' . dm_fmt($kg / 10) . "\xC2\xA0₽ за 100 г</span>";
    return '<span class="price">от ' . dm_fmt(dm_price($pid, 0)) . "\xC2\xA0₽</span>";
}, 10, 2);

add_filter('woocommerce_loop_add_to_cart_link', function ($html, $product) {
    if (!dm_model($product->get_id())) return $html;
    return '<a href="' . esc_url(get_permalink($product->get_id())) . '" class="button dm-pick">Выбрать фасовку</a>';
}, 10, 2);

add_action('woocommerce_before_single_product', function () {
    global $product;
    if (!$product || !dm_model($product->get_id())) return;
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
    add_action('woocommerce_single_product_summary', 'dm_form', 30);
});

function dm_form() {
    global $product;
    $pid = $product->get_id();
    $k = dm_model($pid);
    $m = dm_models()[$k];
    $def = ($k === 'jar') ? 2 : 0;
    $prices = array(); $tares = array();
    foreach ($m['sizes'] as $i => $s) { $prices[] = dm_price($pid, $i); $tares[] = dm_tare($pid, $i); }
    echo '<form class="cart dm-form" method="post" action="' . esc_url(get_permalink($pid)) . '" enctype="multipart/form-data">';
    echo '<fieldset class="dm-sizes"><legend>Фасовка</legend>';
    foreach ($m['sizes'] as $i => $s) {
        echo '<label class="dm-size"><input type="radio" name="dm_size" value="' . (int) $i . '"' . ($i === $def ? ' checked' : '') . '> <span>' . esc_html($s['label']) . '</span> <b>' . dm_fmt($prices[$i]) . "\xC2\xA0₽</b>" . ($tares[$i] ? ' <small>+ тара ' . dm_fmt($tares[$i]) . "\xC2\xA0₽</small>" : '') . '</label>';
    }
    echo '</fieldset>';
    if ($m['note']) echo '<p class="dm-note">' . esc_html($m['note']) . '</p>';
    echo '<p class="dm-total">Итого: <b id="dm-p">' . dm_fmt($prices[$def] + $tares[$def]) . "\xC2\xA0₽</b> <small id=\"dm-pt\">" . ($tares[$def] ? '(мёд ' . dm_fmt($prices[$def]) . ' ₽ + тара ' . dm_fmt($tares[$def]) . ' ₽)' : '') . '</small></p>';
    woocommerce_quantity_input(array('min_value' => 1, 'max_value' => 50, 'input_value' => 1));
    echo '<button type="submit" name="add-to-cart" value="' . (int) $pid . '" class="single_add_to_cart_button button alt">В корзину</button></form>';
    echo '<script>(function(){var P=' . wp_json_encode($prices) . ',T=' . wp_json_encode($tares) . ',f=document.querySelector(".dm-form"),o=document.getElementById("dm-p"),n=document.getElementById("dm-pt");function g(x){return String(x).replace(/\B(?=(\d{3})+(?!\d))/g," ")+" ₽"}if(!f)return;f.addEventListener("change",function(e){if(e.target.name!=="dm_size")return;var i=+e.target.value;o.textContent=g(P[i]+T[i]);n.textContent=T[i]?"(мёд "+g(P[i])+" + тара "+g(T[i])+")":""})})();</script>';
}

add_action('wp_head', function () {
    echo '<style>.dm-sizes{border:0;padding:0;margin:0 0 12px}.dm-sizes legend{font-weight:600;margin-bottom:8px}.dm-size{display:flex;gap:10px;align-items:center;padding:10px 12px;border:1px solid #d9cfc0;border-radius:10px;margin-bottom:8px;cursor:pointer}.dm-size span{flex:1}.dm-size:has(input:checked){border-color:#b8791f;background:#fff7e6}.dm-note{font-size:.9em;opacity:.75}.dm-total{font-size:1.2em}</style>';
});

/* ---------- Корзина ---------- */

add_filter('woocommerce_add_cart_item_data', function ($data, $pid) {
    if (!dm_model($pid)) return $data;
    $i = isset($_REQUEST['dm_size']) ? (int) $_REQUEST['dm_size'] : 0;
    $sizes = dm_models()[dm_model($pid)]['sizes'];
    if (!isset($sizes[$i])) $i = 0;
    $data['dm_i'] = $i;
    return $data;
}, 10, 2);

add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    foreach ($cart->get_cart() as $item) {
        if (isset($item['dm_i'])) {
            $p = dm_price($item['product_id'], $item['dm_i']);
            if ($p !== null) $item['data']->set_price($p);
        }
    }
}, 20);

add_action('woocommerce_cart_calculate_fees', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    foreach ($cart->get_cart() as $key => $item) {
        if (!isset($item['dm_i'])) continue;
        $t = dm_tare($item['product_id'], $item['dm_i']);
        if ($t <= 0) continue;
        $k = dm_model($item['product_id']);
        $label = dm_models()[$k]['sizes'][$item['dm_i']]['label'];
        $q = max(1, (int) $item['quantity']);
        $cart->fees_api()->add_fee(array(
            'id' => 'dm-tare-' . $key,
            'name' => 'Тара ' . $label . ' (' . $item['data']->get_name() . ')' . ($q > 1 ? ' × ' . $q : ''),
            'amount' => $t * $q,
            'taxable' => false,
        ));
    }
});

add_action('woocommerce_checkout_create_order_fee_item', function ($item, $fee_key, $fee) {
    if (isset($fee->id) && strpos((string) $fee->id, 'dm-tare-') === 0) $item->add_meta_data('_dm_auto_tare', 1, true);
}, 10, 3);

add_filter('woocommerce_get_item_data', function ($out, $item) {
    if (isset($item['dm_i'])) {
        $k = dm_model($item['product_id']);
        if ($k) $out[] = array('name' => 'Фасовка', 'value' => dm_models()[$k]['sizes'][$item['dm_i']]['label']);
    }
    return $out;
}, 10, 2);

add_action('woocommerce_checkout_create_order_line_item', function ($li, $key, $values) {
    if (isset($values['dm_i'])) {
        $k = dm_model($values['product_id']);
        if ($k) $li->add_meta_data('Фасовка', dm_models()[$k]['sizes'][$values['dm_i']]['label']);
    }
}, 10, 3);

/* ---------- Оформление: без адреса из пяти полей, без доставки в корзине ---------- */

add_filter('woocommerce_cart_needs_shipping', '__return_false');
add_filter('woocommerce_cart_needs_shipping_address', '__return_false');

add_filter('woocommerce_checkout_fields', function ($f) {
    $b = &$f['billing'];
    unset($b['billing_last_name'], $b['billing_company'], $b['billing_address_2'], $b['billing_state'], $b['billing_postcode'], $b['billing_city']);
    $b['billing_first_name']['label'] = 'ФИО';
    $b['billing_first_name']['class'] = array('form-row-wide');
    $b['billing_first_name']['priority'] = 10;
    $b['billing_phone']['label'] = 'Телефон';
    $b['billing_phone']['required'] = true;
    $b['billing_phone']['priority'] = 20;
    $b['billing_email']['label'] = 'Электронная почта (на неё придёт чек)';
    $b['billing_email']['required'] = true;
    $b['billing_email']['priority'] = 30;
    $b['billing_address_1']['label'] = 'Город и адрес доставки или пункт выдачи';
    $b['billing_address_1']['priority'] = 40;
    $b['dm_receive'] = array(
        'type' => 'radio',
        'label' => 'Как получить заказ',
        'required' => true,
        'default' => 'delivery',
        'options' => array(
            'delivery' => 'Доставка (СДЭК, Ozon, Пятёрочка, Почта России)',
            'pickup' => 'Самовывоз: ' . DM_PICKUP_ADDR,
        ),
        'class' => array('form-row-wide'),
        'priority' => 35,
    );
    if (isset($_POST['dm_receive']) && $_POST['dm_receive'] === 'pickup') $b['billing_address_1']['required'] = false;
    $b['dm_marketing'] = array(
        'type' => 'checkbox',
        'label' => 'Хочу получать информацию об акциях и новинках (необязательно). <a href="' . esc_url(home_url('/politika/')) . '" target="_blank" rel="noopener">Политика конфиденциальности</a>',
        'required' => false,
        'class' => array('form-row-wide'),
        'priority' => 50,
    );
    unset($f['shipping']);
    return $f;
});

add_action('wp_footer', function () {
    if (!function_exists('is_checkout') || !is_checkout()) return;
    echo '<script>(function(){function s(){var p=document.querySelector("input[name=dm_receive]:checked"),f=document.getElementById("billing_address_1_field");if(f)f.style.display=(p&&p.value==="pickup")?"none":""}document.addEventListener("change",function(e){if(e.target&&e.target.name==="dm_receive")s()});if(window.jQuery)jQuery(document.body).on("updated_checkout",s);s()})();</script>';
});

add_action('woocommerce_checkout_create_order', function ($order) {
    $pick = (isset($_POST['dm_receive']) && $_POST['dm_receive'] === 'pickup');
    $order->update_meta_data('_dm_receive', $pick ? 'pickup' : 'delivery');
    if ($pick) {
        $order->update_meta_data('_dm_carrier', 'pickup');
        if (!$order->get_billing_address_1()) $order->set_billing_address_1('Самовывоз: ' . DM_PICKUP_ADDR);
    }
    $order->update_meta_data('_dm_marketing', !empty($_POST['dm_marketing']) ? 1 : 0);
    if (!empty($_POST['dm_marketing'])) $order->update_meta_data('_dm_marketing_ts', time());
});

add_action('woocommerce_admin_order_data_after_billing_address', function ($order) {
    $v = $order->get_meta('_dm_marketing');
    echo '<p><strong>Согласие на рекламную рассылку:</strong> ' . ($v ? 'да' : 'нет') . '</p>';
});

add_filter('woocommerce_ship_to_different_address_checked', '__return_false');
add_filter('woocommerce_enable_order_notes_field', '__return_true');

add_filter('woocommerce_thankyou_order_received_text', function ($t) {
    return 'Спасибо! Заказ принят. Ссылку на оплату мы отправим на Вашу почту и в мессенджер. Оплатить можно картой МИР, Сбербанк или через СБП. После оплаты придёт чек.';
});

/* ---------- Способ оплаты: ссылка на оплату ---------- */

add_action('plugins_loaded', function () {
    if (!class_exists('WC_Payment_Gateway')) return;
    class DM_Gateway_Link extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'dm_link';
            $this->method_title = 'Оплата по ссылке';
            $this->method_description = 'Покупатель оформляет заказ, продавец отправляет ссылку на оплату напрямую на счёт ИП.';
            $this->has_fields = false;
            $this->init_form_fields();
            $this->init_settings();
            $this->title = 'Оплата по ссылке: МИР, Сбербанк, СБП';
            $this->description = 'После оформления мы пришлём ссылку на оплату. Деньги поступают напрямую на расчётный счёт ИП. После оплаты придёт чек.';
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }
        public function init_form_fields() {
            $this->form_fields = array('enabled' => array('title' => 'Включить', 'type' => 'checkbox', 'label' => 'Оплата по ссылке', 'default' => 'yes'));
        }
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $order->update_status('on-hold', 'Ожидает ссылку на оплату от продавца.');
            wc_reduce_stock_levels($order_id);
            WC()->cart->empty_cart();
            return array('result' => 'success', 'redirect' => $this->get_return_url($order));
        }
    }
    add_filter('woocommerce_payment_gateways', function ($g) { $g[] = 'DM_Gateway_Link'; return $g; });
});
