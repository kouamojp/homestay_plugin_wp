<?php
/**
 * Grille de biens. Surchargeable : <thème>/homystay/liste-biens.php
 *
 * @var WP_Query $query
 * @var int      $colonnes
 * @var string   $formule
 * @var string   $vide
 * @var bool     $pagination
 */
defined( 'ABSPATH' ) || exit;

if ( ! $query->have_posts() ) {
	echo $vide ? '<p class="hs-vide">' . esc_html( $vide ) . '</p>' : '';
	return;
}
?>
<div class="hs-grille hs-grille--<?php echo (int) $colonnes; ?>">
	<?php
	while ( $query->have_posts() ) :
		$query->the_post();
		echo HS_Templates::render( 'carte-bien.php', array( 'bien_id' => get_the_ID(), 'formule' => $formule ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	endwhile;
	?>
</div>
<?php
if ( $pagination && $query->max_num_pages > 1 ) {
	echo '<nav class="hs-pagination">';
	echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput
		'base'    => esc_url_raw( add_query_arg( 'hs_page', '%#%' ) ),
		'format'  => '',
		'current' => max( 1, (int) ( isset( $_GET['hs_page'] ) ? $_GET['hs_page'] : 1 ) ), // phpcs:ignore WordPress.Security
		'total'     => $query->max_num_pages,
		'prev_text' => '<span aria-label="Page précédente">‹</span>',
		'next_text' => '<span aria-label="Page suivante">›</span>',
		'mid_size'  => 1,
	) );
	echo '</nav>';
}
wp_reset_postdata();
