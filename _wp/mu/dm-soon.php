<?php
/**
 * Plugin Name: Дом мёда — «Скоро открытие»
 * Description: Показывает посетителям заглушку, пока в папке wp-content лежит файл dm-soon.on. Администраторы видят сайт как обычно.
 * Включить:  touch wp-content/dm-soon.on     Выключить (открыть сайт):  rm wp-content/dm-soon.on
 */
if (!defined('ABSPATH')) exit;

add_action('template_redirect', function () {
    if (!file_exists(WP_CONTENT_DIR . '/dm-soon.on')) return;
    if (is_user_logged_in() && current_user_can('edit_posts')) return;

    $img = esc_url(get_template_directory_uri() . '/img/');
    status_header(503);
    header('Retry-After: 3600');
    header('X-Robots-Tag: noindex, nofollow');
    nocache_headers();
    ?><!doctype html>
<html lang="ru"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Дом мёда — скоро открытие</title>
<style>
:root{--paper:#FFFAF0;--forest:#203B2B;--muted:#6B766A;--gold:#F3B93E;--line:#E7DCC7}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;color:var(--forest);font-family:Arial,sans-serif;
background:linear-gradient(rgba(248,242,230,.88),rgba(248,242,230,.94)),url(<?php echo $img; ?>hero.jpg) center/cover}
.card{background:var(--paper);border:1px solid var(--line);border-radius:28px;padding:48px 56px;max-width:640px;text-align:center;box-shadow:0 20px 60px rgba(32,59,43,.12)}
.card img{display:block;height:96px;margin:0 auto 8px}
.tag{display:inline-block;background:var(--gold);color:var(--forest);font-weight:700;border-radius:99px;padding:8px 20px;margin:14px 0 20px;font-size:15px;letter-spacing:.04em}
h1{font-family:Georgia,serif;font-size:46px;margin:0 0 14px;line-height:1.1}
p{font-size:18px;line-height:1.5;color:#3d4d41;margin:0 0 10px}
.m{color:var(--muted);font-size:15px;margin-top:22px;padding-top:18px;border-top:1px solid var(--line)}
@media(max-width:520px){.card{padding:32px 22px}h1{font-size:32px}}
</style></head><body>
<div class="card"><img src="<?php echo $img; ?>logo-sm.png" alt="Дом мёда">
<div class="tag">СКОРО ОТКРЫТИЕ</div>
<h1>Обновляем сайт «Дом мёда»</h1>
<p>Мы готовим новый магазин: алтайский мёд, перга, чаи и дары гор. Совсем скоро всё будет на месте.</p>
<p>А пока ждём вас в магазине: Усть-Кокса, ул. Мичурина, 1В.</p>
<div class="m">Спасибо, что вы с нами.</div></div>
</body></html>
<?php
    exit;
}, 0);
