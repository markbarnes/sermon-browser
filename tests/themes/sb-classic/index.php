<?php
/**
 * Test site only: the one template of a minimal classic theme (see style.css).
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<?php if ( ! current_theme_supports( 'title-tag' ) ) : ?>
<title><?php bloginfo( 'name' ); ?><?php wp_title(); ?></title>
<?php endif; ?>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<main>
<?php
while ( have_posts() ) {
	the_post();
	?>
	<article>
		<h1><?php the_title(); ?></h1>
		<?php the_content(); ?>
	</article>
	<?php
}
?>
</main>
<aside id="sidebar"><?php dynamic_sidebar( 'sb-tests' ); ?></aside>
<?php wp_footer(); ?>
</body>
</html>
