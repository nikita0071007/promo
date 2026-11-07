<?php get_header(); ?>
<section class="inner1"><div class="wrap">
<div class="sh"><h2><?php the_title(); ?></h2></div>
<?php while (have_posts()) : the_post(); ?><div class="dm-content"><?php the_content(); ?></div><?php endwhile; ?>
</div></section>
<?php get_footer(); ?>
