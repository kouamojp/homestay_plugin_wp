<?php
/**
 * Projets d'investissement. Surchargeable : <thème>/homystay/liste-projets.php
 *
 * @var WP_Query $query
 * @var int      $colonnes
 */
defined( 'ABSPATH' ) || exit;

if ( ! $query->have_posts() ) {
	echo '<p class="hs-vide">Aucun projet ouvert pour le moment.</p>';
	return;
}
?>
<div class="hs-grille hs-grille--<?php echo (int) $colonnes; ?>">
	<?php
	while ( $query->have_posts() ) :
		$query->the_post();
		$id        = get_the_ID();
		$recherche = (int) HS_Fields::get( $id, 'montant_recherche' );
		$leve      = (int) HS_Fields::get( $id, 'montant_leve' );
		$pct       = $recherche ? min( 100, round( $leve / $recherche * 100 ) ) : 0;
		?>
		<article class="hs-carte hs-projet">
			<a class="hs-carte__media" href="<?php the_permalink(); ?>">
				<?php echo has_post_thumbnail() ? get_the_post_thumbnail( $id, 'medium_large', array( 'loading' => 'lazy' ) ) : '<span class="hs-carte__placeholder"></span>'; ?>
			</a>
			<div class="hs-carte__corps">
				<p class="hs-carte__lieu"><?php echo esc_html( HS_Fields::get( $id, 'ville' ) ); ?></p>
				<h3 class="hs-carte__titre"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
				<ul class="hs-carte__infos">
					<?php if ( HS_Fields::get( $id, 'rendement_estime' ) ) : ?><li>Rendement estimé <?php echo esc_html( number_format_i18n( (float) HS_Fields::get( $id, 'rendement_estime' ), 1 ) ); ?> %/an</li><?php endif; ?>
					<?php if ( HS_Fields::get( $id, 'duree_mois' ) ) : ?><li><?php echo (int) HS_Fields::get( $id, 'duree_mois' ); ?> mois</li><?php endif; ?>
					<?php if ( HS_Fields::get( $id, 'ticket_min' ) ) : ?><li>Dès <?php echo esc_html( HS_Templates::prix( HS_Fields::get( $id, 'ticket_min' ) ) ); ?></li><?php endif; ?>
				</ul>
				<?php if ( $recherche ) : ?>
					<div class="hs-progression" role="progressbar" aria-valuenow="<?php echo (int) $pct; ?>" aria-valuemin="0" aria-valuemax="100"><span style="width:<?php echo (int) $pct; ?>%"></span></div>
					<p class="hs-projet__levee"><?php echo esc_html( HS_Templates::prix( $leve ) ); ?> levés sur <?php echo esc_html( HS_Templates::prix( $recherche ) ); ?></p>
				<?php endif; ?>
			</div>
		</article>
	<?php endwhile; ?>
</div>
<?php
wp_reset_postdata();
