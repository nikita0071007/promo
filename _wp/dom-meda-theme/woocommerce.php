<?php get_header(); ?>
<section class="inner1"><div class="wrap">
<?php if (is_singular('product')) : ?>
<a class="back" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">← Назад в каталог</a>
<?php else : ?>
<div class="sh"><h2><?php echo is_product_category() ? esc_html(single_term_title('', false)) : 'Каталог'; ?></h2></div>
<?php dm_chips(); ?>
<?php endif; ?>
<?php woocommerce_content(); ?>
</div></section>
<?php if (is_singular('product')) { get_template_part('price-note'); } ?>
<?php get_footer(); ?>
