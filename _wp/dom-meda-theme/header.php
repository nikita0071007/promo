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
<div class="ic"><a href="<?php echo esc_url(dm_url('catalog','','')); ?>" aria-label="Поиск по каталогу" id="sbtn"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg></a><a href="<?php echo esc_url(dm_account_url()); ?>" aria-label="Личный кабинет"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-5 15-5 16 0"/></svg></a><a href="<?php echo esc_url(dm_url('cart','','')); ?>" class="cartb" aria-label="Корзина"><svg viewBox="0 0 24 24"><path d="M3 4h3l2.4 11h10l2-8H7"/><circle cx="10" cy="19.5" r="1.3"/><circle cx="17" cy="19.5" r="1.3"/></svg><b id="cn"><?php echo function_exists('WC') && WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0; ?></b></a><button class="burger" id="bg" aria-label="Меню" aria-expanded="false"><svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button></div>
</div>
<div class="wrap hs" id="hs" hidden><?php echo dm_search_form('dm-search'); ?></div></header>
<?php if (!is_front_page()) : ?><div class="wrap backrow"><a class="back" href="<?php echo esc_url(home_url('/')); ?>" onclick="if(document.referrer&&document.referrer.indexOf(location.host)>-1&&history.length>1){history.back();return false}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>Назад</a></div><?php endif; ?>
<main>
