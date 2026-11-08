<?php
/**
 * Сборка заказов: взвешивание, точная сумма, ссылка на оплату клиенту.
 * Статусы: «Ждёт взвешивания» (on-hold) → «Ждёт оплаты» (dm-await-pay) → «Оплачен» (processing) → «Отправлен» (completed).
 */
if (!defined('ABSPATH')) exit;

/* ---------- Статусы ---------- */

add_action('init', function () {
    register_post_status('wc-dm-await-pay', array(
        'label' => 'Ждёт оплаты',
        'public' => false,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Ждёт оплаты <span class="count">(%s)</span>', 'Ждёт оплаты <span class="count">(%s)</span>'),
    ));
});

add_filter('wc_order_statuses', function ($s) {
    $s['wc-on-hold'] = 'Ждёт взвешивания';
    $s['wc-processing'] = 'Оплачен';
    $s['wc-completed'] = 'Отправлен';
    $n = array();
    foreach ($s as $k => $v) {
        $n[$k] = $v;
        if ($k === 'wc-on-hold') $n['wc-dm-await-pay'] = 'Ждёт оплаты';
    }
    return $n;
});

/* ---------- Позиции заказа, которые взвешиваются ---------- */

function dm_asm_lines($order) {
    $out = array();
    foreach ($order->get_items('line_item') as $id => $item) {
        $pid = $item->get_product_id();
        $k = dm_model($pid);
        if (!in_array($k, array('jar', 'perga', 'propolis', 'comb'), true)) continue;
        $kg = (float) get_post_meta($pid, '_dm_kg', true);
        $label = $item->get_meta('Фасовка');
        $tare = 0; $g = 0;
        foreach (dm_models()[$k]['sizes'] as $s) {
            if ($s['label'] === $label) { $tare = isset($s['tare']) ? $s['tare'] : 0; $g = isset($s['g']) ? $s['g'] : 0; break; }
        }
        $qty = max(1, (int) $item->get_quantity());
        $out[$id] = array('item' => $item, 'kg' => $kg, 'tare' => $tare, 'qty' => $qty, 'approx' => $g * $qty, 'label' => $label, 'name' => $item->get_name());
    }
    return $out;
}

function dm_asm_exact($line, $w) {
    return round($line['kg'] * $w / 1000 + $line['tare'] * $line['qty']);
}

function dm_asm_money($n) {
    return number_format($n, 0, '', ' ') . ' ₽';
}

/* ---------- Блок на странице заказа ---------- */

add_action('add_meta_boxes', function () {
    if (!function_exists('wc_get_page_screen_id')) return;
    add_meta_box('dm_asm', 'Сборка заказа: взвешивание и ссылка на оплату', 'dm_asm_box', wc_get_page_screen_id('shop-order'), 'normal', 'high');
});

function dm_asm_box($p) {
    $order = ($p instanceof WP_Post) ? wc_get_order($p->ID) : $p;
    if (!$order) return;
    wp_nonce_field('dm_asm', 'dm_asm_nonce');
    $lines = dm_asm_lines($order);
    echo '<style>.dm-asm td,.dm-asm th{padding:6px 8px;text-align:left}.dm-asm input[type=number]{width:90px}.dm-asm .tot{font-size:15px;margin:10px 0}</style>';
    if (!$lines) {
        echo '<p>В заказе нет товаров на развес: взвешивать нечего. Ссылку на оплату можно отправить сразу.</p>';
    } else {
        echo '<p>Впишите фактический вес в граммах (общий для всех банок одной позиции). Сумма пересчитается: цена за кг × вес + тара.</p>';
        echo '<table class="dm-asm widefat"><thead><tr><th>Товар</th><th>Фасовка в заказе</th><th>Кол-во</th><th>Вес по факту, г</th><th>Сумма</th></tr></thead><tbody>';
        foreach ($lines as $id => $l) {
            $w = $l['item']->get_meta('_dm_w');
            echo '<tr><td>' . esc_html($l['name']) . '</td><td>' . esc_html($l['label']) . '</td><td>' . (int) $l['qty'] . '</td>';
            echo '<td><input type="number" min="0" step="1" class="dm-w" name="dm_w[' . (int) $id . ']" value="' . esc_attr($w) . '" placeholder="' . (int) $l['approx'] . '" data-kg="' . esc_attr($l['kg']) . '" data-tare="' . esc_attr($l['tare'] * $l['qty']) . '"></td>';
            echo '<td class="dm-sum">' . esc_html(dm_asm_money($l['item']->get_total())) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
    $url = $order->get_meta('_dm_pay_url');
    echo '<p style="margin-top:14px"><label><strong>Ссылка на оплату</strong> (из банка, на точную сумму):<br><input type="url" name="dm_pay_url" value="' . esc_attr($url) . '" style="width:100%" placeholder="https://..."></label></p>';
    $others = (float) $order->get_total();
    foreach ($lines as $l) $others -= (float) $l['item']->get_total();
    $num = (int) round($order->get_total());
    echo '<p class="tot">Итого сейчас: <strong id="dm-tot">' . esc_html(dm_asm_money($order->get_total())) . '</strong> '
        . '<button type="button" class="button" id="dm-copy-sum" data-v="' . $num . '">Скопировать сумму для банка: <span id="dm-sum-num">' . $num . '</span></button></p>';
    $first = $order->get_billing_first_name();
    echo '<p><label><strong>Готовое сообщение клиенту</strong> (для мессенджера или СМС; ссылка подставится сама):<br>'
        . '<textarea id="dm-msg" rows="5" style="width:100%" readonly data-name="' . esc_attr($first) . '" data-num="' . esc_attr($order->get_order_number()) . '"></textarea></label><br>'
        . '<button type="button" class="button" id="dm-copy-msg">Скопировать сообщение</button></p>';
    echo '<p><button type="submit" name="dm_do" value="recalc" class="button">Сохранить вес и пересчитать</button> ';
    echo '<button type="submit" name="dm_do" value="send" class="button button-primary">Пересчитать и отправить ссылку клиенту</button></p>';
    $rurl = $order->get_meta('_dm_receipt_url');
    echo '<hr><p><label><strong>Чек об оплате</strong> (ссылка на чек из кассы или банка):<br><input type="url" name="dm_receipt_url" value="' . esc_attr($rurl) . '" style="width:100%" placeholder="https://..."></label></p>';
    echo '<p><button type="submit" name="dm_do" value="receipt" class="button">Отправить клиенту чек</button> <span class="description">Письмо «Заказ оплачен» уходит само, когда вы ставите статус «Оплачен». Чек отправляйте этой кнопкой, когда получите ссылку.</span></p>';
    echo '<p class="description">После отправки статус станет «Ждёт оплаты». Когда деньги поступили, поставьте статус «Оплачен», а при отправке посылки — «Отправлен».</p>';
    echo '<script>(function(){
var others=' . json_encode($others) . ';
var rows=document.querySelectorAll(".dm-w");
function f(n){return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g," ")+" ₽"}
var tot=document.getElementById("dm-tot"),numEl=document.getElementById("dm-sum-num"),btn=document.getElementById("dm-copy-sum");
var msg=document.getElementById("dm-msg"),url=document.querySelector("input[name=dm_pay_url]");
function calc(){var t=others;rows.forEach(function(i){var w=parseFloat(i.value);var s=i.closest("tr").querySelector(".dm-sum");if(w>0){var v=parseFloat(i.dataset.kg)*w/1000+parseFloat(i.dataset.tare);s.textContent=f(v);t+=v}else{t+=parseFloat((s.textContent||"0").replace(/[^\d]/g,""))||0}});return Math.round(t)}
function upd(){var t=calc();tot.textContent=f(t);numEl.textContent=t;btn.dataset.v=t;
msg.value="Здравствуйте"+(msg.dataset.name?", "+msg.dataset.name:"")+"! Ваш заказ №"+msg.dataset.num+" в «Доме мёда» собран и взвешен. Точная сумма: "+f(t)+".\nОплатить можно по ссылке (карта МИР, Сбербанк или СБП): "+(url.value||"[вставьте ссылку выше]")+"\nСпасибо, что выбрали нас!"}
function cp(text,b){var old=b.textContent;function ok(){b.textContent="Скопировано";setTimeout(function(){b.textContent=old},1500)}if(navigator.clipboard){navigator.clipboard.writeText(text).then(ok)}else{var a=document.createElement("textarea");a.value=text;document.body.appendChild(a);a.select();document.execCommand("copy");a.remove();ok()}}
rows.forEach(function(i){i.addEventListener("input",upd)});url.addEventListener("input",upd);
btn.addEventListener("click",function(){var v=btn.dataset.v;cp(v,btn);setTimeout(upd,1600)});
document.getElementById("dm-copy-msg").addEventListener("click",function(){cp(msg.value,this)});
upd();})();</script>';
}

/* ---------- Сохранение, пересчёт, отправка ---------- */

add_action('woocommerce_process_shop_order_meta', function ($order_id) {
    if (empty($_POST['dm_asm_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dm_asm_nonce'])), 'dm_asm')) return;
    if (!current_user_can('edit_shop_orders')) return;
    $order = wc_get_order($order_id);
    if (!$order) return;

    $url = isset($_POST['dm_pay_url']) ? esc_url_raw(trim(wp_unslash($_POST['dm_pay_url']))) : '';
    $order->update_meta_data('_dm_pay_url', $url);

    $rurl = isset($_POST['dm_receipt_url']) ? esc_url_raw(trim(wp_unslash($_POST['dm_receipt_url']))) : '';
    $order->update_meta_data('_dm_receipt_url', $rurl);
    $do = isset($_POST['dm_do']) ? sanitize_key($_POST['dm_do']) : '';
    if ($do === 'recalc' || $do === 'send') {
        $ws = isset($_POST['dm_w']) && is_array($_POST['dm_w']) ? wp_unslash($_POST['dm_w']) : array();
        $changed = array();
        foreach (dm_asm_lines($order) as $id => $l) {
            $w = isset($ws[$id]) ? (float) $ws[$id] : 0;
            if ($w <= 0) continue;
            $sum = dm_asm_exact($l, $w);
            $l['item']->update_meta_data('_dm_w', $w);
            $l['item']->update_meta_data('Вес по факту, г', $w);
            $l['item']->set_subtotal($sum);
            $l['item']->set_total($sum);
            $l['item']->save();
            $changed[] = $l['name'] . ': ' . (int) $w . ' г = ' . dm_asm_money($sum);
        }
        $order->calculate_totals(false);
        if ($changed) $order->add_order_note('Взвешено. ' . implode('; ', $changed) . '. Итого ' . dm_asm_money($order->get_total()));
    }
    $order->save();

    if ($do === 'receipt') {
        if (stripos($rurl, 'https://') !== 0) { $order->add_order_note('Чек не отправлен: укажите ссылку на чек (https://...).'); return; }
        $ok = dm_asm_mail_paid($order, true);
        $order->add_order_note($ok ? 'Чек отправлен клиенту на ' . $order->get_billing_email() : 'ПИСЬМО С ЧЕКОМ НЕ УШЛО на ' . $order->get_billing_email());
        return;
    }
    if ($do === 'send') {
        if (!$url || stripos($url, 'https://') !== 0) {
            $order->add_order_note('Ссылка не отправлена: укажите ссылку на оплату (https://...).');
            return;
        }
        $to = $order->get_billing_email();
        if (!$to) { $order->add_order_note('Ссылка не отправлена: у клиента нет почты.'); return; }
        $mailer = WC()->mailer();
        $rows = '';
        foreach ($order->get_items('line_item') as $item) {
            $rows .= '<li>' . esc_html($item->get_name()) . ' — ' . esc_html($item->get_meta('Фасовка')) . ' × ' . (int) $item->get_quantity() . ': ' . esc_html(dm_asm_money($item->get_total())) . '</li>';
        }
        $body = '<p>Здравствуйте, ' . esc_html($order->get_billing_first_name()) . '!</p>'
            . '<p>Ваш заказ №' . esc_html($order->get_order_number()) . ' собран и взвешен. Точная сумма: <strong>' . esc_html(dm_asm_money($order->get_total())) . '</strong>.</p>'
            . '<ul>' . $rows . '</ul>'
            . '<p>Оплатить можно по ссылке картой МИР, Сбербанк или через СБП. Деньги поступают напрямую на расчётный счёт ИП, после оплаты придёт чек.</p>'
            . '<p><a href="' . esc_url($url) . '" style="display:inline-block;background:#e8a317;color:#222;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:bold">Оплатить ' . esc_html(dm_asm_money($order->get_total())) . '</a></p>'
            . '<p>Если кнопка не открывается, скопируйте ссылку: ' . esc_html($url) . '</p>'
            . '<p>Вопросы: +7 913 995-97-80, +7 913 698-09-63.</p>';
        $sent = $mailer->send($to, 'Заказ №' . $order->get_order_number() . ': точная сумма и ссылка на оплату', $mailer->wrap_message('Ваш заказ собран', $body));
        $order->update_meta_data('_dm_sent_ts', time()); $order->delete_meta_data('_dm_remind_sent'); $order->save();
        $order->update_status('dm-await-pay', ($sent ? 'Письмо со ссылкой на оплату отправлено на ' . $to : 'ПИСЬМО НЕ УШЛО на ' . $to . ' (проверьте настройки почты)') . '. Сумма ' . dm_asm_money($order->get_total()) . '.');
    }
}, 60);

/* ---------- Пункт меню слева: «Сборка заказов» ---------- */

add_action('admin_menu', function () {
    $n = function_exists('wc_orders_count') ? (int) wc_orders_count('on-hold') : 0;
    $bubble = $n ? ' <span class="awaiting-mod">' . $n . '</span>' : '';
    add_menu_page('Сборка заказов', 'Сборка заказов' . $bubble, 'edit_shop_orders', 'dm-assembly', 'dm_asm_page', 'dashicons-archive', 3);
});

function dm_asm_table($orders, $empty) {
    if (!$orders) { echo '<p>' . esc_html($empty) . '</p>'; return; }
    echo '<table class="widefat striped"><thead><tr><th>№</th><th>Дата</th><th>Клиент</th><th>Телефон</th><th>Состав</th><th>Сумма</th><th></th></tr></thead><tbody>';
    foreach ($orders as $o) {
        $names = array();
        foreach ($o->get_items('line_item') as $it) $names[] = $it->get_name() . ' (' . $it->get_meta('Фасовка') . ') × ' . $it->get_quantity();
        $ts = (int) $o->get_meta('_dm_sent_ts');
        $late = ($o->get_status() === 'dm-await-pay' && $ts && time() - $ts > DAY_IN_SECONDS);
        echo '<tr' . ($late ? ' style="background:#fff0d6"' : '') . '><td>' . esc_html($o->get_order_number()) . ($late ? ' <span title="Ждёт оплаты больше суток">⏰</span>' : '') . '</td><td>' . esc_html(wc_format_datetime($o->get_date_created(), 'd.m H:i')) . '</td><td>' . esc_html($o->get_billing_first_name()) . '</td><td>' . esc_html($o->get_billing_phone()) . '</td><td>' . esc_html(implode('; ', $names)) . '</td><td>' . esc_html(dm_asm_money($o->get_total())) . '</td>';
        echo '<td><a class="button button-primary" href="' . esc_url($o->get_edit_order_url()) . '">Собрать</a></td></tr>';
    }
    echo '</tbody></table>';
}

function dm_asm_page() {
    echo '<div class="wrap"><h1>Сборка заказов</h1>';
    echo '<h2>Ждут взвешивания</h2>';
    dm_asm_table(wc_get_orders(array('status' => array('on-hold'), 'limit' => 50, 'orderby' => 'date', 'order' => 'ASC')), 'Новых заказов нет.');
    echo '<h2 style="margin-top:28px">Ждут оплаты</h2>';
    dm_asm_table(wc_get_orders(array('status' => array('dm-await-pay'), 'limit' => 50, 'orderby' => 'date', 'order' => 'ASC')), 'Нет заказов, ожидающих оплаты.');
    echo '</div>';
}


/* ---------- Напоминание: заказ ждёт оплаты больше суток ---------- */

add_action('init', function () {
    if (!wp_next_scheduled('dm_asm_remind')) wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'dm_asm_remind');
});

add_action('dm_asm_remind', function () {
    if (!function_exists('wc_get_orders')) return;
    $late = array();
    foreach (wc_get_orders(array('status' => array('dm-await-pay'), 'limit' => 50)) as $o) {
        $ts = (int) $o->get_meta('_dm_sent_ts');
        if (!$ts || time() - $ts < DAY_IN_SECONDS || $o->get_meta('_dm_remind_sent')) continue;
        $late[] = $o;
    }
    if (!$late) return;
    $to = get_option('woocommerce_new_order_settings');
    $to = (is_array($to) && !empty($to['recipient'])) ? $to['recipient'] : get_option('admin_email');
    $lines = array();
    foreach ($late as $o) {
        $lines[] = '№' . $o->get_order_number() . ' — ' . $o->get_billing_first_name() . ', ' . $o->get_billing_phone() . ', ' . dm_asm_money($o->get_total());
        $o->update_meta_data('_dm_remind_sent', 1);
        $o->save();
    }
    wp_mail($to, 'Заказы ждут оплаты больше суток', "Клиенты пока не оплатили:\n\n" . implode("\n", $lines) . "\n\nМожно позвонить или напомнить в мессенджере: раздел «Сборка заказов» в админке.");
});


/* ---------- Письмо «Заказ оплачен» (вместо стандартного) ---------- */

add_filter('woocommerce_email_enabled_customer_processing_order', '__return_false');

function dm_asm_mail_paid($order, $receipt_only = false) {
    $to = $order->get_billing_email();
    if (!$to) return false;
    $mailer = WC()->mailer();
    $rurl = $order->get_meta('_dm_receipt_url');
    $rows = '';
    foreach ($order->get_items('line_item') as $item) {
        $rows .= '<li>' . esc_html($item->get_name()) . ' — ' . esc_html($item->get_meta('Фасовка')) . ' × ' . (int) $item->get_quantity() . ': ' . esc_html(dm_asm_money($item->get_total())) . '</li>';
    }
    $body = '<p>Здравствуйте, ' . esc_html($order->get_billing_first_name()) . '!</p>';
    if ($receipt_only) {
        $body .= '<p>Чек по вашему заказу №' . esc_html($order->get_order_number()) . ' на сумму <strong>' . esc_html(dm_asm_money($order->get_total())) . '</strong>:</p>';
    } else {
        $body .= '<p>Мы получили оплату по заказу №' . esc_html($order->get_order_number()) . ' на сумму <strong>' . esc_html(dm_asm_money($order->get_total())) . '</strong>. Спасибо! Заказ оплачен, передаём его в отправку.</p><ul>' . $rows . '</ul>';
    }
    if ($rurl) {
        $body .= '<p><a href="' . esc_url($rurl) . '" style="display:inline-block;background:#e8a317;color:#222;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:bold">Открыть чек</a></p><p>Если кнопка не открывается, скопируйте ссылку: ' . esc_html($rurl) . '</p>';
    } elseif (!$receipt_only) {
        $body .= '<p>Чек об оплате пришлём отдельным письмом или он придёт от банка.</p>';
    }
    $body .= '<p>Вопросы: +7 913 995-97-80, +7 913 698-09-63.</p>';
    return $mailer->send($to, ($receipt_only ? 'Чек по заказу №' : 'Заказ оплачен №') . $order->get_order_number(), $mailer->wrap_message($receipt_only ? 'Ваш чек' : 'Заказ оплачен', $body));
}

add_action('woocommerce_order_status_processing', function ($order_id) {
    $order = wc_get_order($order_id);
    if (!$order || $order->get_meta('_dm_paid_mail')) return;
    $ok = dm_asm_mail_paid($order, false);
    $order->update_meta_data('_dm_paid_mail', 1);
    $order->add_order_note($ok ? 'Клиенту отправлено письмо «Заказ оплачен» на ' . $order->get_billing_email() : 'ПИСЬМО «ЗАКАЗ ОПЛАЧЕН» НЕ УШЛО на ' . $order->get_billing_email());
    $order->save();
});
