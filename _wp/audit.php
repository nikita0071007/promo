<?php
// Проверка сайта изнутри сервера: страницы, ссылки, картинки, карточки товаров, путь покупки.
$base = 'http://127.0.0.1'; $host = 'test.dom-meda.com';
function req($path, $method = 'GET', $body = '', &$ck = null) {
    global $base, $host;
    $h = "Host: $host\r\nX-Forwarded-Proto: https\r\n";
    if ($ck) { $h .= 'Cookie: ' . implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($ck), $ck)) . "\r\n"; }
    if ($method === 'POST') $h .= "Content-Type: application/x-www-form-urlencoded\r\n";
    $ctx = stream_context_create(['http' => ['timeout' => 40, 'ignore_errors' => true, 'method' => $method, 'follow_location' => 0, 'header' => $h, 'content' => $body]]);
    $t = microtime(true);
    $b = @file_get_contents($base . $path, false, $ctx);
    $hd = $http_response_header ?? [];
    $st = $hd ? (int) substr($hd[0], 9, 3) : 0;
    if ($ck !== null) foreach ($hd as $l) if (stripos($l, 'Set-Cookie:') === 0) { $c = explode(';', trim(substr($l, 11)))[0]; [$k, $v] = array_pad(explode('=', $c, 2), 2, ''); $ck[$k] = $v; }
    return ['st' => $st, 'body' => (string) $b, 'ms' => round((microtime(true) - $t) * 1000), 'hd' => $hd];
}
function dom($html) { $d = new DOMDocument(); libxml_use_internal_errors(true); $d->loadHTML('<?xml encoding="utf-8">' . $html); return new DOMXPath($d); }
function path($u) { $p = parse_url($u); if (isset($p['host']) && $p['host'] !== 'test.dom-meda.com') return null; return ($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : ''); }
function imgcheck($src) { $p = path($src); if (!$p) return 'внешняя'; $r = req($p, 'HEAD'); $len = 0; foreach ($r['hd'] as $l) if (stripos($l, 'Content-Length:') === 0) $len = (int) trim(substr($l, 15)); return [$r['st'], round($len / 1024)]; }
$bad = []; $ok = 0;

// 1. Страницы
$pages = ['/', '/shop/', '/product-category/k-med/', '/product-category/k-tea/', '/product-category/k-perga/', '/product-category/k-gift/', '/oplata/', '/dostavka/', '/dokumenty/', '/otzyvy/', '/kontakty/', '/oferta/', '/politika/', '/basket/', '/checkout/'];
$links = []; $imgs = [];
foreach ($pages as $u) {
    $r = req($u);
    if (in_array($r['st'], [200, 302]) && $r['ms'] < 3000) $ok++; else $bad[] = "Страница $u: код {$r['st']}, {$r['ms']} мс";
    if (preg_match('/\[(vc_|woocommerce_)/', $r['body'])) $bad[] = "Страница $u: в тексте остались коды [vc_…] или [woocommerce_…]";
    $x = dom($r['body']);
    foreach ($x->query('//header//a|//main//a|//footer//a') as $a) { $h = $a->getAttribute('href'); if ($h && $h[0] !== '#' && strpos($h, 'tel:') !== 0 && strpos($h, 'mailto:') !== 0) $links[$h] = $u; }
    foreach ($x->query('//header//img|//main//img|//footer//img') as $i) { $s = $i->getAttribute('src'); if ($s) $imgs[$s] = $u; }
}
echo "СТРАНИЦЫ: проверено " . count($pages) . ", в порядке $ok\n";

// 2. Ссылки
$nl = 0;
foreach ($links as $h => $from) {
    $p = path($h); if (!$p || strpos($p, '/wp-') === 0) continue;
    $nl++; $r = req(strtok($p, '#'));
    if ($r['st'] >= 400 || $r['st'] === 0) $bad[] = "Ссылка $h (со страницы $from): код {$r['st']}";
}
echo "ССЫЛКИ: проверено $nl\n";

// 3. Картинки на страницах
$ni = 0;
foreach ($imgs as $s => $from) {
    $c = imgcheck($s); if ($c === 'внешняя') continue; $ni++;
    if (strpos($s, 'placeholder') !== false) $bad[] = "Нет фото (серая заглушка) на странице $from";
    elseif ($c[0] !== 200) $bad[] = "Картинка не грузится: " . basename($s) . " (код {$c[0]}) на $from";
    elseif ($c[1] > 400) $bad[] = "Тяжёлая картинка: " . basename($s) . " {$c[1]} КБ на $from";
}
echo "КАРТИНКИ: проверено $ni\n";

// 4. Карточки товаров
$shop = req('/shop/'); $x = dom($shop['body']); $prods = [];
foreach ($x->query('//li[contains(@class,"product")]') as $li) {
    $a = $li->getElementsByTagName('a')->item(0); $t = trim($li->textContent); $prods[] = [$a->getAttribute('href'), preg_replace('/\s+/', ' ', substr($t, 0, 40))];
}
$np = 0; $withPhoto = 0; $withForm = 0; $noDesc = [];
foreach ($prods as [$h, $title]) {
    $p = path($h); $r = req($p); $np++;
    if ($r['st'] !== 200) { $bad[] = "Товар «{$title}»: код {$r['st']}"; continue; }
    $xp = dom($r['body']);
    $im = $xp->query('//div[contains(@class,"images")]//img');
    $src = $im->length ? $im->item(0)->getAttribute('src') : '';
    if (!$src || strpos($src, 'placeholder') !== false) $bad[] = "Товар «{$title}»: нет фото"; else { $withPhoto++; $c = imgcheck($src); if ($c[0] !== 200) $bad[] = "Товар «{$title}»: фото не грузится (код {$c[0]})"; }
    $forms = $xp->query('//form[contains(@class,"dm-form")]')->length; $btn = $xp->query('//button[contains(@class,"single_add_to_cart_button")]')->length;
    if ($forms) $withForm++; elseif (!$btn) $bad[] = "Товар «{$title}»: нет кнопки «В корзину»";
    if (!$xp->query('//*[contains(@class,"woocommerce-product-details__short-description")]|//*[@id="tab-description"]')->length) $noDesc[] = $title;
}
echo "ТОВАРЫ: $np, с фото $withPhoto, с выбором фасовки $withForm, без описания " . count($noDesc) . "\n";

// 5. Путь покупки: в корзину, корзина, оформление
$ck = [];
$m = null; foreach ($prods as [$h, $title]) if (strpos($title, 'Альпийское') !== false) $m = $h;
if ($m) {
    $pid = null; $pg = req(path($m)); if (preg_match('/name="add-to-cart" value="(\d+)"/', $pg['body'], $mm)) $pid = $mm[1];
    $r = req(path($m), 'POST', "add-to-cart=$pid&dm_size=2&quantity=2", $ck);
    $c = req('/basket/', 'GET', '', $ck);
    $cart = strip_tags($c['body']);
    echo "КОРЗИНА: добавлено 2 шт. банки 0,5 л: " . (strpos($cart, '0,5 л') !== false ? 'фасовка видна' : 'ФАСОВКИ НЕТ') . ', ' . (preg_match('/1\s?230|1.230/u', $cart) ? 'сумма 1 230 ₽ верна' : 'сумма 1 230 ₽ не найдена') . "\n";
    if (strpos($cart, '0,5 л') === false) $bad[] = 'Корзина: не видна выбранная фасовка';
    $o = req('/checkout/', 'GET', '', $ck); $xo = dom($o['body']);
    $need = ['billing_first_name' => 'ФИО', 'billing_phone' => 'телефон', 'billing_email' => 'почта', 'billing_address_1' => 'адрес'];
    foreach ($need as $id => $name) if (!$xo->query('//*[@id="' . $id . '"]')->length) $bad[] = "Оформление: нет поля «$name»";
    if (!$xo->query('//input[@value="dm_link"]')->length) $bad[] = 'Оформление: нет способа оплаты «по ссылке»';
    if (!$xo->query('//*[@id="place_order"]')->length) $bad[] = 'Оформление: нет кнопки «Подтвердить заказ»';
    echo "ОФОРМЛЕНИЕ: " . ($o['st'] === 200 ? 'страница открывается' : 'код ' . $o['st']) . "\n";
} else $bad[] = 'Не нашёл мёд «Альпийское разнотравье» для проверки покупки';

echo "\nЗАМЕЧАНИЯ (" . count($bad) . "):\n";
foreach (array_unique($bad) as $b) echo " - $b\n";
echo "AUDIT_DONE\n";
