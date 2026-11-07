<?php get_header(); ?>
<section class="inner1"><div class="wrap doc">
<?php while (have_posts()) : the_post(); ?><h2><?php the_title(); ?></h2><div class="dm-content"><?php the_content(); ?></div><?php endwhile; ?>
</div></section>
<?php get_footer(); ?>
