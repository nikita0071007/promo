<?php get_header(); ?>
<section class="inner1"><div class="wrap">
<?php if (is_singular('product')) : ?>
<div class="backrow" style="padding:0 0 14px"><a class="back" href="<?php echo esc_url(dm_shop_url()); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>Назад в каталог</a></div>
<?php else : ?>
<div class="sh"><h2><?php echo is_search() ? 'Поиск: «' . esc_html(get_search_query()) . '»' : (is_product_category() ? esc_html(single_term_title('', false)) : 'Каталог'); ?></h2></div>
<?php echo dm_search_form(); ?>
<?php dm_chips(); ?>
<?php endif; ?>
<?php woocommerce_content(); ?>
</div></section>
<?php if (is_singular('product')) { get_template_part('price-note'); } ?>
<?php get_footer(); ?>
