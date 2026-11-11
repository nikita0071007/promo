<?php
if (!defined('ABSPATH')) exit;

/* =====================================================================
   2. Трек-номер и письмо «Отправлен»
   ===================================================================== */

function dm_ship_carriers() {
    return array(
        'cdek' => array('СДЭК', 'https://www.cdek.ru/ru/tracking?order_id=%s'),
        'ozon' => array('Ozon', ''),
        'pyat' => array('Пятёрочка', ''),
        'post' => array('Почта России', 'https://www.pochta.ru/tracking#%s'),
        'pickup' => array('Самовывоз', ''),
        'other' => array('Другое', ''),
    );
}

function dm_ship_data($order) {
    $c = (string) $order->get_meta('_dm_carrier');
    $t = trim((string) $order->get_meta('_dm_track'));
    $car = dm_ship_carriers();
    $name = isset($car[$c]) ? $car[$c][0] : '';
    $url = '';
    if ($t && isset($car[$c]) && $car[$c][1]) $url = sprintf($car[$c][1], rawurlencode($t));
    return array('name' => $name, 'track' => $t, 'url' => $url);
}

/* Блок в кабинете: сколько знаем о посылке */
function dm_ship_info($order) {
    $d = dm_ship_data($order);
    if (!$d['name'] && !$d['track']) return '';
    $h = '<p class="dm-ship"><strong>Отправка:</strong> ' . esc_html($d['name']);
    if ($d['track']) {
        $h .= ', трек-номер <strong>' . esc_html($d['track']) . '</strong>';
        if ($d['url']) $h .= ' (<a href="' . esc_url($d['url']) . '" target="_blank" rel="noopener">отследить</a>)';
    }
    return $h . '</p>';
}

add_action('woocommerce_order_details_before_order_table', function ($order) {
    echo dm_ship_info($order);
});

add_action('add_meta_boxes', function () {
    if (!function_exists('wc_get_page_screen_id')) return;
    add_meta_box('dm_ship', 'Отправка: служба и трек-номер', 'dm_ship_box', wc_get_page_screen_id('shop-order'), 'side', 'default');
});

function dm_ship_box($p) {
    $order = ($p instanceof WP_Post) ? wc_get_order($p->ID) : $p;
    if (!$order) return;
    wp_nonce_field('dm_ship', 'dm_ship_nonce');
    $cur = (string) $order->get_meta('_dm_carrier');
    echo '<p><label>Служба<br><select name="dm_carrier" style="width:100%"><option value="">— выберите —</option>';
    foreach (dm_ship_carriers() as $k => $v) echo '<option value="' . esc_attr($k) . '"' . selected($cur, $k, false) . '>' . esc_html($v[0]) . '</option>';
    echo '</select></label></p>';
    echo '<p><label>Трек-номер<br><input type="text" name="dm_track" value="' . esc_attr($order->get_meta('_dm_track')) . '" style="width:100%"></label></p>';
    echo '<p><button type="submit" name="dm_ship_do" value="1" class="button button-primary">Сохранить и отправить клиенту</button></p>';
    echo '<p class="description">Статус станет «Отправлен», клиент получит письмо с трек-номером.</p>';
}

add_action('woocommerce_process_shop_order_meta', function ($order_id) {
    if (empty($_POST['dm_ship_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dm_ship_nonce'])), 'dm_ship')) return;
    if (!current_user_can('edit_shop_orders')) return;
    $order = wc_get_order($order_id);
    if (!$order) return;
    $c = isset($_POST['dm_carrier']) ? sanitize_key($_POST['dm_carrier']) : '';
    $t = isset($_POST['dm_track']) ? sanitize_text_field(wp_unslash($_POST['dm_track'])) : '';
    $order->update_meta_data('_dm_carrier', $c);
    $order->update_meta_data('_dm_track', $t);
    $order->save();
    if (!empty($_POST['dm_ship_do'])) {
        if ($order->get_status() === 'completed') {
            $ok = dm_ship_mail($order);
            $order->add_order_note($ok ? 'Клиенту отправлено письмо с трек-номером на ' . $order->get_billing_email() : 'ПИСЬМО С ТРЕКОМ НЕ УШЛО на ' . $order->get_billing_email());
        } else {
            $order->update_status('completed', 'Заказ отправлен.');
        }
    }
}, 70);

add_filter('woocommerce_email_enabled_customer_completed_order', '__return_false');

function dm_ship_mail($order) {
    $to = $order->get_billing_email();
    if (!$to) return false;
    $d = dm_ship_data($order);
    $mailer = WC()->mailer();
    $body = '<p>Здравствуйте, ' . esc_html($order->get_billing_first_name()) . '!</p><p>Ваш заказ №' . esc_html($order->get_order_number()) . ' отправлен.</p>';
    if ($d['name']) $body .= '<p>Служба доставки: <strong>' . esc_html($d['name']) . '</strong></p>';
    if ($d['track']) {
        $body .= '<p>Трек-номер: <strong>' . esc_html($d['track']) . '</strong></p>';
        if ($d['url']) $body .= '<p><a href="' . esc_url($d['url']) . '" style="display:inline-block;background:#e8a317;color:#222;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:bold">Отследить посылку</a></p>';
    } else {
        $body .= '<p>Трек-номер пришлём отдельным сообщением.</p>';
    }
    $body .= '<p>Статус заказа всегда можно посмотреть в личном кабинете.</p><p>Вопросы: +7 913 995-97-80, +7 913 698-09-63.</p>';
    return $mailer->send($to, 'Заказ №' . $order->get_order_number() . ' отправлен', $mailer->wrap_message('Ваш заказ отправлен', $body));
}

add_action('woocommerce_order_status_completed', function ($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    if (!$order->get_meta('_dm_ship_mail')) {
        $ok = dm_ship_mail($order);
        $order->update_meta_data('_dm_ship_mail', 1);
        $order->add_order_note($ok ? 'Клиенту отправлено письмо «Заказ отправлен» на ' . $order->get_billing_email() : 'ПИСЬМО «ЗАКАЗ ОТПРАВЛЕН» НЕ УШЛО на ' . $order->get_billing_email());
    }
    $order->update_meta_data('_dm_shipped_ts', time());
    $order->save();
});

/* =====================================================================
   5. Просьба об отзыве через 5 дней после отправки
   ===================================================================== */

add_action('init', function () {
    if (!wp_next_scheduled('dm_review_ask')) wp_schedule_event(time() + 2 * HOUR_IN_SECONDS, 'daily', 'dm_review_ask');
});

function dm_review_url() {
    $u = get_option('dm_review_url');
    return $u ? $u : home_url('/otzyvy/');
}

add_action('dm_review_ask', function () {
    if (!function_exists('wc_get_orders')) return;
    $days = (int) apply_filters('dm_review_days', 5);
    $orders = wc_get_orders(array('status' => array('completed'), 'limit' => 50, 'orderby' => 'date', 'order' => 'DESC'));
    foreach ($orders as $o) {
        $ts = (int) $o->get_meta('_dm_shipped_ts');
        if (!$ts || $o->get_meta('_dm_review_sent') || time() - $ts < $days * DAY_IN_SECONDS) continue;
        if (time() - $ts > 30 * DAY_IN_SECONDS) { $o->update_meta_data('_dm_review_sent', 'skip'); $o->save(); continue; }
        $to = $o->get_billing_email();
        if (!$to) continue;
        $mailer = WC()->mailer();
        $body = '<p>Здравствуйте, ' . esc_html($o->get_billing_first_name()) . '!</p>'
            . '<p>Надеемся, заказ №' . esc_html($o->get_order_number()) . ' дошёл в порядке и Вам понравился мёд.</p>'
            . '<p>Нам очень важно Ваше мнение. Если не сложно, напишите пару слов:</p>'
            . '<p><a href="' . esc_url(dm_review_url()) . '" style="display:inline-block;background:#e8a317;color:#222;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:bold">Оставить отзыв</a></p>'
            . '<p>Спасибо, что выбрали «Дом мёда»!</p>';
        $ok = $mailer->send($to, 'Как Вам заказ №' . $o->get_order_number() . '?', $mailer->wrap_message('Расскажите, как Вам', $body));
        $o->update_meta_data('_dm_review_sent', $ok ? time() : 'fail');
        $o->add_order_note($ok ? 'Клиенту отправлена просьба об отзыве.' : 'Просьба об отзыве не ушла.');
        $o->save();
    }
});

/* =====================================================================
   3 и 4. Меню: выгрузка заказов и согласия на рассылку
   ===================================================================== */

add_action('admin_menu', function () {
    add_submenu_page('dm-assembly', 'Выгрузка заказов', 'Выгрузка в Excel', 'edit_shop_orders', 'dm-export', 'dm_export_page');
    add_submenu_page('dm-assembly', 'Согласия на рассылку', 'Согласия на рассылку', 'edit_shop_orders', 'dm-consents', 'dm_consents_page');
    add_submenu_page('dm-assembly', 'Настройки', 'Настройки', 'manage_options', 'dm-settings', 'dm_settings_page');
});

function dm_csv_send($filename, $rows) {
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    foreach ($rows as $r) fputcsv($out, $r, ';');
    fclose($out);
    exit;
}

function dm_export_page() {
    $first = gmdate('Y-m-01');
    $today = gmdate('Y-m-d');
    echo '<div class="wrap"><h1>Выгрузка заказов в Excel</h1>';
    echo '<p>Файл открывается в Excel и содержит заказы за выбранные даты: клиент, состав, вес, сумма, статус, доставка, трек.</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="dm_export">';
    wp_nonce_field('dm_export', 'dm_export_nonce');
    echo '<p>С <input type="date" name="from" value="' . esc_attr($first) . '"> по <input type="date" name="to" value="' . esc_attr($today) . '"></p>';
    echo '<p><label><input type="checkbox" name="paid" value="1"> Только оплаченные и отправленные</label></p>';
    echo '<p><button class="button button-primary">Скачать .csv</button></p></form></div>';
}

add_action('admin_post_dm_export', function () {
    if (!current_user_can('edit_shop_orders') || empty($_POST['dm_export_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dm_export_nonce'])), 'dm_export')) wp_die('Нет доступа');
    $from = isset($_POST['from']) ? sanitize_text_field(wp_unslash($_POST['from'])) : '';
    $to = isset($_POST['to']) ? sanitize_text_field(wp_unslash($_POST['to'])) : '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) wp_die('Неверные даты');
    $args = array('limit' => -1, 'orderby' => 'date', 'order' => 'ASC', 'date_created' => $from . '...' . $to . ' 23:59:59');
    if (!empty($_POST['paid'])) $args['status'] = array('processing', 'completed');
    $rows = array(array('№', 'Дата', 'Статус', 'Клиент', 'Телефон', 'Почта', 'Состав', 'Сумма, ₽', 'Доставка', 'Служба', 'Трек', 'Согласие на рассылку', 'Комментарий клиента'));
    foreach (wc_get_orders($args) as $o) {
        $items = array();
        foreach ($o->get_items('line_item') as $it) {
            $w = $it->get_meta('Вес по факту, г');
            $items[] = $it->get_name() . ' (' . $it->get_meta('Фасовка') . ') × ' . $it->get_quantity() . ($w ? ', вес ' . $w . ' г' : '');
        }
        $d = dm_ship_data($o);
        $rows[] = array(
            $o->get_order_number(),
            wc_format_datetime($o->get_date_created(), 'd.m.Y H:i'),
            wc_get_order_status_name($o->get_status()),
            trim($o->get_billing_first_name() . ' ' . $o->get_billing_last_name()),
            $o->get_billing_phone(),
            $o->get_billing_email(),
            implode('; ', $items),
            round($o->get_total()),
            trim($o->get_shipping_address_1() . ' ' . $o->get_billing_address_1() . ' ' . $o->get_billing_city()),
            $d['name'],
            $d['track'],
            $o->get_meta('_dm_marketing') ? 'да' : 'нет',
            $o->get_customer_note(),
        );
    }
    dm_csv_send('zakazy-' . $from . '_' . $to . '.csv', $rows);
});

function dm_consent_list() {
    $out = array();
    foreach (wc_get_orders(array('limit' => -1, 'orderby' => 'date', 'order' => 'DESC')) as $o) {
        if (!$o->get_meta('_dm_marketing')) continue;
        $mail = strtolower(trim($o->get_billing_email()));
        if (!$mail || isset($out[$mail])) continue;
        $out[$mail] = array(
            'date' => wc_format_datetime($o->get_date_created(), 'd.m.Y H:i'),
            'name' => $o->get_billing_first_name(),
            'email' => $mail,
            'phone' => $o->get_billing_phone(),
            'order' => $o->get_order_number(),
            'revoked' => (bool) get_option('dm_revoked_' . md5($mail)),
        );
    }
    foreach (get_users(array('meta_key' => 'dm_marketing', 'meta_value' => '1', 'fields' => 'all')) as $u) {
        $mail = strtolower(trim($u->user_email));
        if (!$mail || isset($out[$mail])) continue;
        $ts = (int) get_user_meta($u->ID, 'dm_marketing_ts', true);
        $out[$mail] = array(
            'date' => $ts ? wp_date('d.m.Y H:i', $ts) : '',
            'name' => get_user_meta($u->ID, 'billing_first_name', true) ?: $u->display_name,
            'email' => $mail,
            'phone' => get_user_meta($u->ID, 'billing_phone', true),
            'order' => '—',
            'revoked' => (bool) get_option('dm_revoked_' . md5($mail)),
        );
    }
    return $out;
}

function dm_consents_page() {
    if (!current_user_can('edit_shop_orders')) return;
    if (isset($_POST['dm_rev_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dm_rev_nonce'])), 'dm_rev') && !empty($_POST['mail'])) {
        $m = strtolower(sanitize_email(wp_unslash($_POST['mail'])));
        if (!empty($_POST['undo'])) delete_option('dm_revoked_' . md5($m)); else update_option('dm_revoked_' . md5($m), time(), false);
    }
    $list = dm_consent_list();
    echo '<div class="wrap"><h1>Согласия на рекламную рассылку</h1>';
    echo '<p>Здесь клиенты, которые отметили галочку при заказе. Если клиент написал, что отзывает согласие, нажмите «Отозвал»: он останется в списке с пометкой и в выгрузку для рассылок не попадёт. Это одна строка на почту, берётся самый свежий заказ.</p>';
    echo '<p><a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=dm_consents_csv'), 'dm_consents')) . '">Скачать список для рассылки (.csv)</a></p>';
    if (!$list) { echo '<p>Пока никто не дал согласие.</p></div>'; return; }
    echo '<table class="widefat striped"><thead><tr><th>Дата согласия</th><th>Имя</th><th>Почта</th><th>Телефон</th><th>Заказ</th><th>Статус</th><th></th></tr></thead><tbody>';
    foreach ($list as $c) {
        echo '<tr><td>' . esc_html($c['date']) . '</td><td>' . esc_html($c['name']) . '</td><td>' . esc_html($c['email']) . '</td><td>' . esc_html($c['phone']) . '</td><td>' . ($c['order'] === '—' ? '—' : '№' . esc_html($c['order'])) . '</td><td>' . ($c['revoked'] ? '<strong style="color:#b32d2e">отозвал</strong>' : 'согласен') . '</td><td>';
        echo '<form method="post" style="margin:0">';
        wp_nonce_field('dm_rev', 'dm_rev_nonce');
        echo '<input type="hidden" name="mail" value="' . esc_attr($c['email']) . '">';
        echo $c['revoked'] ? '<button class="button" name="undo" value="1">Вернуть</button>' : '<button class="button">Отозвал</button>';
        echo '</form></td></tr>';
    }
    echo '</tbody></table></div>';
}

add_action('admin_post_dm_consents_csv', function () {
    if (!current_user_can('edit_shop_orders') || !wp_verify_nonce(isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '', 'dm_consents')) wp_die('Нет доступа');
    $rows = array(array('Дата согласия', 'Имя', 'Почта', 'Телефон', 'Заказ'));
    foreach (dm_consent_list() as $c) {
        if ($c['revoked']) continue;
        $rows[] = array($c['date'], $c['name'], $c['email'], $c['phone'], $c['order']);
    }
    dm_csv_send('rassylka-soglasiya-' . gmdate('Y-m-d') . '.csv', $rows);
});

function dm_settings_page() {
    if (!current_user_can('manage_options')) return;
    if (isset($_POST['dm_set_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dm_set_nonce'])), 'dm_set')) {
        update_option('dm_review_url', esc_url_raw(trim(wp_unslash($_POST['dm_review_url']))));
        echo '<div class="notice notice-success"><p>Сохранено.</p></div>';
    }
    echo '<div class="wrap"><h1>Настройки магазина</h1><form method="post">';
    wp_nonce_field('dm_set', 'dm_set_nonce');
    echo '<p><label><strong>Ссылка для отзывов</strong> (куда ведёт кнопка «Оставить отзыв» в письме через 5 дней после отправки):<br>';
    echo '<input type="url" name="dm_review_url" value="' . esc_attr(get_option('dm_review_url')) . '" style="width:100%;max-width:640px" placeholder="' . esc_attr(home_url('/otzyvy/')) . '"></label></p>';
    echo '<p class="description">Если оставить пустым, письмо поведёт на страницу «Отзывы» сайта. Можно вставить ссылку на карточку организации в Яндекс Картах или группу ВКонтакте.</p>';
    echo '<p><button class="button button-primary">Сохранить</button></p></form></div>';
}

/* Реклама только тем, кто не отозвал согласие */
function dm_may_send_marketing($email) {
    return !get_option('dm_revoked_' . md5(strtolower(trim($email))));
}


/* ---------- Согласие на рассылку: личный кабинет и ссылка «Отписаться» ---------- */

function dm_has_marketing($user_id) {
    $u = get_userdata($user_id);
    if (!$u) return false;
    $mail = strtolower(trim($u->user_email));
    if (get_option('dm_revoked_' . md5($mail))) return false;
    if (get_user_meta($user_id, 'dm_marketing', true) === '1') return true;
    return isset(dm_consent_list()[$mail]);
}

// Галочка в «Мои данные»
add_action('woocommerce_edit_account_form', function () {
    $on = dm_has_marketing(get_current_user_id());
    echo '<p class="woocommerce-form-row form-row dm-opt"><label style="display:flex;gap:10px;align-items:flex-start"><input type="checkbox" name="dm_marketing_acc" value="1"' . checked($on, true, false) . ' style="margin-top:4px"><span>Хочу получать информацию об акциях и новинках на почту. Отказаться можно в любой момент здесь или по ссылке «Отписаться» в письме.</span></label></p>';
}, 20);

add_action('woocommerce_save_account_details', function ($uid) {
    $u = get_userdata($uid);
    if (!$u) return;
    $mail = strtolower(trim($u->user_email));
    if (!empty($_POST['dm_marketing_acc'])) {
        delete_option('dm_revoked_' . md5($mail));
        if (get_user_meta($uid, 'dm_marketing', true) !== '1') update_user_meta($uid, 'dm_marketing_ts', time());
        update_user_meta($uid, 'dm_marketing', '1');
    } else {
        update_user_meta($uid, 'dm_marketing', '0');
        update_option('dm_revoked_' . md5($mail), time(), false);
    }
});

// Необязательная галочка при регистрации
add_action('woocommerce_register_form', function () {
    echo '<p class="woocommerce-form-row form-row"><label style="display:flex;gap:10px;align-items:flex-start"><input type="checkbox" name="dm_marketing_reg" value="1" style="margin-top:4px"><span>Хочу получать информацию об акциях и новинках (необязательно). Согласие можно отозвать в любой момент.</span></label></p>';
});

add_action('woocommerce_created_customer', function ($uid) {
    if (empty($_POST['dm_marketing_reg'])) return;
    update_user_meta($uid, 'dm_marketing', '1');
    update_user_meta($uid, 'dm_marketing_ts', time());
    $u = get_userdata($uid);
    if ($u) delete_option('dm_revoked_' . md5(strtolower(trim($u->user_email))));
}, 20);

// Ссылка «Отписаться» для рекламных писем: dm_unsub_url($email)
function dm_unsub_url($email) {
    $email = strtolower(trim($email));
    $sig = hash_hmac('sha256', $email, wp_salt('auth'));
    return add_query_arg(array('dm_unsub' => rawurlencode($email), 'sig' => $sig), home_url('/'));
}

add_action('template_redirect', function () {
    if (empty($_GET['dm_unsub']) || empty($_GET['sig'])) return;
    $email = strtolower(trim(rawurldecode(sanitize_text_field(wp_unslash($_GET['dm_unsub'])))));
    $ok = $email && hash_equals(hash_hmac('sha256', $email, wp_salt('auth')), sanitize_text_field(wp_unslash($_GET['sig'])));
    if ($ok) {
        update_option('dm_revoked_' . md5($email), time(), false);
        $msg = '<h2>Вы отписались от рассылки</h2><p>Рекламные сообщения на ' . esc_html($email) . ' больше приходить не будут. Письма о Ваших заказах (статус, оплата, чек, отправка) продолжат приходить.</p><p>Если передумаете, согласие можно вернуть в личном кабинете, в разделе «Мои данные».</p>';
    } else {
        $msg = '<h2>Ссылка не подошла</h2><p>Напишите нам на vm-surtaev@yandex.ru, и мы отпишем Вас вручную.</p>';
    }
    wp_die($msg, 'Рассылка', array('response' => 200, 'back_link' => true));
});
