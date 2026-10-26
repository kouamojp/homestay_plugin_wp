<?php
/**
 * Fiche détaillée d'un bien. Surchargeable : <thème>/homystay/fiche-bien.php
 * La galerie photos, le calendrier et la réservation seront branchés à l'étape « Réservations ».
 *
 * @var int $bien_id
 */
defined( 'ABSPATH' ) || exit;

$defs = HS_Fields::for_type( 'hs_bien' );
$f    = function ( $k ) use ( $bien_id ) {
	return HS_Fields::get( $bien_id, $k );
};
$aff  = function ( $k ) use ( $f, $defs ) {
	return HS_Shortcodes::format( $f( $k ), $defs[ $k ], $k );
};

$caracs = array_filter( array(
	'Référence'      => $f( 'reference' ),
	'Type'           => $aff( 'type_bien' ),
	'Surface'        => $f( 'surface' ) ? (int) $f( 'surface' ) . ' m²' : '',
	'Chambres'       => $f( 'chambres' ),
	'Salles de bain' => $f( 'salles_bain' ),
	'Capacité'       => $f( 'capacite' ) ? (int) $f( 'capacite' ) . ' pers.' : '',
	'Quartier'       => $f( 'quartier' ),
	'Ville'          => $f( 'ville' ),
) );

$offres = array();
if ( $f( 'offre_courte' ) && $f( 'prix_courte' ) ) {
	$offres['Séjour'] = HS_Templates::prix( $f( 'prix_courte' ), '/ nuit' );
}
if ( $f( 'offre_longue' ) && $f( 'prix_longue' ) ) {
	$offres['Location'] = HS_Templates::prix( $f( 'prix_longue' ), '/ mois' );
}
if ( $f( 'offre_vente' ) && $f( 'prix_vente' ) ) {
	$offres['Achat'] = HS_Templates::prix( $f( 'prix_vente' ) );
}
?>
<section class="hs-fiche">
	<div class="hs-fiche__statut hs-statut--<?php echo esc_attr( $f( 'statut' ) ); ?>"><?php echo esc_html( $aff( 'statut' ) ); ?></div>

	<?php if ( $offres ) : ?>
		<ul class="hs-fiche__offres">
			<?php foreach ( $offres as $label => $prix ) : ?>
				<li><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( $prix ); ?></strong></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<dl class="hs-fiche__caracs">
		<?php foreach ( $caracs as $label => $val ) : ?>
			<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $val ); ?></dd></div>
		<?php endforeach; ?>
	</dl>

	<?php if ( $f( 'video_url' ) ) : ?>
		<div class="hs-fiche__video"><?php echo wp_oembed_get( esc_url( $f( 'video_url' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
	<?php endif; ?>

	<?php if ( $f( 'visite_url' ) ) : ?>
		<p><a class="hs-bouton hs-bouton--secondaire" href="<?php echo esc_url( $f( 'visite_url' ) ); ?>" target="_blank" rel="noopener">Visite virtuelle</a></p>
	<?php endif; ?>

	<?php if ( $f( 'latitude' ) && $f( 'longitude' ) ) : ?>
		<div class="hs-fiche__carte">
			<iframe title="Carte" loading="lazy" src="<?php echo esc_url( sprintf( 'https://www.openstreetmap.org/export/embed.html?bbox=%1$F,%2$F,%3$F,%4$F&marker=%5$F,%6$F', $f( 'longitude' ) - 0.01, $f( 'latitude' ) - 0.006, $f( 'longitude' ) + 0.01, $f( 'latitude' ) + 0.006, $f( 'latitude' ), $f( 'longitude' ) ) ); ?>"></iframe>
		</div>
	<?php endif; ?>
</section>
