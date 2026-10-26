<?php
/**
 * Page complète d'un bien. Surchargeable : <thème>/homystay/single-bien.php
 * Pour ne changer que le contenu (et garder l'en-tête / pied du thème), surchargez plutôt single-bien-contenu.php.
 */
defined( 'ABSPATH' ) || exit;

HS_Single::header();

while ( have_posts() ) :
	the_post();
	echo HS_Templates::render( 'single-bien-contenu.php', HS_Single::data( get_the_ID() ) ); // phpcs:ignore WordPress.Security.EscapeOutput
endwhile;

HS_Single::footer();
