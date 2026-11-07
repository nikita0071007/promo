<?php get_header(); ?>
<section class="inner1"><div class="wrap">
<div class="sh"><h2><?php echo is_home() ? 'Новости' : esc_html(get_the_archive_title()); ?></h2></div>
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
<article class="dm-post"><h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3><div><?php the_excerpt(); ?></div></article>
<?php endwhile; the_posts_pagination(); endif; ?>
</div></section>
<?php get_footer(); ?>
