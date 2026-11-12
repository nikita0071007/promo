<?php
/**
 * Защита от ботов: Cloudflare Turnstile (бесплатно) на входе, регистрации, сбросе пароля и оформлении заказа.
 * Ключи вводятся в админке: Настройки → Защита сайта. Пока ключи пусты, капча выключена.
 */
if (!defined('ABSPATH')) exit;

function dm_ts_keys() {
    return array(trim((string) get_option('dm_ts_site')), trim((string) get_option('dm_ts_secret')));
}

function dm_ts_on() {
    list($s, $k) = dm_ts_keys();
    return $s !== '' && $k !== '';
}

/* ---------- Настройки ---------- */

add_action('admin_menu', function () {
    add_options_page('Защита сайта', 'Защита сайта', 'manage_options', 'dm-protect', function () {
        echo '<div class="wrap"><h1>Защита сайта: Cloudflare Turnstile</h1>';
        echo '<p>Бесплатная капча, которая отсекает ботов на входе, регистрации, сбросе пароля и оформлении заказа. Ключи берутся в панели Cloudflare, раздел Turnstile.</p>';
        echo '<form method="post" action="options.php">';
        settings_fields('dm_protect');
        echo '<table class="form-table"><tr><th>Ключ сайта (Site Key)</th><td><input type="text" name="dm_ts_site" value="' . esc_attr(get_option('dm_ts_site')) . '" class="regular-text" autocomplete="off"></td></tr>';
        echo '<tr><th>Секретный ключ (Secret Key)</th><td><input type="password" name="dm_ts_secret" value="' . esc_attr(get_option('dm_ts_secret')) . '" class="regular-text" autocomplete="new-password"></td></tr></table>';
        submit_button('Сохранить');
        echo '<p>Статус: <strong>' . (dm_ts_on() ? 'включена' : 'выключена (нет ключей)') . '</strong></p></form></div>';
    });
});

add_action('admin_init', function () {
    register_setting('dm_protect', 'dm_ts_site', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('dm_protect', 'dm_ts_secret', array('sanitize_callback' => 'sanitize_text_field'));
});

/* ---------- Виджет ---------- */

function dm_ts_widget() {
    if (!dm_ts_on()) return;
    list($site) = dm_ts_keys();
    static $n = 0; $n++;
    echo '<div class="cf-turnstile" data-sitekey="' . esc_attr($site) . '" data-language="ru" style="margin:12px 0"></div>';
    if ($n === 1) echo '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}

foreach (array('login_form', 'register_form', 'lostpassword_form', 'woocommerce_login_form', 'woocommerce_register_form', 'woocommerce_lostpassword_form', 'woocommerce_after_order_notes') as $h) {
    add_action($h, 'dm_ts_widget');
}

add_action('wp_footer', function () {
    if (!dm_ts_on() || !function_exists('is_checkout') || !is_checkout()) return;
    echo '<script>if(window.jQuery)jQuery(document.body).on("checkout_error",function(){if(window.turnstile)try{turnstile.reset()}catch(e){}});</script>';
});

/* ---------- Проверка ---------- */

/* true — прошла, false — не прошла; при сбое связи с Cloudflare не блокируем людей */
function dm_ts_check() {
    if (!dm_ts_on()) return true;
    list(, $secret) = dm_ts_keys();
    $tok = isset($_POST['cf-turnstile-response']) ? sanitize_text_field(wp_unslash($_POST['cf-turnstile-response'])) : '';
    if ($tok === '') return false;
    $r = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
        'timeout' => 8,
        'body' => array('secret' => $secret, 'response' => $tok, 'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''),
    ));
    if (is_wp_error($r)) return true;
    $j = json_decode(wp_remote_retrieve_body($r), true);
    if (!is_array($j)) return true;
    return !empty($j['success']);
}

$dm_ts_msg = 'Подтвердите, что Вы не робот: дождитесь галочки в окошке проверки и попробуйте ещё раз.';

add_filter('authenticate', function ($user) use ($dm_ts_msg) {
    if (!dm_ts_on() || empty($_POST) || (empty($_POST['log']) && empty($_POST['username']))) return $user;
    if (!dm_ts_check()) return new WP_Error('dm_ts', $dm_ts_msg);
    return $user;
}, 30);

add_filter('registration_errors', function ($errors) use ($dm_ts_msg) {
    if (dm_ts_on() && !dm_ts_check()) $errors->add('dm_ts', $dm_ts_msg);
    return $errors;
}, 5);

add_filter('woocommerce_registration_errors', function ($errors) use ($dm_ts_msg) {
    if (dm_ts_on() && !dm_ts_check()) $errors->add('dm_ts', $dm_ts_msg);
    return $errors;
}, 5);

add_action('lostpassword_post', function ($errors) use ($dm_ts_msg) {
    if (dm_ts_on() && !dm_ts_check()) $errors->add('dm_ts', $dm_ts_msg);
});

add_action('woocommerce_after_checkout_validation', function ($data, $errors) use ($dm_ts_msg) {
    if (dm_ts_on() && !dm_ts_check()) $errors->add('dm_ts', $dm_ts_msg);
}, 10, 2);
