<?php
/** Личный кабинет: вход и регистрация по почте, понятные статусы заказа, кнопка «Оплатить». */
if (!defined('ABSPATH')) exit;

/* Регистрация по почте: логин создаётся сам, пароль клиент задаёт по ссылке из письма. */
foreach (array(
    'woocommerce_enable_myaccount_registration' => 'yes',
    'woocommerce_registration_generate_username' => 'yes',
    'woocommerce_registration_generate_password' => 'yes',
    'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
    'woocommerce_enable_guest_checkout' => 'yes',
) as $opt => $val) {
    add_filter('pre_option_' . $opt, function () use ($val) { return $val; });
}

/* Меню */
add_filter('woocommerce_account_menu_items', function ($items) {
    $names = array('dashboard' => 'Главная', 'orders' => 'Мои заказы', 'edit-address' => 'Адрес доставки', 'edit-account' => 'Мои данные', 'customer-logout' => 'Выйти');
    $out = array();
    foreach ($names as $k => $v) if (isset($items[$k])) $out[$k] = $v;
    return $out;
});

/* Главная кабинета */
remove_action('woocommerce_account_dashboard', 'woocommerce_account_dashboard');
add_action('woocommerce_account_dashboard', function () {
    $u = wp_get_current_user();
    $name = trim(get_user_meta($u->ID, 'billing_first_name', true));
    if (!$name) $name = $u->display_name;
    echo '<div class="dm-acc-hello"><h3>Здравствуйте, ' . esc_html($name) . '!</h3><p>Здесь Вы видите свои заказы и ссылки на оплату. Чек и ссылка также приходят на почту <strong>' . esc_html($u->user_email) . '</strong>.</p></div>';
    $orders = wc_get_orders(array('customer' => $u->ID, 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC'));
    if ($orders) {
        $o = $orders[0];
        echo '<div class="dm-acc-card"><div class="dm-acc-top"><strong>Последний заказ №' . esc_html($o->get_order_number()) . '</strong><span class="dm-st dm-st-' . esc_attr($o->get_status()) . '">' . esc_html(wc_get_order_status_name($o->get_status())) . '</span></div>';
        echo '<div class="dm-steps">' . dm_acc_steps($o->get_status()) . '</div>';
        if (function_exists('dm_ship_info')) echo dm_ship_info($o);
        echo '<p class="dm-acc-sum">Сумма: <strong>' . wp_kses_post($o->get_formatted_order_total()) . '</strong>' . ($o->get_status() === 'on-hold' ? ' <span>ориентировочная, точную назовём после взвешивания</span>' : '') . '</p>';
        $url = $o->get_meta('_dm_pay_url');
        if ($o->get_status() === 'dm-await-pay' && $url) echo '<p><a class="btn" href="' . esc_url($url) . '" target="_blank" rel="noopener">Оплатить ' . wp_kses_post($o->get_formatted_order_total()) . '</a></p>';
        echo '<p><a href="' . esc_url($o->get_view_order_url()) . '">Подробнее о заказе</a></p></div>';
    } else {
        echo '<div class="dm-acc-card"><p>Заказов пока нет.</p><p><a class="btn" href="' . esc_url(function_exists('dm_shop_url') ? dm_shop_url() : home_url('/shop/')) . '">Перейти в каталог</a></p></div>';
    }
});

function dm_acc_steps($status) {
    $steps = array('on-hold' => 'Взвешиваем', 'dm-await-pay' => 'Ждёт оплаты', 'processing' => 'Оплачен', 'completed' => 'Отправлен');
    $keys = array_keys($steps); $cur = array_search($status, $keys, true);
    $h = '';
    $i = 0;
    foreach ($steps as $k => $label) {
        $cls = ($cur !== false && $i < $cur) ? 'done' : (($cur !== false && $i === $cur) ? 'now' : '');
        $h .= '<span class="dm-step ' . $cls . '"><i>' . ($i + 1) . '</i>' . esc_html($label) . '</span>';
        $i++;
    }
    return $h;
}

/* В списке заказов: кнопка «Оплатить», когда готова ссылка */
add_filter('woocommerce_my_account_my_orders_actions', function ($actions, $order) {
    $url = $order->get_meta('_dm_pay_url');
    if ($order->get_status() === 'dm-await-pay' && $url) {
        $actions = array_merge(array('dm_pay' => array('url' => $url, 'name' => 'Оплатить')), $actions);
    }
    return $actions;
}, 10, 2);

/* Подсказка на странице входа */
add_action('woocommerce_login_form_start', function () {
    echo '<p class="dm-login-note">Войдите по почте и паролю. Почта нужна обязательно: на неё приходят чек и ссылка на оплату.</p>';
});
add_action('woocommerce_register_form_start', function () {
    echo '<p class="dm-login-note">Укажите почту: на неё придёт ссылка, чтобы задать пароль, а также чек и ссылка на оплату заказа.</p>';
});

/* Место для кнопок входа через ВК, Telegram, MAX (показываются, когда подключены) */
add_action('woocommerce_login_form_end', function () {
    $html = apply_filters('dm_social_login_buttons', '');
    if ($html) echo '<div class="dm-social"><span>или войти через</span>' . $html . '</div>';
});

/* ---------- Регистрация: имя, телефон, почта ---------- */

add_action('woocommerce_register_form_start', function () {
    $n = isset($_POST['dm_reg_name']) ? esc_attr(wp_unslash($_POST['dm_reg_name'])) : '';
    $p = isset($_POST['dm_reg_phone']) ? esc_attr(wp_unslash($_POST['dm_reg_phone'])) : '';
    echo '<p class="woocommerce-form-row form-row"><label for="dm_reg_name">Как к Вам обращаться (ФИО) <span class="required">*</span></label><input type="text" class="input-text" name="dm_reg_name" id="dm_reg_name" autocomplete="name" value="' . $n . '" required></p>';
    echo '<p class="woocommerce-form-row form-row"><label for="dm_reg_phone">Телефон <span class="required">*</span></label><input type="tel" class="input-text" name="dm_reg_phone" id="dm_reg_phone" autocomplete="tel" placeholder="+7 ___ ___-__-__" value="' . $p . '" required></p>';
});

add_filter('woocommerce_registration_errors', function ($errors, $username, $email) {
    $n = isset($_POST['dm_reg_name']) ? trim(wp_unslash($_POST['dm_reg_name'])) : '';
    $p = isset($_POST['dm_reg_phone']) ? preg_replace('/\D+/', '', wp_unslash($_POST['dm_reg_phone'])) : '';
    if ($n === '') $errors->add('dm_name', 'Укажите имя.');
    if (strlen($p) < 10) $errors->add('dm_phone', 'Укажите телефон, чтобы мы могли с Вами связаться.');
    return $errors;
}, 10, 3);

add_action('woocommerce_created_customer', function ($uid) {
    $n = isset($_POST['dm_reg_name']) ? sanitize_text_field(wp_unslash($_POST['dm_reg_name'])) : '';
    $p = isset($_POST['dm_reg_phone']) ? sanitize_text_field(wp_unslash($_POST['dm_reg_phone'])) : '';
    if ($n) {
        update_user_meta($uid, 'first_name', $n);
        update_user_meta($uid, 'billing_first_name', $n);
        wp_update_user(array('ID' => $uid, 'display_name' => $n));
    }
    if ($p) update_user_meta($uid, 'billing_phone', $p);
});

/* ---------- Мои данные: телефон ---------- */

add_action('woocommerce_edit_account_form', function () {
    $v = get_user_meta(get_current_user_id(), 'billing_phone', true);
    echo '<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide"><label for="dm_phone">Телефон <span class="required">*</span></label><input type="tel" class="woocommerce-Input input-text" name="dm_phone" id="dm_phone" value="' . esc_attr($v) . '" required></p>';
});

add_action('woocommerce_save_account_details_errors', function ($errors) {
    $p = isset($_POST['dm_phone']) ? preg_replace('/\D+/', '', wp_unslash($_POST['dm_phone'])) : '';
    if (strlen($p) < 10) $errors->add('dm_phone', 'Укажите телефон.');
});

add_action('woocommerce_save_account_details', function ($uid) {
    if (isset($_POST['dm_phone'])) update_user_meta($uid, 'billing_phone', sanitize_text_field(wp_unslash($_POST['dm_phone'])));
    if (!empty($_POST['account_first_name'])) update_user_meta($uid, 'billing_first_name', sanitize_text_field(wp_unslash($_POST['account_first_name'])));
});
