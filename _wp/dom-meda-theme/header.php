<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="top"><div class="wrap nav">
<a class="logo" href="<?php echo esc_url(dm_url('home','','')); ?>"><img class="hex" src="<?php echo esc_url(get_theme_file_uri('img/logo-sm.png')); ?>" alt="" width="52" height="44"><span>Дом Мёда<small>Натуральные продукты Алтая</small></span></a>
<nav class="menu" id="menu"><a href="<?php echo esc_url(dm_url('catalog','','')); ?>">Каталог</a><a href="<?php echo esc_url(dm_url('about','','')); ?>">О нас</a><a href="<?php echo esc_url(dm_url('documents','','')); ?>">Документы</a><a href="<?php echo esc_url(dm_url('reviews','','')); ?>">Отзывы</a><a href="<?php echo esc_url(dm_url('payment','','')); ?>">Оплата</a><a href="<?php echo esc_url(dm_url('delivery','','')); ?>">Доставка</a><a href="<?php echo esc_url(dm_url('contacts','','')); ?>">Контакты</a></nav>
<div class="ic"><a href="<?php echo esc_url(dm_url('catalog','','')); ?>" aria-label="Поиск по каталогу" id="sbtn"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg></a><a href="<?php echo esc_url(dm_account_url()); ?>" aria-label="Личный кабинет"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-5 15-5 16 0"/></svg></a><button class="phb" id="pbtn" type="button" aria-label="Позвонить или написать" aria-expanded="false" aria-controls="pm"><svg viewBox="0 0 24 24"><path d="M5 4h3.5l2 5-2.4 1.5a11 11 0 005 5L14.6 13l5 2v3.5a2 2 0 01-2 2A15 15 0 013 6a2 2 0 012-2z"/></svg></button><a href="<?php echo esc_url(dm_url('cart','','')); ?>" class="cartb" aria-label="Корзина"><svg viewBox="0 0 24 24"><path d="M3 4h3l2.4 11h10l2-8H7"/><circle cx="10" cy="19.5" r="1.3"/><circle cx="17" cy="19.5" r="1.3"/></svg><b id="cn"><?php echo function_exists('WC') && WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0; ?></b></a><button class="burger" id="bg" aria-label="Меню" aria-expanded="false"><svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button></div>
</div>
<div class="wrap pmw"><div class="pm" id="pm" hidden>
<div class="pmt">Позвонить</div>
<a class="pmr" href="tel:+79139959780"><i class="pi" style="background:#E8A21F"><svg viewBox="0 0 24 24"><path d="M5 4h3.5l2 5-2.4 1.5a11 11 0 005 5L14.6 13l5 2v3.5a2 2 0 01-2 2A15 15 0 013 6a2 2 0 012-2z"/></svg></i><span>+7 913 995-97-80</span></a>
<a class="pmr" href="tel:+79136980963"><i class="pi" style="background:#E8A21F"><svg viewBox="0 0 24 24"><path d="M5 4h3.5l2 5-2.4 1.5a11 11 0 005 5L14.6 13l5 2v3.5a2 2 0 01-2 2A15 15 0 013 6a2 2 0 012-2z"/></svg></i><span>+7 913 698-09-63</span></a>
<div class="pmt">Написать</div>
<a class="pmr" href="https://wa.me/79139959780" target="_blank" rel="noopener"><i class="pi" style="background:#25D366">W</i><span>WhatsApp</span></a>
<a class="pmr" href="https://t.me/D_Meda" target="_blank" rel="noopener"><i class="pi" style="background:#2AABEE">T</i><span>Telegram</span></a>
<a class="pmr" href="https://max.ru/u/f9LHodD0cOKHADhw9PlIaBZ7OcauWM72iyn0SKltly_5SMdbPXyii96VhCA" target="_blank" rel="noopener"><i class="pi" style="background:#6c4cf0">M</i><span>MAX</span></a>
</div></div>
<div class="wrap hs" id="hs" hidden><?php echo dm_search_form('dm-search'); ?></div></header>
<?php if (!is_front_page()) : ?><div class="wrap backrow"><a class="back" href="<?php echo esc_url(home_url('/')); ?>" onclick="if(document.referrer&&document.referrer.indexOf(location.host)>-1&&history.length>1){history.back();return false}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>Назад</a></div><?php endif; ?>
<main>
