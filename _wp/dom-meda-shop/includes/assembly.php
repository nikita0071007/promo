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

/* Стоимость мёда по факту — без тары. Тара идёт в заказ отдельной строкой. */
function dm_asm_exact($line, $w) {
    return round($line['kg'] * $w / 1000);
}

/* Вес: принимаем «518», «0,518», «0.518», «1,2». Меньше 20 — считаем килограммами (банка не весит меньше 20 г). */
function dm_asm_w($raw) {
    $w = (float) str_replace(',', '.', trim((string) $raw));
    if ($w > 0 && $w < 20) $w *= 1000;
    return round($w);
}

/* Тара по строке: то, что вписали в панели (можно 0), иначе по прайсу. Сумма за всю строку (с учётом количества). */
function dm_asm_line_tare($l) {
    $v = $l['item']->get_meta('_dm_tare');
    return ($v === '' || $v === null || $v === false) ? $l['tare'] * $l['qty'] : max(0, (float) $v);
}

function dm_asm_tares() {
    return array(
        'small' => array('Тара 0,25–0,33 л', 45),
        'mid'   => array('Тара 0,5 л', 55),
        'big'   => array('Тара 0,75–1,5 л', 75),
    );
}

function dm_asm_fee_rows($order) {
    $r = '';
    foreach ($order->get_items('fee') as $f) $r .= '<li>' . esc_html($f->get_name()) . ': ' . esc_html(dm_asm_money($f->get_total())) . '</li>';
    return $r;
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
    $pickup = dm_is_pickup($order);
    $phone = $order->get_billing_phone();
    $note = $order->get_customer_note();
    echo '<div style="background:' . ($pickup ? '#e7f4ea' : '#fff4dc') . ';border:1px solid #d8cfae;border-radius:8px;padding:10px 14px;margin:0 0 12px;line-height:1.7">';
    echo '<strong style="font-size:15px">' . ($pickup ? '🏠 САМОВЫВОЗ' : '📦 ДОСТАВКА') . '</strong> · ' . esc_html($order->get_billing_first_name());
    if ($phone) echo ' · <a href="tel:' . esc_attr(preg_replace('/[^\d+]/', '', $phone)) . '">' . esc_html($phone) . '</a>';
    if ($order->get_billing_email()) echo ' · ' . esc_html($order->get_billing_email());
    echo '<br>' . ($pickup ? 'Заберёт сам: ' . esc_html(DM_PICKUP_ADDR) : 'Адрес: ' . esc_html($order->get_billing_address_1()));
    if ($note) echo '<br><strong>Комментарий клиента:</strong> ' . esc_html($note);
    echo '<br><button type="button" class="button" id="dm-print" style="margin-top:6px">🖨 Распечатать лист сборки</button></div>';
    echo '<style>.dm-asm td,.dm-asm th{padding:6px 8px;text-align:left}.dm-asm td{vertical-align:middle}.dm-asm input.dm-w{width:130px;font-size:18px;padding:8px 10px;text-align:right}.dm-asm .dm-hint{display:block;font-size:12px;color:#7a6a3a;margin-top:3px;min-height:15px}.dm-asm .dm-sum{font-size:17px;font-weight:700;color:#1f5a35;white-space:nowrap}.dm-asm tbody tr{background:#fffaf0}.dm-asm .tot{font-size:15px;margin:10px 0}</style>';
    if (!$lines) {
        echo '<p>В заказе нет товаров на развес: взвешивать нечего. Ссылку на оплату можно отправить сразу.</p>';
    } else {
        echo '<p><strong>Впишите вес по факту</strong>: можно в граммах (518) или в килограммах (0,518), программа поймёт сама. Вес общий для всех банок одной позиции. Мёд считается по весу (цена за кг × вес), а тара добавляется в заказ отдельной строкой.</p>';
        echo '<table class="dm-asm widefat"><thead><tr><th>Товар</th><th>Фасовка в заказе</th><th>Кол-во</th><th>Вес по факту</th><th>Мёд, ₽</th><th>Тара, ₽</th></tr></thead><tbody>';
        foreach ($lines as $id => $l) {
            $w = $l['item']->get_meta('_dm_w');
            echo '<tr><td>' . esc_html($l['name']) . '</td><td>' . esc_html($l['label']) . '</td><td>' . (int) $l['qty'] . '</td>';
            echo '<td><input type="text" inputmode="decimal" autocomplete="off" class="dm-w" name="dm_w[' . (int) $id . ']" value="' . esc_attr($w) . '" placeholder="примерно ' . (int) $l['approx'] . ' г" data-kg="' . esc_attr($l['kg']) . '" ><span class="dm-hint"></span></td>';
            echo '<td class="dm-sum">' . esc_html(dm_asm_money($l['item']->get_total())) . '</td>';
            echo '<td><input type="number" min="0" step="1" class="dm-t" name="dm_t[' . (int) $id . ']" value="' . esc_attr((int) dm_asm_line_tare($l)) . '" style="width:80px;font-size:16px;padding:6px 8px;text-align:right"></td></tr>';
        }
        echo '</tbody></table>';
    }
    $url = $order->get_meta('_dm_pay_url');
    echo '<p style="margin-top:14px"><label><strong>Ссылка на оплату</strong> (из банка, на точную сумму):<br><input type="url" name="dm_pay_url" value="' . esc_attr($url) . '" style="width:100%" placeholder="https://..."></label></p>';
    $others = (float) $order->get_total();
    foreach ($lines as $l) $others -= (float) $l['item']->get_total();
    $manual = '';
    foreach ($order->get_items('fee') as $fid => $f) {
        $others -= (float) $f->get_total();
        if ($f->get_meta('_dm_auto_tare')) continue;
        $manual .= '<label style="display:block"><input type="checkbox" class="dm-mf" name="dm_rm_fee[]" data-amt="' . esc_attr((float) $f->get_total()) . '" value="' . (int) $fid . '"> убрать: ' . esc_html($f->get_name()) . ' — ' . esc_html(dm_asm_money($f->get_total())) . '</label>';
    }
    $num = (int) round($order->get_total());
    echo '<p class="tot">Итого сейчас: <strong id="dm-tot">' . esc_html(dm_asm_money($order->get_total())) . '</strong> '
        . '<button type="button" class="button" id="dm-copy-sum" data-v="' . $num . '">Скопировать сумму для банка: <span id="dm-sum-num">' . $num . '</span></button></p>';
    if ($manual) echo '<div style="background:#fff4dc;border:1px solid #d8cfae;border-radius:8px;padding:8px 12px;margin:6px 0"><strong>Добавлено вручную (входит в сумму):</strong>' . $manual . '<span class="description">Поставьте галочку и нажмите «Сохранить вес и пересчитать», чтобы убрать строку.</span></div>';
    $first = $order->get_billing_first_name();
    echo '<p><label><strong>Готовое сообщение клиенту</strong> (для мессенджера или СМС; ссылка подставится сама):<br>'
        . '<textarea id="dm-msg" rows="5" style="width:100%" readonly data-name="' . esc_attr($first) . '" data-num="' . esc_attr($order->get_order_number()) . '"></textarea></label><br>'
        . '<button type="button" class="button" id="dm-copy-msg">Скопировать сообщение</button></p>';
    echo '<hr><p><strong>Добавить тару отдельной позицией</strong> (если нужна лишняя банка или другая тара):<br>';
    echo '<select name="dm_tare" style="min-width:230px">';
    foreach (dm_asm_tares() as $k => $t) echo '<option value="' . esc_attr($k) . '">' . esc_html($t[0] . ' — ' . $t[1] . ' ₽') . '</option>';
    echo '</select> × <input type="number" name="dm_tare_qty" value="1" min="1" max="50" style="width:60px"> ';
    echo '<button type="submit" name="dm_do" value="add_tare" class="button">Добавить тару в заказ</button></p>';
    echo '<p><a class="button button-primary" href="https://sbi.sberbank.ru:9443/ic/ufs/login.html" target="_blank" rel="noopener noreferrer" style="background:#21a038;border-color:#21a038">Открыть СберБизнес ↗</a> <span class="description">откроется в новой вкладке, сюда вернётесь после создания ссылки</span></p>';
    echo '<details style="margin:12px 0;background:#f6f9f6;border:1px solid #cfe0d2;border-radius:8px;padding:10px 14px"><summary style="cursor:pointer;font-weight:600">Как выставить клиенту ссылку на оплату (пошагово)</summary><ol style="margin:10px 0 0 18px;line-height:1.7">'
        . '<li>Впишите вес по факту в таблице выше и нажмите «Сохранить вес и пересчитать». Проверьте строку «Итого».</li>'
        . '<li>Нажмите «Скопировать сумму для банка»: сумма скопируется без пробелов и знаков.</li>'
        . '<li>Нажмите зелёную кнопку «Открыть СберБизнес» (или откройте приложение) и найдите раздел приёма платежей: платёжная ссылка или QR СБП на сумму.</li>'
        . '<li>Создайте ссылку на эту сумму, в назначении напишите «Заказ №' . esc_html($order->get_order_number()) . '», скопируйте готовую ссылку.</li>'
        . '<li>Вернитесь сюда, вставьте ссылку в поле «Ссылка на оплату» и нажмите «Пересчитать и отправить ссылку клиенту». Клиент получит письмо с кнопкой, оплата идёт через СБП, деньги приходят на счёт ИП.</li>'
        . '<li>Когда деньги пришли на счёт, поставьте статус заказа «Оплачен». Клиент получит письмо об оплате. Чек отправьте кнопкой ниже.</li>'
        . '</ol></details>';
    echo '<p><button type="submit" name="dm_do" value="recalc" class="button">Сохранить вес и пересчитать</button> ';
    echo '<button type="submit" name="dm_do" value="send" class="button button-primary">Пересчитать и отправить ссылку клиенту</button></p>';
    $rurl = $order->get_meta('_dm_receipt_url');
    echo '<hr><p><label><strong>Чек об оплате</strong> (ссылка на чек из кассы или банка):<br><input type="url" name="dm_receipt_url" value="' . esc_attr($rurl) . '" style="width:100%" placeholder="https://..."></label></p>';
    echo '<p><button type="submit" name="dm_do" value="receipt" class="button">Отправить клиенту чек</button> <span class="description">Письмо «Заказ оплачен» уходит само, когда Вы ставите статус «Оплачен». Чек отправляйте этой кнопкой, когда получите ссылку.</span></p>';
    echo '<p class="description">После отправки статус станет «Ждёт оплаты». Когда деньги поступили, поставьте статус «Оплачен», а при отправке посылки — «Отправлен».</p>';
    $info = array(
        'num' => $order->get_order_number(),
        'who' => trim($order->get_billing_first_name() . ' ' . $phone),
        'way' => $pickup ? 'САМОВЫВОЗ: ' . DM_PICKUP_ADDR : 'ДОСТАВКА: ' . $order->get_billing_address_1(),
        'note' => (string) $note,
        'pickup' => $pickup ? 1 : 0,
        'items' => array(),
    );
    foreach ($lines as $id => $l) $info['items'][] = array('id' => (int) $id, 'name' => $l['name'], 'label' => $l['label'], 'qty' => $l['qty'], 'approx' => (int) $l['approx']);
    echo '<script>(function(){
var info=' . wp_json_encode($info, JSON_UNESCAPED_UNICODE) . ';
var others=' . json_encode(round($others, 2)) . ';
var rows=document.querySelectorAll(".dm-w");
function f(n){return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g," ")+" ₽"}
var tot=document.getElementById("dm-tot"),numEl=document.getElementById("dm-sum-num"),btn=document.getElementById("dm-copy-sum");
var msg=document.getElementById("dm-msg"),url=document.querySelector("input[name=dm_pay_url]");
function pw(v){var w=parseFloat(String(v).replace(",","."));if(w>0&&w<20)w=w*1000;return Math.round(w)||0}
function calc(){var t=others;document.querySelectorAll(".dm-mf").forEach(function(c){if(!c.checked)t+=parseFloat(c.dataset.amt)||0});rows.forEach(function(i){var w=pw(i.value);var tr=i.closest("tr");var te=tr.querySelector(".dm-t");var tv=Math.max(0,parseFloat(te.value)||0);var h=i.parentNode.querySelector(".dm-hint");h.textContent=w>0?("= "+w+" г"):"";var s=tr.querySelector(".dm-sum");if(w>0){var v=Math.round(parseFloat(i.dataset.kg)*w/1000);s.textContent=f(v);t+=v+tv}else{t+=(parseFloat((s.textContent||"0").replace(/[^\d]/g,""))||0)+tv}});return Math.round(t)}
function upd(){var t=calc();tot.textContent=f(t);numEl.textContent=t;btn.dataset.v=t;
msg.value="Здравствуйте"+(msg.dataset.name?", "+msg.dataset.name:"")+"! Ваш заказ №"+msg.dataset.num+" в «Доме мёда» собран и взвешен. Точная сумма: "+f(t)+"."+(info.pickup?"\nЗаказ заберёте сами: "+info.way.replace("САМОВЫВОЗ: ",""):"")+"\nОплатить можно по ссылке (карта МИР, Сбербанк или СБП): "+(url.value||"[вставьте ссылку выше]")+"\nСпасибо, что выбрали нас!"}
function cp(text,b){var old=b.textContent;function ok(){b.textContent="Скопировано";setTimeout(function(){b.textContent=old},1500)}if(navigator.clipboard){navigator.clipboard.writeText(text).then(ok)}else{var a=document.createElement("textarea");a.value=text;document.body.appendChild(a);a.select();document.execCommand("copy");a.remove();ok()}}
rows.forEach(function(i){i.addEventListener("input",upd)});document.querySelectorAll(".dm-t,.dm-mf").forEach(function(i){i.addEventListener("input",upd);i.addEventListener("change",upd)});document.querySelectorAll(".dm-w,.dm-t").forEach(function(i){i.addEventListener("keydown",function(e){if(e.key==="Enter")e.preventDefault()})});url.addEventListener("input",upd);
btn.addEventListener("click",function(){var v=btn.dataset.v;cp(v,btn);setTimeout(upd,1600)});
document.getElementById("dm-copy-msg").addEventListener("click",function(){cp(msg.value,this)});
function e(x){return String(x).replace(/[&<>]/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;"}[c]})}
document.getElementById("dm-print").addEventListener("click",function(){var r="";info.items.forEach(function(it){var inp=document.querySelector("input[name=\"dm_w["+it.id+"]\"]");r+="<tr><td>&#9744;</td><td>"+e(it.name)+"</td><td>"+e(it.label)+"</td><td>"+it.qty+"</td><td>"+(inp&&inp.value?e(inp.value):"примерно "+it.approx+" г")+"</td></tr>"});var w=window.open("","_blank");w.document.write("<meta charset=utf-8><title>Лист сборки</title><body style=font-family:sans-serif><h2>Заказ №"+e(info.num)+"</h2><p><b>"+e(info.way)+"</b></p><p>"+e(info.who)+"</p>"+(info.note?"<p><b>Комментарий клиента:</b> "+e(info.note)+"</p>":"")+"<table border=1 cellpadding=8 cellspacing=0 style=border-collapse:collapse;font-size:16px><tr><th></th><th>Товар</th><th>Фасовка</th><th>Кол-во</th><th>Вес</th></tr>"+r+"</table>");w.document.close();w.focus();w.print()});
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
    if ($do === 'add_tare') {
        $tares = dm_asm_tares();
        $k = isset($_POST['dm_tare']) ? sanitize_key($_POST['dm_tare']) : '';
        $q = isset($_POST['dm_tare_qty']) ? max(1, min(50, (int) $_POST['dm_tare_qty'])) : 1;
        if (isset($tares[$k])) {
            $fee = new WC_Order_Item_Fee();
            $fee->set_name($tares[$k][0] . ($q > 1 ? ' × ' . $q : ''));
            $fee->set_amount($tares[$k][1] * $q);
            $fee->set_total($tares[$k][1] * $q);
            $fee->set_tax_status('none');
            $order->add_item($fee);
            $order->calculate_totals(false);
            $order->add_order_note('Добавлена ' . mb_strtolower($tares[$k][0]) . ' × ' . $q . ' = ' . dm_asm_money($tares[$k][1] * $q) . '. Итого ' . dm_asm_money($order->get_total()));
        }
    }
    if ($do === 'recalc' || $do === 'send') {
        $ws = isset($_POST['dm_w']) && is_array($_POST['dm_w']) ? wp_unslash($_POST['dm_w']) : array();
        $changed = array();
        $rm = isset($_POST['dm_rm_fee']) && is_array($_POST['dm_rm_fee']) ? array_map('intval', $_POST['dm_rm_fee']) : array();
        $ts = isset($_POST['dm_t']) && is_array($_POST['dm_t']) ? wp_unslash($_POST['dm_t']) : array();
        foreach ($order->get_items('fee') as $fid => $f) { if ($f->get_meta('_dm_auto_tare') || in_array((int) $fid, $rm, true)) $order->remove_item($fid); }
        foreach (dm_asm_lines($order) as $id => $l) {
            if (isset($ts[$id]) && $ts[$id] !== '') { $l['item']->update_meta_data('_dm_tare', max(0, round((float) str_replace(',', '.', $ts[$id])))); $l['item']->save(); }
            $tt = dm_asm_line_tare($l);
            if ($tt > 0) {
                $fee = new WC_Order_Item_Fee();
                $fee->set_name('Тара ' . $l['label'] . ' (' . $l['name'] . ')' . ($l['qty'] > 1 ? ' × ' . $l['qty'] : ''));
                $fee->set_amount($tt);
                $fee->set_total($tt);
                $fee->set_tax_status('none');
                $fee->add_meta_data('_dm_auto_tare', 1, true);
                $order->add_item($fee);
            }
            $w = isset($ws[$id]) ? dm_asm_w($ws[$id]) : 0;
            if ($w <= 0) continue;
            $sum = dm_asm_exact($l, $w);
            $l['item']->update_meta_data('_dm_w', $w);
            $l['item']->update_meta_data('Вес по факту, г', $w);
            $l['item']->set_subtotal($sum);
            $l['item']->set_total($sum);
            $l['item']->save();
            $changed[] = $l['name'] . ': ' . (int) $w . ' г = ' . dm_asm_money($sum) . ($tt ? ' + тара ' . dm_asm_money($tt) : '');
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
            $wg = (int) $item->get_meta('_dm_w');
            $rows .= '<li>' . esc_html($item->get_name()) . ' — ' . esc_html($item->get_meta('Фасовка')) . ' × ' . (int) $item->get_quantity() . ($wg ? ', вес по факту ' . esc_html(number_format($wg, 0, '', ' ')) . ' г' : '') . ': ' . esc_html(dm_asm_money($item->get_total())) . '</li>';
        }
        $rows .= dm_asm_fee_rows($order);
        $body = '<p>Здравствуйте, ' . esc_html($order->get_billing_first_name()) . '!</p>'
            . '<p>Ваш заказ №' . esc_html($order->get_order_number()) . ' собран и взвешен. Точная сумма: <strong>' . esc_html(dm_asm_money($order->get_total())) . '</strong>.</p>'
            . '<ul>' . $rows . '</ul>'
            . (dm_is_pickup($order) ? '<p>Заказ Вы заберёте сами: ' . esc_html(DM_PICKUP_ADDR) . '. Часы работы: ' . esc_html(DM_HOURS) . '. Когда он будет готов, мы сообщим.</p>' : '')
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
    echo '<table class="widefat striped"><thead><tr><th>№</th><th>Дата</th><th>Клиент</th><th>Телефон</th><th>Получение</th><th>Состав</th><th>Сумма</th><th></th></tr></thead><tbody>';
    foreach ($orders as $o) {
        $names = array();
        foreach ($o->get_items('line_item') as $it) $names[] = $it->get_name() . ' (' . $it->get_meta('Фасовка') . ') × ' . $it->get_quantity();
        $ts = (int) $o->get_meta('_dm_sent_ts');
        $late = ($o->get_status() === 'dm-await-pay' && $ts && time() - $ts > DAY_IN_SECONDS);
        echo '<tr' . ($late ? ' style="background:#fff0d6"' : '') . '><td>' . esc_html($o->get_order_number()) . ($late ? ' <span title="Ждёт оплаты больше суток">⏰</span>' : '') . '</td><td>' . esc_html(wc_format_datetime($o->get_date_created(), 'd.m H:i')) . '</td><td>' . esc_html($o->get_billing_first_name()) . '</td><td>' . esc_html($o->get_billing_phone()) . '</td><td>' . (dm_is_pickup($o) ? '🏠 Самовывоз' : '📦 Доставка') . '</td><td>' . esc_html(implode('; ', $names)) . '</td><td>' . esc_html(dm_asm_money($o->get_total())) . '</td>';
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
        $wg = (int) $item->get_meta('_dm_w');
            $rows .= '<li>' . esc_html($item->get_name()) . ' — ' . esc_html($item->get_meta('Фасовка')) . ' × ' . (int) $item->get_quantity() . ($wg ? ', вес по факту ' . esc_html(number_format($wg, 0, '', ' ')) . ' г' : '') . ': ' . esc_html(dm_asm_money($item->get_total())) . '</li>';
    }
    $rows .= dm_asm_fee_rows($order);
    $body = '<p>Здравствуйте, ' . esc_html($order->get_billing_first_name()) . '!</p>';
    if ($receipt_only) {
        $body .= '<p>Чек по Вашему заказу №' . esc_html($order->get_order_number()) . ' на сумму <strong>' . esc_html(dm_asm_money($order->get_total())) . '</strong>:</p>';
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


/* ---------- Письмо «Заказ принят» (вместо стандартного «на удержании») ---------- */

add_filter('woocommerce_email_enabled_customer_on_hold_order', '__return_false');

function dm_asm_mail_new($order) {
    $to = $order->get_billing_email();
    if (!$to) return false;
    $mailer = WC()->mailer();
    $rows = '';
    foreach ($order->get_items('line_item') as $item) {
        $pack = $item->get_meta('Фасовка');
        $rows .= '<li>' . esc_html($item->get_name()) . ($pack ? ' — ' . esc_html($pack) : '') . ' × ' . (int) $item->get_quantity() . ': примерно ' . esc_html(dm_asm_money($item->get_total())) . '</li>';
    }
    $rows .= dm_asm_fee_rows($order);
    $name = trim((string) $order->get_billing_first_name());
    $body  = '<p>Здравствуйте' . ($name !== '' ? ', ' . esc_html($name) : '') . '!</p>';
    $body .= '<p>Благодарим Вас за заказ. Мы получили Ваш заказ №' . esc_html($order->get_order_number()) . ' и уже начали его собирать.</p>';
    $body .= '<ul>' . $rows . '</ul>';
    $body .= '<p>Ориентировочная сумма: <strong>' . esc_html(dm_asm_money($order->get_total())) . '</strong>.</p>';
    $body .= '<p><strong>Почему сумма ориентировочная.</strong> Мёд и мёд в сотах мы продаём по весу, а вес каждой банки и рамки немного отличается. Поэтому сначала мы взвесим Ваш заказ и посчитаем точную стоимость. Она может оказаться чуть больше или чуть меньше указанной.</p>';
    $body .= '<p><strong>Что будет дальше.</strong> После взвешивания мы пришлём Вам точную сумму для согласования и ссылку на оплату. Оплачивать сейчас ничего не нужно. Товар зарезервирован за Вами до оплаты.</p>';
    $body .= '<p>Статус заказа можно посмотреть в личном кабинете на сайте.</p>';
    $body .= '<p>Вопросы: +7 913 995-97-80, +7 913 698-09-63.</p>';
    return $mailer->send($to, 'Заказ №' . $order->get_order_number() . ' принят, взвешиваем', $mailer->wrap_message('Спасибо за Ваш заказ', $body));
}

add_action('woocommerce_order_status_on-hold', function ($order_id) {
    $order = wc_get_order($order_id);
    if (!$order || $order->get_meta('_dm_new_mail')) return;
    $ok = dm_asm_mail_new($order);
    $order->update_meta_data('_dm_new_mail', 1);
    $order->add_order_note($ok ? 'Клиенту отправлено письмо «Заказ принят» на ' . $order->get_billing_email() : 'ПИСЬМО «ЗАКАЗ ПРИНЯТ» НЕ УШЛО на ' . $order->get_billing_email());
    $order->save();
});
